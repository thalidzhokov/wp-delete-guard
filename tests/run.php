<?php
/**
 * CLI entry: php tests/run.php
 *
 * From Docker:
 *   docker compose exec -T php php /var/www/html/wp-content/plugins/delete-guard/tests/run.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only.\n");
	exit(1);
}

define('DELETE_GUARD_RUNNING_TESTS', true);

$plugin_root = dirname(__DIR__);
$wp_load = dirname($plugin_root, 3) . '/wp-load.php';

if (!is_readable($wp_load)) {
	fwrite(STDERR, "Cannot find wp-load.php at {$wp_load}\n");
	exit(1);
}

require_once $wp_load;
require_once $plugin_root . '/tests/class-test-case.php';
require_once $plugin_root . '/tests/class-test-runner.php';
require_once $plugin_root . '/tests/Functional/class-settings-test.php';
require_once $plugin_root . '/tests/Functional/class-policy-test.php';
require_once $plugin_root . '/tests/Functional/class-guard-flow-test.php';
require_once $plugin_root . '/tests/Functional/class-logging-test.php';

use Delete_Guard\Tests\Test_Runner;
use Delete_Guard\Tests\Functional\Settings_Test;
use Delete_Guard\Tests\Functional\Policy_Test;
use Delete_Guard\Tests\Functional\Guard_Flow_Test;
use Delete_Guard\Tests\Functional\Logging_Test;

$runner = new Test_Runner();
$runner->add(new Settings_Test());
$runner->add(new Policy_Test());
$runner->add(new Guard_Flow_Test());
$runner->add(new Logging_Test());

exit($runner->run());
