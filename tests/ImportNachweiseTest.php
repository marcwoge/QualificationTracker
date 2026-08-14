<?php
/**
 * Unit tests for the pure historical-proof import helpers (F6.2):
 * qt_import_nachweise_map_row(), qt_import_nachweise_target_status() and
 * qt_import_nachweise_zyklus().
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class ImportNachweiseTest extends TestCase {

	private const TODAY = '2026-08-14';

	public function testMapRowTrimsFields() {
		$t = qt_import_nachweise_map_row( array(
			'personalnummer' => ' 900001 ', 'massnahme' => ' UW-JAHR ',
			'durchgefuehrt_am' => '2025-03-01', 'gueltig_bis' => '2026-12-31',
			'durchfuehrender' => 'Frau X', 'zyklus' => '2025' ) );
		self::assertSame( '900001', $t['personalnummer'] );
		self::assertSame( 'UW-JAHR', $t['massnahme'] );
		self::assertSame( '2025', $t['zyklus'] );
	}

	public function testTargetStatusValidWhenNoEndDate() {
		self::assertSame( 'gueltig', qt_import_nachweise_target_status( '', self::TODAY ) );
	}

	public function testTargetStatusValidWhenInFuture() {
		self::assertSame( 'gueltig', qt_import_nachweise_target_status( '2027-01-01', self::TODAY ) );
		self::assertSame( 'gueltig', qt_import_nachweise_target_status( self::TODAY, self::TODAY ) );
	}

	public function testTargetStatusExpiredWhenPast() {
		self::assertSame( 'abgelaufen', qt_import_nachweise_target_status( '2026-08-13', self::TODAY ) );
		self::assertSame( 'abgelaufen', qt_import_nachweise_target_status( '2020-01-01', self::TODAY ) );
	}

	public function testZyklusPrefersExplicitColumn() {
		self::assertSame( '2024', qt_import_nachweise_zyklus( array(
			'zyklus' => '2024', 'gueltig_bis' => '2026-12-31', 'durchgefuehrt_am' => '2025-01-01' ) ) );
	}

	public function testZyklusFallsBackToGueltigBisYear() {
		self::assertSame( '2026', qt_import_nachweise_zyklus( array(
			'zyklus' => '', 'gueltig_bis' => '2026-12-31', 'durchgefuehrt_am' => '2025-01-01' ) ) );
	}

	public function testZyklusFallsBackToPerformedYear() {
		self::assertSame( '2025', qt_import_nachweise_zyklus( array(
			'zyklus' => '', 'gueltig_bis' => '', 'durchgefuehrt_am' => '2025-01-01' ) ) );
	}
}
