<?php

declare(strict_types=1);

namespace PloginsAssets\Settings;

defined('ABSPATH') || exit;

/**
 * Reads, normalises and persists the plugin settings stored in the
 * `plogins_assets_settings` option. Single source of truth for the rule shape,
 * so the admin UI and the front-end dequeue engine agree on structure.
 */
final class SettingsRepository
{
    public const OPTION = 'plogins_assets_settings';

    /**
     * Supported rule conditions. `ids` is only consulted for the *_singular
     * conditions; every other condition ignores it.
     */
    public const CONDITIONS = [
        'everywhere',    // every front-end page
        'front_page',    // the site front page
        'blog_home',     // the posts index
        'is_singular',   // any single post/page (optionally limited to ids)
        'not_singular',  // everything except single posts/pages (or except ids)
        'mobile',        // wp_is_mobile() is true
        'desktop',       // wp_is_mobile() is false
    ];

    /**
     * The only conditions AssetManager::matches() reads the ids list for. Every
     * other condition decides on the request alone.
     */
    private const ID_CONDITIONS = ['is_singular', 'not_singular'];

    /** @return array<int, string> */
    public function conditions(): array
    {
        return self::CONDITIONS;
    }

    /**
     * Whether a condition can actually act on the post/page IDs of a rule.
     */
    public function conditionUsesIds(string $condition): bool
    {
        return in_array($condition, self::ID_CONDITIONS, true);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->settings()['enabled'] ?? false);
    }

    /**
     * The configured rules, already normalised to canonical shape.
     *
     * @return array<int, array{handle: string, type: string, condition: string, ids: array<int, int>}>
     */
    public function rules(): array
    {
        $rules = $this->settings()['rules'] ?? [];

        return is_array($rules) ? array_values($rules) : [];
    }

    /**
     * Settings array merged over packaged defaults.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $stored = get_option(self::OPTION, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require PLOGINS_ASSETS_DIR . 'config/defaults.php';

        $merged          = array_merge($defaults, $stored);
        $merged['rules'] = $this->normalizeRules($merged['rules'] ?? []);

        return $merged;
    }

    public function save(bool $enabled, mixed $rawRules): void
    {
        update_option(self::OPTION, [
            'enabled' => $enabled,
            'rules'   => $this->normalizeRules($rawRules),
        ]);
    }

    /**
     * Coerce a raw submitted rules array into the canonical shape, dropping any
     * row without a handle.
     *
     * @param mixed $raw
     * @return array<int, array{handle: string, type: string, condition: string, ids: array<int, int>}>
     */
    public function normalizeRules(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $rules = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $handle = sanitize_text_field((string) ($row['handle'] ?? ''));
            $handle = trim($handle);
            if ('' === $handle) {
                continue;
            }

            $type = ('style' === ($row['type'] ?? '')) ? 'style' : 'script';

            $condition = (string) ($row['condition'] ?? 'everywhere');
            if (! in_array($condition, self::CONDITIONS, true)) {
                $condition = 'everywhere';
            }

            // Only the two singular conditions reach the ids downstream. Keeping
            // them on the other five was a quiet lie: the merchant typed "12, 34"
            // next to "Everywhere", saw it come back on reload as if it were in
            // force, and every shopper on every page still lost the handle. Store
            // nothing we cannot apply.
            $ids = $this->conditionUsesIds($condition)
                ? $this->normalizeIds($row['ids'] ?? '')
                : [];

            $rules[] = [
                'handle'    => $handle,
                'type'      => $type,
                'condition' => $condition,
                'ids'       => $ids,
            ];
        }

        return $rules;
    }

    /**
     * Accept either an array of IDs or a comma-separated string and return a
     * clean, unique, positive-int list.
     *
     * @param mixed $raw
     * @return array<int, int>
     */
    private function normalizeIds(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\s,]+/', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }

        // Only clean positive integers: a stray "-5" or "abc" is dropped, not
        // coerced (absint would turn -5 into 5 and target the wrong page).
        $ids = array_map(static fn (mixed $v): int => (int) $v, $raw);
        $ids = array_filter($ids, static fn (int $n): bool => $n > 0);

        return array_values(array_unique($ids));
    }
}
