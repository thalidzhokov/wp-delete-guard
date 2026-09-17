<?php

namespace Delete_Guard\Tests\Functional;

use Delete_Guard\Logger;
use Delete_Guard\Policy;
use Delete_Guard\Settings;
use Delete_Guard\Tests\Test_Case;

defined('ABSPATH') || exit;

final class Policy_Test extends Test_Case {
	public function name(): string {
		return 'Policy decide matrix';
	}

	public function run(): void {
		$editor_id = $this->create_user('editor');
		$admin_id = $this->create_user('administrator');
		$this->assert_true($editor_id > 0 && $admin_id > 0, 'test users created');

		// Off: no logging flag.
		$this->set_page_mode(Settings::MODE_OFF);
		$this->as_user($editor_id);
		$d = Policy::decide(Logger::ACTION_TRASH, 'page');
		$this->assert_false($d['enabled'], 'off: enabled=false');
		$this->assert_true($d['allow'], 'off: allow=true');
		$this->assert_false($d['should_log'], 'off: should_log=false');

		// Log only.
		$this->set_page_mode(Settings::MODE_LOG);
		$d = Policy::decide(Logger::ACTION_DELETE, 'page');
		$this->assert_true($d['enabled'], 'log: enabled');
		$this->assert_true($d['allow'], 'log: allow delete');
		$this->assert_true($d['should_log'], 'log: should_log');

		// Block trash: editor cannot trash or delete.
		$this->set_page_mode(Settings::MODE_BLOCK_TRASH);
		$this->as_user($editor_id);
		$d = Policy::decide(Logger::ACTION_TRASH, 'page');
		$this->assert_false($d['allow'], 'block_trash: editor cannot trash');
		$d = Policy::decide(Logger::ACTION_DELETE, 'page');
		$this->assert_false($d['allow'], 'block_trash: editor cannot delete');
		$d = Policy::decide(Logger::ACTION_RESTORE, 'page');
		$this->assert_true($d['allow'], 'block_trash: restore always allowed');

		// Admin bypass.
		$this->as_user($admin_id);
		$d = Policy::decide(Logger::ACTION_TRASH, 'page');
		$this->assert_true($d['allow'], 'block_trash: admin can trash');
		$d = Policy::decide(Logger::ACTION_DELETE, 'page');
		$this->assert_true($d['allow'], 'block_trash: admin can delete');

		// Block delete: trash ok, delete blocked for editor.
		$this->set_page_mode(Settings::MODE_BLOCK_DELETE, false);
		$this->as_user($editor_id);
		$d = Policy::decide(Logger::ACTION_TRASH, 'page');
		$this->assert_true($d['allow'], 'block_delete: editor can trash');
		$d = Policy::decide(Logger::ACTION_DELETE, 'page');
		$this->assert_false($d['allow'], 'block_delete: editor cannot delete');

		$this->as_user($admin_id);
		$d = Policy::decide(Logger::ACTION_DELETE, 'page');
		$this->assert_true($d['allow'], 'block_delete: admin can delete');

		// EMPTY via cron for editor/system.
		$this->set_page_mode(Settings::MODE_BLOCK_DELETE, true);
		$this->as_user(0);
		$this->simulate_cron(true);
		$d = Policy::decide(Logger::ACTION_DELETE, 'page');
		$this->assert_true($d['allow'], 'block_delete+empty: cron may delete');

		$this->simulate_cron(false);
		$d = Policy::decide(Logger::ACTION_DELETE, 'page');
		$this->assert_false($d['allow'], 'block_delete+empty: non-cron still blocked');
	}
}
