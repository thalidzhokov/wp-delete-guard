<?php

namespace Delete_Guard;

defined('ABSPATH') || exit;

final class Logger {
	public const STATUS_ALLOWED = 'allowed';
	public const STATUS_DENIED = 'denied';

	public const ACTION_TRASH = 'trash';
	public const ACTION_DELETE = 'delete';
	public const ACTION_RESTORE = 'restore';

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'delete_guard_log';
	}

	public static function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			status varchar(20) NOT NULL,
			action varchar(20) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_type varchar(64) NOT NULL DEFAULT '',
			post_title text NOT NULL,
			source varchar(20) NOT NULL DEFAULT 'code',
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY post_id (post_id),
			KEY user_id (user_id),
			KEY status_action (status, action)
		) {$charset};";

		dbDelta($sql);
	}

	/**
	 * @param array{
	 *   status: string,
	 *   action: string,
	 *   post_id?: int,
	 *   post_type?: string,
	 *   post_title?: string,
	 *   user_id?: int,
	 *   source?: string
	 * } $entry
	 */
	public static function write(array $entry): void {
		global $wpdb;

		$status = $entry['status'] ?? '';
		$action = $entry['action'] ?? '';
		if (
			!in_array($status, [self::STATUS_ALLOWED, self::STATUS_DENIED], true)
			|| !in_array($action, [self::ACTION_TRASH, self::ACTION_DELETE, self::ACTION_RESTORE], true)
		) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom log table.
		$wpdb->insert(
			self::table_name(),
			[
				'created_at' => current_time('mysql'),
				'status' => $status,
				'action' => $action,
				'user_id' => isset($entry['user_id']) ? (int) $entry['user_id'] : self::current_user_id(),
				'post_id' => isset($entry['post_id']) ? (int) $entry['post_id'] : 0,
				'post_type' => isset($entry['post_type']) ? substr((string) $entry['post_type'], 0, 64) : '',
				'post_title' => isset($entry['post_title']) ? (string) $entry['post_title'] : '',
				'source' => isset($entry['source']) ? substr((string) $entry['source'], 0, 20) : self::detect_source(),
			],
			['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']
		);
	}

	/**
	 * @param array{status?: string, action?: string, post_type?: string, user_id?: int, paged?: int, per_page?: int} $args
	 * @return array{items: list<object>, total: int}
	 */
	public static function query(array $args = []): array {
		global $wpdb;

		$per_page = max(1, min(200, (int) ($args['per_page'] ?? 50)));
		$paged = max(1, (int) ($args['paged'] ?? 1));
		$offset = ($paged - 1) * $per_page;

		$status = isset($args['status']) ? sanitize_key((string) $args['status']) : '';
		$action = isset($args['action']) ? sanitize_key((string) $args['action']) : '';
		$post_type = isset($args['post_type']) ? sanitize_key((string) $args['post_type']) : '';
		$user_filter = array_key_exists('user_id', $args) && $args['user_id'] !== '' && $args['user_id'] !== null;
		$user_id = $user_filter ? (int) $args['user_id'] : 0;

		$where = '1=1';
		$params = [];

		if ($status !== '') {
			$where .= ' AND status = %s';
			$params[] = $status;
		}
		if ($action !== '') {
			$where .= ' AND action = %s';
			$params[] = $action;
		}
		if ($post_type !== '') {
			$where .= ' AND post_type = %s';
			$params[] = $post_type;
		}
		if ($user_filter) {
			$where .= ' AND user_id = %d';
			$params[] = $user_id;
		}

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}delete_guard_log WHERE {$where}";
		$list_sql = "SELECT * FROM {$wpdb->prefix}delete_guard_log WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";

		if ($params) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is built from fixed fragments + placeholders; values bound via prepare().
			$total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$items = $wpdb->get_results($wpdb->prepare($list_sql, array_merge($params, [$per_page, $offset])));
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- no user-supplied SQL; table from $wpdb->prefix.
			$total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}delete_guard_log WHERE 1=%d", 1));
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}delete_guard_log WHERE 1=%d ORDER BY id DESC LIMIT %d OFFSET %d",
					1,
					$per_page,
					$offset
				)
			);
		}

		return [
			'items' => is_array($items) ? $items : [],
			'total' => $total,
		];
	}

	public static function purge_older_than_days(int $days = 30): int {
		global $wpdb;

		$days = max(1, $days);
		$cutoff_local = get_date_from_gmt(
			gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS)),
			'Y-m-d H:i:s'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}delete_guard_log WHERE created_at < %s",
				$cutoff_local
			)
		);

		return is_int($deleted) ? $deleted : 0;
	}

	public static function current_user_id(): int {
		return (int) get_current_user_id();
	}

	public static function detect_source(): string {
		if (defined('WP_CLI') && \WP_CLI) {
			return 'cli';
		}
		if (wp_doing_cron()) {
			return 'cron';
		}
		if (defined('REST_REQUEST') && \REST_REQUEST) {
			return 'rest';
		}
		if (is_admin()) {
			return 'admin';
		}

		return 'code';
	}

	/**
	 * @param \WP_Post $post
	 * @return array{post_id: int, post_type: string, post_title: string}
	 */
	public static function snapshot_from_post(\WP_Post $post): array {
		return [
			'post_id' => (int) $post->ID,
			'post_type' => (string) $post->post_type,
			'post_title' => self::resolve_post_title($post),
		];
	}

	private static function resolve_post_title(\WP_Post $post): string {
		if ($post->post_type === 'nav_menu_item') {
			return self::nav_menu_item_label($post);
		}

		return (string) $post->post_title;
	}

	/**
	 * Classic menus: term name is the menu; item post_title is often empty for page links.
	 */
	private static function nav_menu_item_label(\WP_Post $post): string {
		$item_title = '';
		$item = wp_setup_nav_menu_item($post);
		if (is_object($item) && isset($item->title)) {
			$item_title = wp_strip_all_tags((string) $item->title);
		}
		if ($item_title === '' && $post->post_title !== '') {
			$item_title = (string) $post->post_title;
		}

		$menu_name = '';
		$menus = wp_get_object_terms((int) $post->ID, 'nav_menu', ['fields' => 'names']);
		if (!is_wp_error($menus) && isset($menus[0]) && is_string($menus[0]) && $menus[0] !== '') {
			$menu_name = $menus[0];
		}

		if ($menu_name !== '' && $item_title !== '') {
			return $menu_name . ' › ' . $item_title;
		}
		if ($menu_name !== '') {
			return $menu_name;
		}

		return $item_title;
	}
}
