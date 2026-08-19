<?php
/**
 * Unit tests for the pure Bordmittel-migration helpers (F8.6):
 * qt_migrate_typ_from_category(), qt_migrate_split_name(), qt_migrate_status()
 * and qt_migrate_zyklus().
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class MigrationTest extends TestCase {

	public function testCategoryMapsToMeasureType() {
		self::assertSame( 'UW', qt_migrate_typ_from_category( 'Unterweisung' ) );
		self::assertSame( 'QU', qt_migrate_typ_from_category( 'Qualifikation' ) );
		self::assertSame( 'BE', qt_migrate_typ_from_category( 'Beauftragung' ) );
		self::assertSame( 'VO', qt_migrate_typ_from_category( 'Vorsorge' ) );
	}

	public function testUnknownOrHousekeepingCategoryIsEmpty() {
		self::assertSame( '', qt_migrate_typ_from_category( 'Stammdaten' ) );
		self::assertSame( '', qt_migrate_typ_from_category( '' ) );
		self::assertSame( '', qt_migrate_typ_from_category( 'Sonstiges' ) );
	}

	public function testSplitNameCommaForm() {
		$t = qt_migrate_split_name( 'Mustermann, Erika' );
		self::assertSame( 'Mustermann', $t['nachname'] );
		self::assertSame( 'Erika', $t['vorname'] );
	}

	public function testSplitNameSpaceForm() {
		$t = qt_migrate_split_name( 'Erika Mustermann' );
		self::assertSame( 'Mustermann', $t['nachname'] );
		self::assertSame( 'Erika', $t['vorname'] );
	}

	public function testSplitNameMultipleGivenNames() {
		$t = qt_migrate_split_name( 'Anna Lena Meyer' );
		self::assertSame( 'Meyer', $t['nachname'] );
		self::assertSame( 'Anna Lena', $t['vorname'] );
	}

	public function testSplitNameSingleToken() {
		$t = qt_migrate_split_name( 'Müller' );
		self::assertSame( 'Müller', $t['nachname'] );
		self::assertSame( '', $t['vorname'] );
	}

	public function testSplitNameEmpty() {
		$t = qt_migrate_split_name( '   ' );
		self::assertSame( '', $t['nachname'] );
		self::assertSame( '', $t['vorname'] );
	}

	public function testStatusMapsKnownNumbers() {
		$t_map = qt_migrate_default_status_map();
		self::assertSame( 'offen', qt_migrate_status( 10, $t_map ) );
		self::assertSame( 'durchgefuehrt', qt_migrate_status( 40, $t_map ) );
		self::assertSame( 'durchgefuehrt', qt_migrate_status( 50, $t_map ) );
		self::assertSame( 'gueltig', qt_migrate_status( 80, $t_map ) );
		self::assertSame( 'gueltig', qt_migrate_status( 85, $t_map ) );
		self::assertSame( 'abgelaufen', qt_migrate_status( 90, $t_map ) );
		self::assertSame( 'entfallen', qt_migrate_status( 95, $t_map ) );
	}

	public function testStatusFallsBackToOffen() {
		self::assertSame( 'offen', qt_migrate_status( 999, qt_migrate_default_status_map() ) );
	}

	public function testZyklusPrefersSollYear() {
		self::assertSame( '2025', qt_migrate_zyklus( '2025-03-01', '2026-03-01' ) );
	}

	public function testZyklusFallsBackToGueltigYear() {
		self::assertSame( '2026', qt_migrate_zyklus( '', '2026-03-01' ) );
		self::assertSame( '2026', qt_migrate_zyklus( null, '2026-03-01' ) );
	}

	public function testZyklusEmptyWhenNoDates() {
		self::assertSame( '', qt_migrate_zyklus( '', '' ) );
		self::assertSame( '', qt_migrate_zyklus( 'not-a-date', '' ) );
	}
}
