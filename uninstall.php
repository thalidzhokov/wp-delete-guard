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
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- uninstall drops our own table.
	$wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($table) . '`');
}

if (is_multisite()) {
	$delete_guard_site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
	foreach ($delete_guard_site_ids as $delete_guard_site_id) {
		switch_to_blog((int) $delete_guard_site_id);
		delete_guard_uninstall_site();
		restore_current_blog();
	}
} else {
	delete_guard_uninstall_site();
}
