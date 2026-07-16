<?php

declare(strict_types=1);

namespace PloginsAssets;

defined('ABSPATH') || exit;

/**
 * Idempotent schema/version migrations, run on every boot. Compares a stored
 * option against VERSION and applies forward steps as needed.
 */
final class Migrator
{
    private const OPTION = 'plogins_assets_db_version';

    public function maybeMigrate(): void
    {
        $current = (string) get_option(self::OPTION, '0');

        if (version_compare($current, VERSION, '>=')) {
            return;
        }

        // No custom tables. Rules live in the plogins_assets_settings option,
        // merged over packaged defaults at read time.

        update_option(self::OPTION, VERSION, false);
    }
}
