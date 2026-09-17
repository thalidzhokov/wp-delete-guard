<?php
/**
 * Uninstall Delete Guard.
 *
 * @package Delete_Guard
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Remove plugin data for the current site.
 */
function delete_guard_uninstall_site(): void {
	global $wpdb;

	delete_option('delete_guard_settings');
	delete_option('delete_guard_db_version');

	$table = $wpdb->prefix . 'delete_guard_log';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefixed and fixed.
	$wpdb->query("DROP TABLE IF EXISTS {$table}");
}

if (is_multisite()) {
	$site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
	foreach ($site_ids as $site_id) {
		switch_to_blog((int) $site_id);
		delete_guard_uninstall_site();
		restore_current_blog();
	}
} else {
	delete_guard_uninstall_site();
}
