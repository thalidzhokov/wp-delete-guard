<?php
/**
 * CLI entry: php tests/run.php
 *
 * From Docker:
 *   docker compose exec -T php php /var/www/html/wp-content/plugins/delete-guard/tests/run.php
 *
 * @package Delete_Guard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	if ( PHP_SAPI !== 'cli' ) {
		exit;
	}

	// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI before WP bootstrap.
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- bootstrap locals.
	$delete_guard_plugin_root = dirname( __DIR__ );
	$delete_guard_wp_load     = dirname( $delete_guard_plugin_root, 3 ) . '/wp-load.php';

	if ( ! is_readable( $delete_guard_wp_load ) ) {
		fwrite( STDERR, "Cannot find wp-load.php at {$delete_guard_wp_load}\n" );
		exit( 1 );
	}

	require_once $delete_guard_wp_load;
	// phpcs:enable
}

define( 'DELETE_GUARD_RUNNING_TESTS', true );

$delete_guard_plugin_root = dirname( __DIR__ );

require_once $delete_guard_plugin_root . '/tests/class-test-case.php';
require_once $delete_guard_plugin_root . '/tests/class-test-runner.php';
require_once $delete_guard_plugin_root . '/tests/Functional/class-settings-test.php';
require_once $delete_guard_plugin_root . '/tests/Functional/class-policy-test.php';
require_once $delete_guard_plugin_root . '/tests/Functional/class-guard-flow-test.php';
require_once $delete_guard_plugin_root . '/tests/Functional/class-logging-test.php';

use Delete_Guard\Tests\Test_Runner;
use Delete_Guard\Tests\Functional\Settings_Test;
use Delete_Guard\Tests\Functional\Policy_Test;
use Delete_Guard\Tests\Functional\Guard_Flow_Test;
use Delete_Guard\Tests\Functional\Logging_Test;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- CLI entry local.
$delete_guard_runner = new Test_Runner();
$delete_guard_runner->add( new Settings_Test() );
$delete_guard_runner->add( new Policy_Test() );
$delete_guard_runner->add( new Guard_Flow_Test() );
$delete_guard_runner->add( new Logging_Test() );

$delete_guard_exit_code = (int) $delete_guard_runner->run();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- process exit status, not HTML output.
exit( $delete_guard_exit_code );
