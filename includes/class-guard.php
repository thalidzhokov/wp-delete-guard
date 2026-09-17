<?php

namespace Delete_Guard;

defined('ABSPATH') || exit;

final class Guard {
	/** @var array<string, true> */
	private array $logged_keys = [];

	public function init(): void {
		add_filter('pre_trash_post', [$this, 'filter_pre_trash_post'], 5, 3);
		add_filter('pre_untrash_post', [$this, 'filter_pre_untrash_post'], 5, 3);
		add_filter('pre_delete_post', [$this, 'filter_pre_delete_post'], 5, 3);
		add_filter('pre_delete_attachment', [$this, 'filter_pre_delete_attachment'], 5, 3);

		add_action('trashed_post', [$this, 'on_trashed_post'], 10, 2);
		add_action('untrashed_post', [$this, 'on_untrashed_post'], 10, 2);
		add_action('deleted_post', [$this, 'on_deleted_post'], 10, 2);
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
