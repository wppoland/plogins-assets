<?php
/**
 * ponytail: one runnable self-check for the money path (rule sanitisation).
 * Run:  php tests/test-normalize.php
 * No framework: shims the two WP functions the repo touches, then asserts.
 *
 * @package PloginsAssets
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);
define('PLOGINS_ASSETS_DIR', dirname(__DIR__) . '/');

// Minimal shims for the WP functions SettingsRepository::normalizeRules() calls.
function sanitize_text_field(string $s): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', wp_strip_all_tags($s)) ?? '');
}
function wp_strip_all_tags(string $s): string
{
    return trim(strip_tags($s));
}
function absint(mixed $n): int
{
    return abs((int) $n);
}

require dirname(__DIR__) . '/src/Settings/SettingsRepository.php';

use PloginsAssets\Settings\SettingsRepository;

$repo = new SettingsRepository();

// 1. Rows without a handle are dropped; handle is trimmed + tag-stripped.
$out = $repo->normalizeRules([
    ['handle' => '  contact-form-7  ', 'type' => 'script', 'condition' => 'not_singular', 'ids' => ''],
    ['handle' => '', 'type' => 'style', 'condition' => 'everywhere'],
    ['handle' => "<b>slider</b>", 'type' => 'style', 'condition' => 'is_singular', 'ids' => '12, 34, 34, -5, abc'],
]);
assert(count($out) === 2, 'empty-handle row must be dropped');
assert($out[0]['handle'] === 'contact-form-7', 'handle must be trimmed');
assert($out[0]['type'] === 'script');
assert($out[0]['ids'] === []);

// 2. Type defaults to script; unknown condition falls back to everywhere.
$out2 = $repo->normalizeRules([['handle' => 'x', 'type' => 'bogus', 'condition' => 'nope']]);
assert($out2[0]['type'] === 'script', 'bad type -> script');
assert($out2[0]['condition'] === 'everywhere', 'bad condition -> everywhere');

// 3. IDs: tags stripped from handle, ids parsed, deduped, positives only.
assert($out[1]['handle'] === 'slider', 'tags stripped from handle');
assert($out[1]['ids'] === [12, 34], 'ids parsed/deduped/positive-only, got ' . implode(',', $out[1]['ids']));

// 4. IDs are dropped for the conditions that never read them, so nothing comes
// back to the admin screen looking like it narrows the rule.
$out3 = $repo->normalizeRules([
    ['handle' => 'a', 'condition' => 'everywhere', 'ids' => '12, 34'],
    ['handle' => 'b', 'condition' => 'mobile', 'ids' => '12'],
    ['handle' => 'c', 'condition' => 'not_singular', 'ids' => '12'],
]);
assert($out3[0]['ids'] === [], 'everywhere must not keep ids');
assert($out3[1]['ids'] === [], 'mobile must not keep ids');
assert($out3[2]['ids'] === [12], 'not_singular must keep ids');

// 5. Non-array input is safe.
assert($repo->normalizeRules('garbage') === []);
assert($repo->normalizeRules(['not-a-row', 42]) === []);

echo "OK - all normalizeRules assertions passed\n";
