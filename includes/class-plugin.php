<?php

namespace Delete_Guard;

defined('ABSPATH') || exit;

final class Plugin {
	private static ?self $instance = null;

	private Guard $guard;
	private Admin $admin;

	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->guard = new Guard();
		$this->admin = new Admin();
	}

	public static function activate(bool $network_wide = false): void {
		if (is_multisite() && $network_wide) {
			$site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
			foreach ($site_ids as $site_id) {
				switch_to_blog((int) $site_id);
				Logger::create_table();
				update_option('delete_guard_db_version', DELETE_GUARD_VERSION, false);
				restore_current_blog();
			}
			return;
		}

		Logger::create_table();
		update_option('delete_guard_db_version', DELETE_GUARD_VERSION, false);
	}

	public function init(): void {
		if ((string) get_option('delete_guard_db_version', '') !== DELETE_GUARD_VERSION) {
			Logger::create_table();
			update_option('delete_guard_db_version', DELETE_GUARD_VERSION, false);
		}

		$this->guard->init();

		if (is_admin()) {
			$this->admin->init();
		}

		if (is_multisite()) {
			add_action('wp_initialize_site', [$this, 'on_initialize_site'], 20, 1);
		}
	}

	/**
	 * @param \WP_Site $site
	 */
	public function on_initialize_site($site): void {
		if (!($site instanceof \WP_Site)) {
			return;
		}

		switch_to_blog((int) $site->blog_id);
		Logger::create_table();
		update_option('delete_guard_db_version', DELETE_GUARD_VERSION, false);
		restore_current_blog();
	}
}
