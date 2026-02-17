<?php
defined('ABSPATH') || exit;

class ICSFM_Deactivator {

    public static function deactivate(): void {
        wp_clear_scheduled_hook('icsfm_check_stale_feeds');

        ICSFM_Logger::info('system', 'Plugin deactivated');
    }
}
