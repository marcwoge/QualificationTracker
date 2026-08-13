<?php
/**
 * Unit tests for optional-plugin detection (F2.4).
 *
 * Verifies graceful degradation: with no MantisBT plugin API available, the
 * helpers return false instead of erroring.
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class IntegrationDetectTest extends TestCase {

	public function testDegradesGracefullyWithoutPluginApi() {
		# plugin_is_installed() is not defined in the unit-test context.
		self::assertFalse( function_exists( 'plugin_is_installed' ) );
		self::assertFalse( qt_integration_available( 'Anything' ) );
	}

	public function testIssueRecurrenceAndReveilleDetectors() {
		self::assertFalse( qt_integration_issuerecurrence() );
		self::assertFalse( qt_integration_reveille() );
	}
}
