<?php

namespace Delete_Guard\Tests;

defined('ABSPATH') || exit;

abstract class Test_Case {
	/** @var list<string> */
	private array $failures = [];

	/** @var list<int> */
	private array $created_post_ids = [];

	/** @var list<int> */
	private array $created_user_ids = [];

	/** @var mixed */
	private $settings_backup;

	abstract public function name(): string;

	abstract public function run(): void;

	public function set_up(): void {
		$this->failures = [];
		$this->created_post_ids = [];
		$this->created_user_ids = [];
		$this->settings_backup = get_option(\Delete_Guard\Settings::OPTION_KEY, null);
		\Delete_Guard\Logger::create_table();
		\Delete_Guard\Plugin::instance()->guard()->clear_logged_keys();
		wp_set_current_user(0);
		remove_all_filters('wp_doing_cron');
	}

	public function tear_down(): void {
		foreach ($this->created_post_ids as $post_id) {
			wp_delete_post($post_id, true);
		}
		foreach ($this->created_user_ids as $user_id) {
			if (!function_exists('wp_delete_user')) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			// Тема цепляется на delete_user к таблицам, которых может не быть в локали.
			remove_all_actions('delete_user');
			remove_all_actions('deleted_user');
			wp_delete_user($user_id);
		}

		if ($this->settings_backup === null || $this->settings_backup === false) {
			delete_option(\Delete_Guard\Settings::OPTION_KEY);
		} else {
			update_option(\Delete_Guard\Settings::OPTION_KEY, $this->settings_backup, false);
		}

		wp_set_current_user(0);
		remove_all_filters('wp_doing_cron');
		\Delete_Guard\Plugin::instance()->guard()->clear_logged_keys();
	}

	/**
	 * @return list<string>
	 */
	public function failures(): array {
		return $this->failures;
	}

	public function record_exception(\Throwable $e): void {
		$this->failures[] = 'Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
	}

	protected function assert_true(bool $condition, string $message): void {
		if (!$condition) {
			$this->failures[] = $message;
		}
	}

	protected function assert_false(bool $condition, string $message): void {
		$this->assert_true(!$condition, $message);
	}

	protected function assert_same(mixed $expected, mixed $actual, string $message): void {
		if ($expected !== $actual) {
			$this->failures[] = $message . sprintf(
				' (expected %s, got %s)',
				wp_json_encode($expected),
				wp_json_encode($actual)
			);
		}
	}

	protected function assert_not_null(mixed $value, string $message): void {
		$this->assert_true($value !== null && $value !== false, $message);
	}

	/**
	 * @param array{mode: string, allow_empty_trash_days?: bool} $row
	 */
	protected function set_page_mode(string $mode, bool $allow_empty = false): void {
		\Delete_Guard\Settings::save([
			'page' => [
				'mode' => $mode,
				'allow_empty_trash_days' => $allow_empty,
			],
		]);
	}

	protected function create_page(string $title = 'DG Test Page'): int {
		$post_id = wp_insert_post([
			'post_title' => $title . ' ' . wp_generate_password(6, false),
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_content' => 'delete-guard test',
		], true);

		if (is_wp_error($post_id)) {
			$this->failures[] = 'Failed to create page: ' . $post_id->get_error_message();
			return 0;
		}

		$this->created_post_ids[] = (int) $post_id;
		return (int) $post_id;
	}

	protected function create_user(string $role): int {
		$login = 'dg_test_' . $role . '_' . wp_generate_password(8, false);
		$user_id = wp_insert_user([
			'user_login' => $login,
			'user_pass' => wp_generate_password(16),
			'user_email' => $login . '@example.test',
			'role' => $role,
		]);

		if (is_wp_error($user_id)) {
			$this->failures[] = 'Failed to create user: ' . $user_id->get_error_message();
			return 0;
		}

		$this->created_user_ids[] = (int) $user_id;
		return (int) $user_id;
	}

	protected function as_user(int $user_id): void {
		wp_set_current_user($user_id);
		\Delete_Guard\Plugin::instance()->guard()->clear_logged_keys();
	}

	protected function simulate_cron(bool $enabled = true): void {
		remove_all_filters('wp_doing_cron');
		if ($enabled) {
			add_filter('wp_doing_cron', '__return_true');
		}
	}

	/**
	 * @return list<object>
	 */
	protected function latest_logs(int $post_id, int $limit = 20): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test helper; table name from Logger::table_name().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}delete_guard_log WHERE post_id = %d ORDER BY id DESC LIMIT %d",
				$post_id,
				$limit
			)
		);

		return is_array($rows) ? $rows : [];
	}

	protected function clear_logs_for_post(int $post_id): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test helper cleanup.
		$wpdb->delete(\Delete_Guard\Logger::table_name(), ['post_id' => $post_id], ['%d']);
		\Delete_Guard\Plugin::instance()->guard()->clear_logged_keys();
	}

	protected function find_log(int $post_id, string $action, string $status): ?object {
		foreach ($this->latest_logs($post_id) as $row) {
			if ($row->action === $action && $row->status === $status) {
				return $row;
			}
		}
		return null;
	}
}
