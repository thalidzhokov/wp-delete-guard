<?php

namespace Delete_Guard;

defined('ABSPATH') || exit;

final class Guard {
	/** @var array<string, true> */
	private array $logged_keys = [];

	/** @var string|null trash|delete — set when an admin UI delete was blocked. */
	private ?string $admin_block_action = null;

	public function init(): void {
		add_filter('pre_trash_post', [$this, 'filter_pre_trash_post'], 5, 3);
		add_filter('pre_untrash_post', [$this, 'filter_pre_untrash_post'], 5, 3);
		add_filter('pre_delete_post', [$this, 'filter_pre_delete_post'], 5, 3);
		add_filter('pre_delete_attachment', [$this, 'filter_pre_delete_attachment'], 5, 3);

		add_action('trashed_post', [$this, 'on_trashed_post'], 10, 2);
		add_action('untrashed_post', [$this, 'on_untrashed_post'], 10, 2);
		add_action('deleted_post', [$this, 'on_deleted_post'], 10, 2);

		add_filter('wp_die_handler', [$this, 'filter_wp_die_handler']);
	}

	/**
	 * @param bool|null $check
	 * @return bool|null
	 */
	public function filter_pre_trash_post($check, $post, $previous_status) {
		if ($check !== null || !($post instanceof \WP_Post)) {
			return $check;
		}

		$decision = Policy::decide(Logger::ACTION_TRASH, $post->post_type);
		if (!$decision['enabled']) {
			return $check;
		}

		if (!$decision['allow']) {
			$this->log_once($post, Logger::ACTION_TRASH, Logger::STATUS_DENIED);
			$this->mark_admin_block(Logger::ACTION_TRASH);
			return false;
		}

		// Log before trash so nav_menu_item still has terms/meta for the title snapshot.
		$this->log_once($post, Logger::ACTION_TRASH, Logger::STATUS_ALLOWED);

		return $check;
	}

	/**
	 * @param bool|null $check
	 * @return bool|null
	 */
	public function filter_pre_untrash_post($check, $post, $previous_status) {
		if ($check !== null || !($post instanceof \WP_Post)) {
			return $check;
		}

		$decision = Policy::decide(Logger::ACTION_RESTORE, $post->post_type);
		if (!$decision['enabled']) {
			return $check;
		}

		return $check;
	}

	/**
	 * @param \WP_Post|false|null $check
	 * @return \WP_Post|false|null
	 */
	public function filter_pre_delete_post($check, $post, $force_delete) {
		if ($check !== null || !($post instanceof \WP_Post)) {
			return $check;
		}

		return $this->maybe_block_delete($post);
	}

	/**
	 * @param \WP_Post|false|null $check
	 * @return \WP_Post|false|null
	 */
	public function filter_pre_delete_attachment($check, $post, $force_delete) {
		if ($check !== null || !($post instanceof \WP_Post)) {
			return $check;
		}

		return $this->maybe_block_delete($post);
	}

	/**
	 * @return \WP_Post|false|null
	 */
	private function maybe_block_delete(\WP_Post $post) {
		$decision = Policy::decide(Logger::ACTION_DELETE, $post->post_type);
		if (!$decision['enabled']) {
			return null;
		}

		if (!$decision['allow']) {
			$this->log_once($post, Logger::ACTION_DELETE, Logger::STATUS_DENIED);
			if (wp_doing_cron() && $post->post_status === 'trash') {
				update_post_meta($post->ID, '_wp_trash_meta_time', time());
			}
			$this->mark_admin_block(Logger::ACTION_DELETE);
			return false;
		}

		// Log before delete so nav_menu_item still has terms/meta for the title snapshot.
		$this->log_once($post, Logger::ACTION_DELETE, Logger::STATUS_ALLOWED);

		return null;
	}

	public function on_trashed_post($post_id, $previous_status = null): void {
		$post = get_post((int) $post_id);
		if (!($post instanceof \WP_Post)) {
			return;
		}

		$decision = Policy::decide(Logger::ACTION_TRASH, $post->post_type);
		if (!$decision['should_log']) {
			return;
		}

		$this->log_once($post, Logger::ACTION_TRASH, Logger::STATUS_ALLOWED);
	}

	public function on_untrashed_post($post_id, $previous_status = null): void {
		$post = get_post((int) $post_id);
		if (!($post instanceof \WP_Post)) {
			return;
		}

		$decision = Policy::decide(Logger::ACTION_RESTORE, $post->post_type);
		if (!$decision['should_log']) {
			return;
		}

		$this->log_once($post, Logger::ACTION_RESTORE, Logger::STATUS_ALLOWED);
	}

	/**
	 * @param int $post_id
	 * @param \WP_Post|null $post
	 */
	public function on_deleted_post($post_id, $post = null): void {
		if (!($post instanceof \WP_Post)) {
			return;
		}

		if ($post->post_type === 'revision') {
			return;
		}

		$decision = Policy::decide(Logger::ACTION_DELETE, $post->post_type);
		if (!$decision['should_log']) {
			return;
		}

		$this->log_once($post, Logger::ACTION_DELETE, Logger::STATUS_ALLOWED);
	}

	/**
	 * @param callable $handler
	 * @return callable
	 */
	public function filter_wp_die_handler($handler) {
		if ($this->admin_block_action === null) {
			return $handler;
		}

		return [$this, 'redirect_admin_block_die'];
	}

	/**
	 * Replace wp_die() after a blocked trash/delete with a redirect back to the list.
	 *
	 * @param string|WP_Error $message
	 * @param string|int      $title
	 * @param string|array|int $args
	 */
	public function redirect_admin_block_die($message = '', $title = '', $args = []): void {
		$action = $this->admin_block_action ?? Logger::ACTION_TRASH;
		$this->admin_block_action = null;

		$sendback = wp_get_referer();
		if (!$sendback) {
			$sendback = admin_url('edit.php');
		}

		$sendback = remove_query_arg(
			['trashed', 'untrashed', 'deleted', 'locked', 'ids', 'delete_guard_blocked'],
			$sendback
		);
		$sendback = add_query_arg('delete_guard_blocked', $action, $sendback);

		wp_safe_redirect($sendback);
		exit;
	}

	private function mark_admin_block(string $action): void {
		if (!$this->should_redirect_admin_block()) {
			return;
		}

		$this->admin_block_action = $action;
	}

	private function should_redirect_admin_block(): bool {
		if (!is_admin()) {
			return false;
		}
		if (wp_doing_ajax() || wp_doing_cron()) {
			return false;
		}
		if (defined('REST_REQUEST') && REST_REQUEST) {
			return false;
		}
		if (defined('WP_CLI') && \WP_CLI) {
			return false;
		}

		return true;
	}

	private function log_once(\WP_Post $post, string $action, string $status): void {
		$key = $status . ':' . $action . ':' . (int) $post->ID;
		if (isset($this->logged_keys[$key])) {
			return;
		}
		$this->logged_keys[$key] = true;

		Logger::write(array_merge(
			Logger::snapshot_from_post($post),
			[
				'status' => $status,
				'action' => $action,
			]
		));
	}

	public function clear_logged_keys(): void {
		$this->logged_keys = [];
	}
}
