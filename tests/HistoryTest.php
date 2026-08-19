<?php
/**
 * Unit tests for the pure change-history helper (F7.5): qt_historie_diff()
 * and the tracked-entity list.
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class HistoryTest extends TestCase {

	public function testDiffReportsOnlyChangedFields() {
		$t_old = array( 'name' => 'A', 'aktiv' => '1', 'beschreibung' => 'x' );
		$t_new = array( 'name' => 'B', 'aktiv' => '1', 'beschreibung' => 'y' );
		$t_changes = qt_historie_diff( $t_old, $t_new, array( 'name', 'aktiv', 'beschreibung' ) );
		self::assertCount( 2, $t_changes );
		$t_map = array();
		foreach( $t_changes as $c ) { $t_map[$c['feld']] = $c; }
		self::assertSame( 'A', $t_map['name']['alt'] );
		self::assertSame( 'B', $t_map['name']['neu'] );
		self::assertArrayHasKey( 'beschreibung', $t_map );
		self::assertArrayNotHasKey( 'aktiv', $t_map );
	}

	public function testDiffTreatsNullAndMissingAsEmptyString() {
		$t_old = array( 'gueltig_bis' => null );
		$t_new = array();
		# null vs missing -> both '' -> no change.
		self::assertSame( array(), qt_historie_diff( $t_old, $t_new, array( 'gueltig_bis' ) ) );
	}

	public function testDiffDetectsSettingAPreviouslyNullValue() {
		$t_old = array( 'gueltig_bis' => null );
		$t_new = array( 'gueltig_bis' => '2026-12-31' );
		$t_changes = qt_historie_diff( $t_old, $t_new, array( 'gueltig_bis' ) );
		self::assertCount( 1, $t_changes );
		self::assertSame( '', $t_changes[0]['alt'] );
		self::assertSame( '2026-12-31', $t_changes[0]['neu'] );
	}

	public function testDiffComparesNumbersAsStrings() {
		# DB rows arrive as strings; 5 and '5' must count as equal.
		$t_old = array( 'intervall_monate' => '12' );
		$t_new = array( 'intervall_monate' => 12 );
		self::assertSame( array(), qt_historie_diff( $t_old, $t_new, array( 'intervall_monate' ) ) );
	}

	public function testDiffIgnoresFieldsNotInList() {
		$t_old = array( 'name' => 'A', 'date_modified' => '1' );
		$t_new = array( 'name' => 'A', 'date_modified' => '999' );
		self::assertSame( array(), qt_historie_diff( $t_old, $t_new, array( 'name' ) ) );
	}

	public function testEntitiesAreTheTrackedMasterData() {
		$t_entities = qt_historie_entities();
		self::assertContains( 'massnahme', $t_entities );
		self::assertContains( 'profil', $t_entities );
		self::assertContains( 'zuordnung', $t_entities );
	}
}
