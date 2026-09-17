<?php

namespace Delete_Guard\Tests\Functional;

use Delete_Guard\Logger;
use Delete_Guard\Settings;
use Delete_Guard\Tests\Test_Case;

defined('ABSPATH') || exit;

final class Logging_Test extends Test_Case {
	public function name(): string {
		return 'Logging fields and log-only mode';
	}

	public function run(): void {
		$editor_id = $this->create_user('editor');
		$this->assert_true($editor_id > 0, 'editor created');

		$this->set_page_mode(Settings::MODE_LOG);
		$this->as_user($editor_id);
		$post_id = $this->create_page('Logging fields');
		$this->clear_logs_for_post($post_id);

		wp_trash_post($post_id);
		$log = $this->find_log($post_id, Logger::ACTION_TRASH, Logger::STATUS_ALLOWED);
		$this->assert_not_null($log, 'log-only: trash logged');
		if ($log) {
			$this->assert_same((string) $editor_id, (string) $log->user_id, 'log: user_id');
			$this->assert_same('page', $log->post_type, 'log: post_type');
			$this->assert_true($log->post_title !== '', 'log: post_title stored');
			$this->assert_true($log->source !== '', 'log: source stored');
			$this->assert_true($log->created_at !== '', 'log: created_at stored');
		}

		\Delete_Guard\Plugin::instance()->guard()->clear_logged_keys();
		wp_delete_post($post_id, true);
		$del = $this->find_log($post_id, Logger::ACTION_DELETE, Logger::STATUS_ALLOWED);
		$this->assert_not_null($del, 'log-only: delete logged');
		if ($del) {
			$this->assert_true($del->post_title !== '', 'log: title snapshot survives delete');
		}

		$purged = Logger::purge_older_than_days(30);
		$this->assert_true(is_int($purged) && $purged >= 0, 'purge returns int');
	}
}
