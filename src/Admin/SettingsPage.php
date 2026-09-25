<?php

declare(strict_types=1);

namespace PloginsAssets\Admin;

use PloginsAssets\Contract\HasHooks;
use PloginsAssets\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Settings screen under Settings -> Pagelean. Renders the master switch
 * and the repeatable rule table, and handles the nonce-guarded save.
 */
final class SettingsPage implements HasHooks
{
    private const SLUG   = 'pagelean';
    private const ACTION = 'plogins_assets_save';

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_post_' . self::ACTION, [$this, 'save']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function addMenu(): void
    {
        add_menu_page(
            __('Pagelean', 'pagelean'),
            __('Pagelean', 'pagelean'),
            'manage_options',
            self::SLUG,
            [$this, 'render'],
            'dashicons-performance',
            81
        );
    }

    public function assets(string $hook): void
    {
        if ('toplevel_page_' . self::SLUG !== $hook) {
            return;
        }

        wp_enqueue_style(
            'plogins-assets-admin',
            PLOGINS_ASSETS_URL . 'assets/css/admin.css',
            [],
            \PloginsAssets\VERSION
        );
        wp_enqueue_script(
            'plogins-assets-admin',
            PLOGINS_ASSETS_URL . 'assets/js/admin.js',
            [],
            \PloginsAssets\VERSION,
            true
        );
    }

    public function save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'pagelean'));
        }

        check_admin_referer(self::ACTION);

        $enabled = ! empty($_POST['enabled']);

        // Every field is single-line text, sanitised here on read; the
        // repository's normalizeRules() then validates each one.
        $rawRules = isset($_POST['rules']) && is_array($_POST['rules'])
            ? map_deep(wp_unslash($_POST['rules']), 'sanitize_text_field')
            : [];

        $this->settings->save($enabled, $rawRules);

        wp_safe_redirect(add_query_arg(
            ['page' => self::SLUG, 'updated' => '1'],
            admin_url('admin.php')
        ));
        exit;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $enabled = $this->settings->isEnabled();
        $rules   = $this->settings->rules();
        if ([] === $rules) {
            // One empty starter row so the table is never blank.
            $rules = [['handle' => '', 'type' => 'script', 'condition' => 'everywhere', 'ids' => []]];
        }

        ?>
        <div class="wrap plogins-assets">
            <h1><?php esc_html_e('Pagelean', 'pagelean'); ?></h1>
            <p class="description">
                <?php esc_html_e('Remove specific scripts and styles from the pages that do not need them. Lighter pages load faster.', 'pagelean'); ?>
            </p>

            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view parameter, only the literal '1' shows the notice.
            if (isset($_GET['updated']) && '1' === sanitize_key(wp_unslash($_GET['updated']))) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'pagelean'); ?></p></div>
            <?php endif; ?>

            <div class="notice notice-warning inline">
                <p><?php esc_html_e('Removing a handle that another script depends on can break page functionality. Change one rule at a time and check the front end.', 'pagelean'); ?></p>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <?php wp_nonce_field(self::ACTION); ?>

                <p>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>>
                        <strong><?php esc_html_e('Enable conditional asset loading', 'pagelean'); ?></strong>
                    </label>
                </p>

                <table class="widefat plogins-assets-rules">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Handle', 'pagelean'); ?></th>
                            <th><?php esc_html_e('Type', 'pagelean'); ?></th>
                            <th><?php esc_html_e('Remove when', 'pagelean'); ?></th>
                            <th><?php esc_html_e('Post/Page IDs', 'pagelean'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="plogins-assets-rows">
                        <?php foreach ($rules as $i => $rule) : ?>
                            <?php $this->renderRow((int) $i, $rule); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button type="button" class="button" id="plogins-assets-add">
                        <?php esc_html_e('+ Add rule', 'pagelean'); ?>
                    </button>
                </p>

                <?php $this->renderHandleHints(); ?>

                <?php submit_button(__('Save rules', 'pagelean')); ?>
            </form>

            <template id="plogins-assets-template">
                <?php $this->renderRow(0, ['handle' => '', 'type' => 'script', 'condition' => 'everywhere', 'ids' => []], true); ?>
            </template>
        </div>
        <?php
    }

    /**
     * @param array{handle: string, type: string, condition: string, ids: array<int, int>} $rule
     */
    private function renderRow(int $index, array $rule, bool $template = false): void
    {
        $name = $template ? 'rules[__INDEX__]' : 'rules[' . $index . ']';
        ?>
        <tr class="plogins-assets-row">
            <td>
                <input type="text" class="regular-text" list="plogins-assets-handles"
                    name="<?php echo esc_attr($name); ?>[handle]"
                    value="<?php echo esc_attr($rule['handle']); ?>"
                    placeholder="<?php esc_attr_e('e.g. contact-form-7', 'pagelean'); ?>">
            </td>
            <td>
                <select name="<?php echo esc_attr($name); ?>[type]">
                    <option value="script" <?php selected($rule['type'], 'script'); ?>><?php esc_html_e('Script (JS)', 'pagelean'); ?></option>
                    <option value="style" <?php selected($rule['type'], 'style'); ?>><?php esc_html_e('Style (CSS)', 'pagelean'); ?></option>
                </select>
            </td>
            <td>
                <select name="<?php echo esc_attr($name); ?>[condition]">
                    <?php foreach ($this->settings->conditions() as $cond) : ?>
                        <option value="<?php echo esc_attr($cond); ?>" <?php selected($rule['condition'], $cond); ?>>
                            <?php echo esc_html($this->conditionLabel($cond)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <?php
            // The dequeue engine reads these ids for the two single post/page
            // conditions only. The field used to stay open on every row, so an
            // admin could pick "Everywhere", type "12, 34", save, and see it come
            // back looking active while visitors lost the handle on the whole
            // site. Closed unless the chosen condition can honour it.
            $usesIds = $this->settings->conditionUsesIds($rule['condition']);
            ?>
            <td class="plogins-assets-ids">
                <input type="text" class="regular-text" name="<?php echo esc_attr($name); ?>[ids]"
                    value="<?php echo esc_attr(implode(', ', $rule['ids'])); ?>"
                    placeholder="<?php esc_attr_e('optional, e.g. 12, 34', 'pagelean'); ?>"
                    <?php disabled(! $usesIds); ?>>
                <span class="description plogins-assets-ids-note"<?php echo $usesIds ? ' hidden' : ''; ?>>
                    <?php esc_html_e('Used only by the two single posts/pages conditions.', 'pagelean'); ?>
                </span>
            </td>
            <td>
                <button type="button" class="button-link plogins-assets-remove" aria-label="<?php esc_attr_e('Remove rule', 'pagelean'); ?>">&times;</button>
            </td>
        </tr>
        <?php
    }

    private function conditionLabel(string $cond): string
    {
        $labels = [
            'everywhere'   => __('Everywhere', 'pagelean'),
            'front_page'   => __('On the front page', 'pagelean'),
            'blog_home'    => __('On the blog posts index', 'pagelean'),
            'is_singular'  => __('On single posts/pages (matching IDs)', 'pagelean'),
            'not_singular' => __('Except single posts/pages (matching IDs)', 'pagelean'),
            'mobile'       => __('On mobile devices', 'pagelean'),
            'desktop'      => __('On desktop devices', 'pagelean'),
        ];

        return $labels[$cond] ?? $cond;
    }

    /**
     * A datalist of currently registered handles so admins get autocomplete
     * hints instead of guessing. It is only a hint; any handle can be typed.
     */
    private function renderHandleHints(): void
    {
        $handles = array_keys(wp_scripts()->registered);
        $handles = array_merge($handles, array_keys(wp_styles()->registered));
        $handles = array_values(array_unique($handles));
        sort($handles);

        echo '<datalist id="plogins-assets-handles">';
        foreach ($handles as $handle) {
            echo '<option value="' . esc_attr((string) $handle) . '"></option>';
        }
        echo '</datalist>';
    }
}
