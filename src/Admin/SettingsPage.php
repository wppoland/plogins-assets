<?php

declare(strict_types=1);

namespace PloginsAssets\Admin;

use PloginsAssets\Contract\HasHooks;
use PloginsAssets\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Settings screen under Settings -> Plogins Assets. Renders the master switch
 * and the repeatable rule table, and handles the nonce-guarded save.
 */
final class SettingsPage implements HasHooks
{
    private const SLUG   = 'plogins-assets';
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
            __('Plogins Assets', 'plogins-assets'),
            __('Plogins Assets', 'plogins-assets'),
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
            wp_die(esc_html__('You are not allowed to do this.', 'plogins-assets'));
        }

        check_admin_referer(self::ACTION);

        $enabled = ! empty($_POST['enabled']);

        // Rules are re-normalised in the repository; here we only unslash. Each
        // field is sanitised inside SettingsRepository::normalizeRules().
        $rawRules = isset($_POST['rules']) && is_array($_POST['rules'])
            ? wp_unslash($_POST['rules']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in normalizeRules()
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
            <h1><?php esc_html_e('Plogins Assets', 'plogins-assets'); ?></h1>
            <p class="description">
                <?php esc_html_e('Remove specific scripts and styles from the pages that do not need them. Lighter pages load faster.', 'plogins-assets'); ?>
            </p>

            <?php if (isset($_GET['updated'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'plogins-assets'); ?></p></div>
            <?php endif; ?>

            <div class="notice notice-warning inline">
                <p><?php esc_html_e('Removing a handle that another script depends on can break page functionality. Change one rule at a time and check the front end.', 'plogins-assets'); ?></p>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <?php wp_nonce_field(self::ACTION); ?>

                <p>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>>
                        <strong><?php esc_html_e('Enable conditional asset loading', 'plogins-assets'); ?></strong>
                    </label>
                </p>

                <table class="widefat plogins-assets-rules">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Handle', 'plogins-assets'); ?></th>
                            <th><?php esc_html_e('Type', 'plogins-assets'); ?></th>
                            <th><?php esc_html_e('Remove when', 'plogins-assets'); ?></th>
                            <th><?php esc_html_e('Post/Page IDs', 'plogins-assets'); ?></th>
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
                        <?php esc_html_e('+ Add rule', 'plogins-assets'); ?>
                    </button>
                </p>

                <?php $this->renderHandleHints(); ?>

                <?php submit_button(__('Save rules', 'plogins-assets')); ?>
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
                    placeholder="<?php esc_attr_e('e.g. contact-form-7', 'plogins-assets'); ?>">
            </td>
            <td>
                <select name="<?php echo esc_attr($name); ?>[type]">
                    <option value="script" <?php selected($rule['type'], 'script'); ?>><?php esc_html_e('Script (JS)', 'plogins-assets'); ?></option>
                    <option value="style" <?php selected($rule['type'], 'style'); ?>><?php esc_html_e('Style (CSS)', 'plogins-assets'); ?></option>
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
            <td>
                <input type="text" class="regular-text" name="<?php echo esc_attr($name); ?>[ids]"
                    value="<?php echo esc_attr(implode(', ', $rule['ids'])); ?>"
                    placeholder="<?php esc_attr_e('optional, e.g. 12, 34', 'plogins-assets'); ?>">
            </td>
            <td>
                <button type="button" class="button-link plogins-assets-remove" aria-label="<?php esc_attr_e('Remove rule', 'plogins-assets'); ?>">&times;</button>
            </td>
        </tr>
        <?php
    }

    private function conditionLabel(string $cond): string
    {
        $labels = [
            'everywhere'   => __('Everywhere', 'plogins-assets'),
            'front_page'   => __('On the front page', 'plogins-assets'),
            'blog_home'    => __('On the blog posts index', 'plogins-assets'),
            'is_singular'  => __('On single posts/pages (matching IDs)', 'plogins-assets'),
            'not_singular' => __('Except single posts/pages (matching IDs)', 'plogins-assets'),
            'mobile'       => __('On mobile devices', 'plogins-assets'),
            'desktop'      => __('On desktop devices', 'plogins-assets'),
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
