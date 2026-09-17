<?php

namespace Delete_Guard\Tests\Functional;

use Delete_Guard\Logger;
use Delete_Guard\Settings;
use Delete_Guard\Tests\Test_Case;

defined('ABSPATH') || exit;

final class Guard_Flow_Test extends Test_Case {
	public function name(): string {
		return 'Guard trash/delete/restore flows';
	}

	public function run(): void {
		$editor_id = $this->create_user('editor');
		$admin_id = $this->create_user('administrator');
		$this->assert_true($editor_id > 0 && $admin_id > 0, 'users created');

		$this->test_off_mode($editor_id);
		$this->test_block_trash_editor($editor_id);
		$this->test_block_trash_admin($admin_id);
		$this->test_block_delete_editor($editor_id);
		$this->test_block_delete_admin($admin_id);
		$this->test_restore($editor_id);
		$this->test_cron_empty_allowed($editor_id);
		$this->test_cron_empty_denied($editor_id);
	}

	private function test_off_mode(int $editor_id): void {
		$this->set_page_mode(Settings::MODE_OFF);
		$this->as_user($editor_id);
		$post_id = $this->create_page('Off mode');
		$this->clear_logs_for_post($post_id);

		$result = wp_trash_post($post_id);
		$this->assert_not_null($result, 'off: trash succeeds');
		$this->assert_same('trash', get_post_status($post_id), 'off: status trash');
		$this->assert_same(null, $this->find_log($post_id, Logger::ACTION_TRASH, Logger::STATUS_ALLOWED), 'off: no log');

		$result = wp_delete_post($post_id, true);
		$this->assert_not_null($result, 'off: force delete succeeds');
		$this->assert_same(null, get_post($post_id), 'off: post gone');
	}

	private function test_block_trash_editor(int $editor_id): void {
		$this->set_page_mode(Settings::MODE_BLOCK_TRASH);
		$this->as_user($editor_id);
		$post_id = $this->create_page('Block trash editor');
		$this->clear_logs_for_post($post_id);

		$result = wp_trash_post($post_id);
		$this->assert_false((bool) $result, 'block_trash: editor trash denied');
		$this->assert_same('publish', get_post_status($post_id), 'block_trash: still published');
		$this->assert_not_null(
			$this->find_log($post_id, Logger::ACTION_TRASH, Logger::STATUS_DENIED),
			'block_trash: denied trash logged'
		);

		$result = wp_delete_post($post_id, true);
		$this->assert_false((bool) $result, 'block_trash: editor force delete denied');
		$this->assert_not_null(get_post($post_id), 'block_trash: post still exists');
		$this->assert_not_null(
			$this->find_log($post_id, Logger::ACTION_DELETE, Logger::STATUS_DENIED),
			'block_trash: denied delete logged'
		);
	}

	private function test_block_trash_admin(int $admin_id): void {
		$this->set_page_mode(Settings::MODE_BLOCK_TRASH);
		$this->as_user($admin_id);
		$post_id = $this->create_page('Block trash admin');
		$this->clear_logs_for_post($post_id);

		$result = wp_trash_post($post_id);
		$this->assert_not_null($result, 'block_trash: admin trash allowed');
		$this->assert_same('trash', get_post_status($post_id), 'block_trash: admin trashed');
		$this->assert_not_null(
			$this->find_log($post_id, Logger::ACTION_TRASH, Logger::STATUS_ALLOWED),
			'block_trash: admin trash logged as allowed'
		);
	}

	private function test_block_delete_editor(int $editor_id): void {
		$this->set_page_mode(Settings::MODE_BLOCK_DELETE, false);
		$this->as_user($editor_id);
		$post_id = $this->create_page('Block delete editor');
		$this->clear_logs_for_post($post_id);

		$result = wp_trash_post($post_id);
		$this->assert_not_null($result, 'block_delete: editor trash allowed');
		$this->assert_same('trash', get_post_status($post_id), 'block_delete: in trash');
		$this->assert_not_null(
			$this->find_log($post_id, Logger::ACTION_TRASH, Logger::STATUS_ALLOWED),
			'block_delete: trash logged'
		);

		\Delete_Guard\Plugin::instance()->guard()->clear_logged_keys();
		$result = wp_delete_post($post_id, true);
		$this->assert_false((bool) $result, 'block_delete: editor permanent delete denied');
		$this->assert_same('trash', get_post_status($post_id), 'block_delete: still in trash');
		$this->assert_not_null(
			$this->find_log($post_id, Logger::ACTION_DELETE, Logger::STATUS_DENIED),
			'block_delete: denied delete logged'
		);
	}

	private function test_block_delete_admin(int $admin_id): void {
		$this->set_page_mode(Settings::MODE_BLOCK_DELETE, false);
		$this->as_user($admin_id);
		$post_id = $this->create_page('Block delete admin');
		$this->clear_logs_for_post($post_id);

		wp_trash_post($post_id);
		\Delete_Guard\Plugin::instance()->guard()->clear_logged_keys();
		$result = wp_delete_post($post_id, true);
		$this->assert_not_null($result, 'block_delete: admin can permanently delete');
		$this->assert_same(null, get_post($post_id), 'block_delete: admin deleted post');
		$this->assert_not_null(
			$this->find_log($post_id, Logger::ACTION_DELETE, Logger::STATUS_ALLOWED),
			'block_delete: admin delete logged'
		);
	}

	private function test_restore(int $editor_id): void {
		$this->set_page_mode(Settings::MODE_LOG);
		$this->as_user($editor_id);
		$post_id = $this->create_page('Restore flow');
		$this->clear_logs_for_post($post_id);

		wp_trash_post($post_id);
		\Delete_Guard\Plugin::instance()->guard()->clear_logged_keys();
		$result = wp_untrash_post($post_id);
		$this->assert_not_null($result, 'restore: untrash succeeds');
		$this->assert_not_null(
			$this->find_log($post_id, Logger::ACTION_RESTORE, Logger::STATUS_ALLOWED),
			'restore: logged'
		);
	}

	private function test_cron_empty_allowed(int $editor_id): void {
		$this->set_page_mode(Settings::MODE_BLOCK_DELETE, true);
		$this->as_user($editor_id);
		$post_id = $this->create_page('Cron empty allow');
		wp_trash_post($post_id);
		$this->clear_logs_for_post($post_id);

		$this->as_user(0);
		$this->simulate_cron(true);
		$result = wp_delete_post($post_id, true);
		$this->assert_not_null($result, 'cron empty allowed: delete succeeds');
		$this->assert_not_null(
			$this->find_log($post_id, Logger::ACTION_DELETE, Logger::STATUS_ALLOWED),
			'cron empty allowed: logged'
		);
	}

	private function test_cron_empty_denied(int $editor_id): void {
		$this->set_page_mode(Settings::MODE_BLOCK_DELETE, false);
		$this->as_user($editor_id);
		$post_id = $this->create_page('Cron empty deny');
		wp_trash_post($post_id);
		$old_meta = (int) get_post_meta($post_id, '_wp_trash_meta_time', true);
		$this->clear_logs_for_post($post_id);

		$this->as_user(0);
		$this->simulate_cron(true);
		$result = wp_delete_post($post_id, true);
		$this->assert_false((bool) $result, 'cron empty denied: delete blocked');
		$this->assert_same('trash', get_post_status($post_id), 'cron empty denied: still trash');
		$this->assert_not_null(
			$this->find_log($post_id, Logger::ACTION_DELETE, Logger::STATUS_DENIED),
			'cron empty denied: logged'
		);

		$new_meta = (int) get_post_meta($post_id, '_wp_trash_meta_time', true);
		$this->assert_true($new_meta >= $old_meta, 'cron empty denied: trash meta time bumped');
	}
}
