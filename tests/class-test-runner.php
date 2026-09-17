<?php

namespace Delete_Guard\Tests;

defined( 'ABSPATH' ) || exit;

final class Test_Runner {
	/** @var list<Test_Case> */
	private array $cases = [];

	public function add( Test_Case $case ): void {
		$this->cases[] = $case;
	}

	public function run(): int {
		$passed = 0;
		$failed = 0;

		echo esc_html( "Delete Guard functional tests\n" );
		echo esc_html( str_repeat( '=', 40 ) . "\n" );

		if ( ! class_exists( \Delete_Guard\Plugin::class ) ) {
			echo esc_html( "FAIL: plugin classes not loaded. Is delete-guard active?\n" );
			return 1;
		}

		foreach ( $this->cases as $case ) {
			$case->set_up();
			try {
				$case->run();
			} catch ( \Throwable $e ) {
				$case->record_exception( $e );
			}
			$case->tear_down();

			$failures = $case->failures();
			if ( $failures ) {
				$failed++;
				echo esc_html( '[FAIL] ' . $case->name() . "\n" );
				foreach ( $failures as $message ) {
					echo esc_html( '  - ' . $message . "\n" );
				}
			} else {
				$passed++;
				echo esc_html( '[OK]   ' . $case->name() . "\n" );
			}
		}

		echo esc_html( str_repeat( '=', 40 ) . "\n" );
		echo esc_html( "Passed: {$passed}, Failed: {$failed}\n" );

		return $failed > 0 ? 1 : 0;
	}
}
