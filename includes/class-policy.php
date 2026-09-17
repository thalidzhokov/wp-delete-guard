<?php

namespace Delete_Guard;

defined('ABSPATH') || exit;

final class Policy {
	public static function is_privileged_user(?int $user_id = null): bool {
		if ($user_id === null) {
			$user_id = get_current_user_id();
		}
		$user_id = (int) $user_id;
		if ($user_id <= 0) {
			return false;
		}

		if (is_multisite() && is_super_admin($user_id)) {
			return true;
		}

		$user = get_userdata($user_id);
		if (!$user) {
			return false;
		}

		return in_array('administrator', (array) $user->roles, true);
	}

	/**
	 * @return array{enabled: bool, allow: bool, should_log: bool}
	 */
	public static function decide(string $action, string $post_type): array {
		$settings = Settings::for_post_type($post_type);
		$mode = $settings['mode'];

		if ($mode === Settings::MODE_OFF) {
			return [
				'enabled' => false,
				'allow' => true,
				'should_log' => false,
			];
		}

		$privileged = self::is_privileged_user();
		$allow = true;

		if ($action === Logger::ACTION_TRASH) {
			if ($mode === Settings::MODE_BLOCK_TRASH && !$privileged) {
				$allow = false;
			}
		} elseif ($action === Logger::ACTION_DELETE) {
			if ($mode === Settings::MODE_BLOCK_TRASH && !$privileged) {
				$allow = false;
			} elseif ($mode === Settings::MODE_BLOCK_DELETE) {
				if ($privileged) {
					$allow = true;
				} elseif ($settings['allow_empty_trash_days'] && wp_doing_cron()) {
					$allow = true;
				} else {
					$allow = false;
				}
			}
		} elseif ($action === Logger::ACTION_RESTORE) {
			$allow = true;
		}

		return [
			'enabled' => true,
			'allow' => $allow,
			'should_log' => true,
		];
	}
}
