<?php

namespace Delete_Guard\Tests;

defined('ABSPATH') || exit;

final class Test_Runner {
	/** @var list<Test_Case> */
	private array $cases = [];

	public function add(Test_Case $case): void {
		$this->cases[] = $case;
	}

	public function run(): int {
		$passed = 0;
		$failed = 0;

		echo "Delete Guard functional tests\n";
		echo str_repeat('=', 40) . "\n";

		if (!class_exists(\Delete_Guard\Plugin::class)) {
			echo "FAIL: plugin classes not loaded. Is delete-guard active?\n";
			return 1;
		}

		foreach ($this->cases as $case) {
			$case->set_up();
			try {
				$case->run();
			} catch (\Throwable $e) {
				$case->record_exception($e);
			}
			$case->tear_down();

			$failures = $case->failures();
			if ($failures) {
				$failed++;
				echo '[FAIL] ' . $case->name() . "\n";
				foreach ($failures as $message) {
					echo '  - ' . $message . "\n";
				}
			} else {
				$passed++;
				echo '[OK]   ' . $case->name() . "\n";
			}
		}

		echo str_repeat('=', 40) . "\n";
		echo "Passed: {$passed}, Failed: {$failed}\n";

		return $failed > 0 ? 1 : 0;
	}
}
