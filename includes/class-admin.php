<?php

namespace Delete_Guard;

defined('ABSPATH') || exit;

final class Admin {
	private const MENU_SLUG = 'delete-guard';
	private const NONCE_SETTINGS = 'delete_guard_settings';
	private const NONCE_PURGE = 'delete_guard_purge';

	public function init(): void {
		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_init', [$this, 'handle_post_actions']);
		add_filter(
			'plugin_action_links_' . plugin_basename(DELETE_GUARD_FILE),
			[$this, 'plugin_action_links']
		);
	}

	/**
	 * @param array<string, string> $links
	 * @return array<string, string>
	 */
	public function plugin_action_links(array $links): array {
		if (!current_user_can('manage_options')) {
			return $links;
		}

		$url = admin_url('options-general.php?page=' . self::MENU_SLUG);
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url($url),
			esc_html__('Settings', 'delete-guard')
		);

		return array_merge(['settings' => $settings], $links);
	}

	public function register_menu(): void {
		add_options_page(
			__('Delete Guard', 'delete-guard'),
			__('Delete Guard', 'delete-guard'),
			'manage_options',
			self::MENU_SLUG,
			[$this, 'render_page']
		);
	}

	public function handle_post_actions(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		if (!isset($_POST['delete_guard_action'])) {
			return;
		}

		$action = sanitize_key((string) wp_unslash($_POST['delete_guard_action']));

		if ($action === 'save_settings') {
			check_admin_referer(self::NONCE_SETTINGS);
			$rows = isset($_POST['settings']) && is_array($_POST['settings'])
				? wp_unslash($_POST['settings'])
				: [];
			$parsed = [];
			foreach ($rows as $post_type => $row) {
				if (!is_string($post_type) || !is_array($row)) {
					continue;
				}
				$parsed[sanitize_key($post_type)] = [
					'mode' => isset($row['mode']) ? sanitize_key((string) $row['mode']) : Settings::MODE_OFF,
					'allow_empty_trash_days' => !empty($row['allow_empty_trash_days']),
				];
			}
			Settings::save($parsed);
			wp_safe_redirect(add_query_arg([
				'page' => self::MENU_SLUG,
				'tab' => 'settings',
				'updated' => '1',
			], admin_url('options-general.php')));
			exit;
		}

		if ($action === 'purge_logs') {
			check_admin_referer(self::NONCE_PURGE);
			$deleted = Logger::purge_older_than_days(30);
			wp_safe_redirect(add_query_arg([
				'page' => self::MENU_SLUG,
				'tab' => 'log',
				'purged' => (string) $deleted,
			], admin_url('options-general.php')));
			exit;
		}
	}

	public function render_page(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'settings';
		if (!in_array($tab, ['settings', 'log'], true)) {
			$tab = 'settings';
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Delete Guard', 'delete-guard') . '</h1>';

		if (isset($_GET['updated'])) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__('Settings saved.', 'delete-guard')
				. '</p></div>';
		}
		if (isset($_GET['purged'])) {
			$count = (int) $_GET['purged'];
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: %d: number of deleted log rows */
					_n('%d log entry deleted.', '%d log entries deleted.', $count, 'delete-guard'),
					$count
				)
			);
			echo '</p></div>';
		}

		$base = admin_url('options-general.php?page=' . self::MENU_SLUG);
		echo '<nav class="nav-tab-wrapper" style="margin-bottom:16px">';
		printf(
			'<a href="%s" class="nav-tab %s">%s</a>',
			esc_url(add_query_arg('tab', 'settings', $base)),
			$tab === 'settings' ? 'nav-tab-active' : '',
			esc_html__('Settings', 'delete-guard')
		);
		printf(
			'<a href="%s" class="nav-tab %s">%s</a>',
			esc_url(add_query_arg('tab', 'log', $base)),
			$tab === 'log' ? 'nav-tab-active' : '',
			esc_html__('Log', 'delete-guard')
		);
		echo '</nav>';

		if ($tab === 'log') {
			$this->render_log_tab();
		} else {
			$this->render_settings_tab();
		}

		echo '</div>';
	}

	private function render_settings_tab(): void {
		$stored = Settings::all();
		$post_types = get_post_types(['show_ui' => true], 'objects');
		$attachment = get_post_type_object('attachment');
		if ($attachment && !isset($post_types['attachment'])) {
			$post_types['attachment'] = $attachment;
		}

		uasort($post_types, static function ($a, $b): int {
			return strcasecmp($a->labels->name ?? $a->name, $b->labels->name ?? $b->name);
		});

		echo '<p>' . esc_html__(
			'New post types are off by default. Administrators and super admins can delete even when a mode blocks it; those actions are still logged.',
			'delete-guard'
		) . '</p>';
		echo '<p>' . wp_kses(
			sprintf(
				/* translators: %s: EMPTY_TRASH_DAYS value */
				__('Current <code>EMPTY_TRASH_DAYS</code>: <strong>%s</strong>.', 'delete-guard'),
				esc_html((string) EMPTY_TRASH_DAYS)
			),
			[
				'code' => [],
				'strong' => [],
			]
		) . '</p>';

		echo '<form method="post">';
		wp_nonce_field(self::NONCE_SETTINGS);
		echo '<input type="hidden" name="delete_guard_action" value="save_settings" />';

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Post type', 'delete-guard') . '</th>';
		echo '<th>' . esc_html__('Mode', 'delete-guard') . '</th>';
		echo '<th>' . esc_html__('Allow EMPTY_TRASH_DAYS cleanup', 'delete-guard') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($post_types as $post_type => $object) {
			$row = $stored[$post_type] ?? Settings::default_row();
			$mode = $row['mode'];
			$allow_empty = !empty($row['allow_empty_trash_days']);
			$empty_disabled = $mode !== Settings::MODE_BLOCK_DELETE;

			echo '<tr>';
			echo '<td><strong>' . esc_html($object->labels->name ?? $post_type) . '</strong>';
			echo '<br><code>' . esc_html($post_type) . '</code></td>';

			echo '<td>';
			printf('<select name="settings[%s][mode]" class="delete-guard-mode">', esc_attr($post_type));
			foreach ($this->mode_labels() as $value => $label) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr($value),
					selected($mode, $value, false),
					esc_html($label)
				);
			}
			echo '</select></td>';

			echo '<td>';
			printf(
				'<label><input type="checkbox" name="settings[%s][allow_empty_trash_days]" value="1"%s%s /> %s</label>',
				esc_attr($post_type),
				checked($allow_empty, true, false),
				$empty_disabled ? ' disabled' : '',
				esc_html__('Via cron', 'delete-guard')
			);
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		submit_button(__('Save settings', 'delete-guard'));
		echo '</form>';

		echo '<script>
		(function () {
			function syncRow(select) {
				var row = select.closest("tr");
				if (!row) return;
				var cb = row.querySelector("input[type=checkbox]");
				if (!cb) return;
				var enable = select.value === "block_delete";
				cb.disabled = !enable;
				if (!enable) cb.checked = false;
			}
			document.querySelectorAll(".delete-guard-mode").forEach(function (el) {
				el.addEventListener("change", function () { syncRow(el); });
				syncRow(el);
			});
		})();
		</script>';
	}

	private function render_log_tab(): void {
		$status = isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : '';
		$action = isset($_GET['log_action']) ? sanitize_key((string) wp_unslash($_GET['log_action'])) : '';
		$post_type = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '';
		$paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

		$result = Logger::query([
			'status' => in_array($status, [Logger::STATUS_ALLOWED, Logger::STATUS_DENIED], true) ? $status : '',
			'action' => in_array($action, [Logger::ACTION_TRASH, Logger::ACTION_DELETE, Logger::ACTION_RESTORE], true) ? $action : '',
			'post_type' => $post_type,
			'paged' => $paged,
			'per_page' => 50,
		]);

		$base = add_query_arg(
			array_filter([
				'page' => self::MENU_SLUG,
				'tab' => 'log',
				'status' => $status,
				'log_action' => $action,
				'post_type' => $post_type,
			]),
			admin_url('options-general.php')
		);

		echo '<form method="get" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">';
		echo '<input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG) . '" />';
		echo '<input type="hidden" name="tab" value="log" />';

		echo '<p><label>' . esc_html__('Status', 'delete-guard') . '<br><select name="status">';
		echo '<option value="">' . esc_html__('All', 'delete-guard') . '</option>';
		foreach ($this->status_labels() as $value => $label) {
			printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($status, $value, false), esc_html($label));
		}
		echo '</select></label></p>';

		echo '<p><label>' . esc_html__('Action', 'delete-guard') . '<br><select name="log_action">';
		echo '<option value="">' . esc_html__('All', 'delete-guard') . '</option>';
		foreach ($this->action_labels() as $value => $label) {
			printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($action, $value, false), esc_html($label));
		}
		echo '</select></label></p>';

		echo '<p><label>' . esc_html__('Type', 'delete-guard') . '<br><input type="text" name="post_type" value="'
			. esc_attr($post_type) . '" placeholder="page" /></label></p>';
		echo '<p><button class="button">' . esc_html__('Filter', 'delete-guard') . '</button></p>';
		echo '</form>';

		$confirm = esc_js(__('Delete log entries older than 30 days?', 'delete-guard'));
		echo '<form method="post" style="margin-bottom:16px" onsubmit="return confirm(\'' . $confirm . '\');">';
		wp_nonce_field(self::NONCE_PURGE);
		echo '<input type="hidden" name="delete_guard_action" value="purge_logs" />';
		submit_button(__('Delete logs older than 30 days', 'delete-guard'), 'secondary', 'submit', false);
		echo '</form>';

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Date', 'delete-guard') . '</th>';
		echo '<th>' . esc_html__('Status', 'delete-guard') . '</th>';
		echo '<th>' . esc_html__('Action', 'delete-guard') . '</th>';
		echo '<th>' . esc_html__('User', 'delete-guard') . '</th>';
		echo '<th>' . esc_html__('Source', 'delete-guard') . '</th>';
		echo '<th>' . esc_html__('Post', 'delete-guard') . '</th>';
		echo '</tr></thead><tbody>';

		if (!$result['items']) {
			echo '<tr><td colspan="6">' . esc_html__('No entries.', 'delete-guard') . '</td></tr>';
		}

		foreach ($result['items'] as $item) {
			$user_label = $this->format_user((int) $item->user_id);
			$post_label = $this->format_post_link(
				(int) $item->post_id,
				(string) $item->post_type,
				(string) $item->post_title
			);

			echo '<tr>';
			echo '<td>' . esc_html((string) $item->created_at) . '</td>';
			echo '<td>' . esc_html($this->status_labels()[$item->status] ?? $item->status) . '</td>';
			echo '<td>' . esc_html($this->action_labels()[$item->action] ?? $item->action) . '</td>';
			echo '<td>' . esc_html($user_label) . '</td>';
			echo '<td><code>' . esc_html((string) $item->source) . '</code></td>';
			echo '<td>' . $post_label . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$total_pages = (int) ceil($result['total'] / 50);
		if ($total_pages > 1) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo paginate_links([
				'base' => add_query_arg('paged', '%#%', $base),
				'format' => '',
				'current' => $paged,
				'total' => $total_pages,
			]);
			echo '</div></div>';
		}
	}

	/**
	 * @return array<string, string>
	 */
	private function mode_labels(): array {
		return [
			Settings::MODE_OFF => __('Off', 'delete-guard'),
			Settings::MODE_LOG => __('Log only', 'delete-guard'),
			Settings::MODE_BLOCK_TRASH => __('Block trash', 'delete-guard'),
			Settings::MODE_BLOCK_DELETE => __('Block permanent delete', 'delete-guard'),
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function status_labels(): array {
		return [
			Logger::STATUS_ALLOWED => __('Allowed', 'delete-guard'),
			Logger::STATUS_DENIED => __('Denied', 'delete-guard'),
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function action_labels(): array {
		return [
			Logger::ACTION_TRASH => __('Move to trash', 'delete-guard'),
			Logger::ACTION_DELETE => __('Permanent delete', 'delete-guard'),
			Logger::ACTION_RESTORE => __('Restore', 'delete-guard'),
		];
	}

	private function format_user(int $user_id): string {
		if ($user_id <= 0) {
			return __('System', 'delete-guard');
		}
		$user = get_userdata($user_id);
		if (!$user) {
			return '#' . $user_id;
		}

		return $user->user_login . ' (#' . $user_id . ')';
	}

	private function format_post_link(int $post_id, string $post_type, string $title): string {
		$label = $title !== '' ? $title : __('(no title)', 'delete-guard');
		$meta = sprintf('#%d [%s]', $post_id, $post_type !== '' ? $post_type : '?');

		$existing = $post_id > 0 ? get_post($post_id) : null;
		if ($existing instanceof \WP_Post) {
			$link = get_edit_post_link($post_id, 'raw');
			if ($link) {
				return sprintf(
					'<a href="%s">%s</a><br><code>%s</code>',
					esc_url($link),
					esc_html($label),
					esc_html($meta)
				);
			}
		}

		return esc_html($label) . '<br><code>' . esc_html($meta) . '</code>';
	}
}
