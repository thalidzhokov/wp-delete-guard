<?php
/**
 * Plugin Name: Delete Guard
 * Plugin URI: https://github.com/thalidzhokov/wp-delete-guard
 * Description: Политики удаления постов по типу: логирование, запрет корзины и полного удаления.
 * Version: 1.0.0
 * Author: Albert Thalidzhokov
 * Author URI: https://github.com/thalidzhokov/wp-delete-guard
 * Text Domain: delete-guard
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

define('DELETE_GUARD_VERSION', '1.0.0');
define('DELETE_GUARD_FILE', __FILE__);
define('DELETE_GUARD_DIR', plugin_dir_path(__FILE__));
define('DELETE_GUARD_URL', plugin_dir_url(__FILE__));

require_once DELETE_GUARD_DIR . 'includes/class-settings.php';
require_once DELETE_GUARD_DIR . 'includes/class-logger.php';
require_once DELETE_GUARD_DIR . 'includes/class-policy.php';
require_once DELETE_GUARD_DIR . 'includes/class-guard.php';
require_once DELETE_GUARD_DIR . 'includes/class-admin.php';
require_once DELETE_GUARD_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, ['Delete_Guard\\Plugin', 'activate']);

add_action('plugins_loaded', static function (): void {
	Delete_Guard\Plugin::instance()->init();
});
