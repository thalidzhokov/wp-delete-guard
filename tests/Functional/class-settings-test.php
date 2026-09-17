<?php

namespace Delete_Guard\Tests\Functional;

use Delete_Guard\Settings;
use Delete_Guard\Tests\Test_Case;

defined('ABSPATH') || exit;

final class Settings_Test extends Test_Case {
	public function name(): string {
		return 'Settings save/normalize';
	}

	public function run(): void {
		Settings::save([
			'page' => [
				'mode' => Settings::MODE_BLOCK_DELETE,
				'allow_empty_trash_days' => true,
			],
			'post' => [
				'mode' => Settings::MODE_LOG,
				'allow_empty_trash_days' => true,
			],
			'unknown_type_xyz' => [
				'mode' => Settings::MODE_LOG,
			],
		]);

		$page = Settings::for_post_type('page');
		$this->assert_same(Settings::MODE_BLOCK_DELETE, $page['mode'], 'page mode saved');
		$this->assert_true($page['allow_empty_trash_days'], 'page allow_empty kept for block_delete');

		$post = Settings::for_post_type('post');
		$this->assert_same(Settings::MODE_LOG, $post['mode'], 'post mode saved');
		$this->assert_false($post['allow_empty_trash_days'], 'allow_empty cleared outside block_delete');

		$missing = Settings::for_post_type('news');
		$this->assert_same(Settings::MODE_OFF, $missing['mode'], 'unknown CPT defaults to off');

		$all = Settings::all();
		$this->assert_false(isset($all['unknown_type_xyz']), 'invalid post type not stored');

		Settings::save([
			'page' => ['mode' => Settings::MODE_OFF],
		]);
		$all = Settings::all();
		$this->assert_false(isset($all['page']), 'off mode is not stored');
	}
}
