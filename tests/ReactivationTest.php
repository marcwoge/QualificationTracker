<?php
/**
 * Unit tests for the pure reactivation helpers (F5.2):
 * qt_reactivation_wake_date(), qt_reactivation_vorlauf() and
 * qt_reactivation_is_dormant().
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class ReactivationTest extends TestCase {

	public function testWakeDateSubtractsLeadTime() {
		self::assertSame( '2028-04-01', qt_reactivation_wake_date( '2028-06-30', 90 ) );
		self::assertSame( '2028-06-30', qt_reactivation_wake_date( '2028-06-30', 0 ) );
	}

	public function testWakeDateNullWithoutTarget() {
		self::assertNull( qt_reactivation_wake_date( null, 90 ) );
		self::assertNull( qt_reactivation_wake_date( '', 90 ) );
	}

	public function testVorlaufPrefersMeasureValue() {
		self::assertSame( 120, qt_reactivation_vorlauf(
			array( 'vorlaufzeit_tage' => 120 ), array( 90, 30 ) ) );
	}

	public function testVorlaufFallsBackToLargestStage() {
		self::assertSame( 90, qt_reactivation_vorlauf(
			array( 'vorlaufzeit_tage' => 0 ), array( 90, 30, 0, -30 ) ) );
		self::assertSame( 90, qt_reactivation_vorlauf( array(), array() ) );
	}

	public function testDormantWhenWakeInFuture() {
		self::assertTrue( qt_reactivation_is_dormant( '2028-04-01', '2026-08-13' ) );
	}

	public function testNotDormantWhenWakeReachedOrPast() {
		self::assertFalse( qt_reactivation_is_dormant( '2026-08-13', '2026-08-13' ) );
		self::assertFalse( qt_reactivation_is_dormant( '2026-08-12', '2026-08-13' ) );
	}

	public function testNotDormantWithoutWakeDate() {
		self::assertFalse( qt_reactivation_is_dormant( null, '2026-08-13' ) );
	}
}
