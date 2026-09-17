<?php

namespace Delete_Guard;

defined('ABSPATH') || exit;

final class Settings {
	public const OPTION_KEY = 'delete_guard_settings';

	public const MODE_OFF = 'off';
	public const MODE_LOG = 'log';
	public const MODE_BLOCK_TRASH = 'block_trash';
	public const MODE_BLOCK_DELETE = 'block_delete';

	/**
	 * @return array<string, array{mode: string, allow_empty_trash_days: bool}>
	 */
	public static function all(): array {
		$stored = get_option(self::OPTION_KEY, []);
		if (!is_array($stored)) {
			return [];
		}

		$result = [];
		foreach ($stored as $post_type => $row) {
			if (!is_string($post_type) || $post_type === '' || !is_array($row)) {
				continue;
			}
			$result[$post_type] = self::normalize_row($row);
		}

		return $result;
	}

	/**
	 * @return array{mode: string, allow_empty_trash_days: bool}
	 */
	public static function for_post_type(string $post_type): array {
		$all = self::all();
		if (isset($all[$post_type])) {
			return $all[$post_type];
		}

		// Appearance → Menus stores items as nav_menu_item (no show_ui). Inherit
		// Navigation Menus policy so one setting covers both menu UIs when unset.
		if ($post_type === 'nav_menu_item' && isset($all['wp_navigation'])) {
			return $all['wp_navigation'];
		}

		return self::default_row();
	}

	/**
	 * @param array<string, array{mode?: string, allow_empty_trash_days?: bool|int|string}> $input
	 */
	public static function save(array $input): void {
		$clean = [];
		foreach ($input as $post_type => $row) {
			if (!is_string($post_type) || $post_type === '' || !post_type_exists($post_type)) {
				continue;
			}
			if (!is_array($row)) {
				continue;
			}
			$normalized = self::normalize_row($row);
			if ($normalized['mode'] === self::MODE_OFF) {
				continue;
			}
			$clean[$post_type] = $normalized;
		}

		update_option(self::OPTION_KEY, $clean, false);
	}

	/**
	 * @return list<string>
	 */
	public static function modes(): array {
		return [
			self::MODE_OFF,
			self::MODE_LOG,
			self::MODE_BLOCK_TRASH,
			self::MODE_BLOCK_DELETE,
		];
	}

	/**
	 * @return array{mode: string, allow_empty_trash_days: bool}
	 */
	public static function default_row(): array {
		return [
			'mode' => self::MODE_OFF,
			'allow_empty_trash_days' => false,
		];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array{mode: string, allow_empty_trash_days: bool}
	 */
	private static function normalize_row(array $row): array {
		$mode = isset($row['mode']) && is_string($row['mode']) ? $row['mode'] : self::MODE_OFF;
		if (!in_array($mode, self::modes(), true)) {
			$mode = self::MODE_OFF;
		}

		$allow_empty = !empty($row['allow_empty_trash_days']);
		if ($mode !== self::MODE_BLOCK_DELETE) {
			$allow_empty = false;
		}

		return [
			'mode' => $mode,
			'allow_empty_trash_days' => $allow_empty,
		];
	}
}
