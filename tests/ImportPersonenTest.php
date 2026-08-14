<?php
/**
 * Unit tests for the pure CSV import helpers (F6.1):
 * qt_import_personen_parse(), qt_import_personen_map_row() and
 * qt_import_personen_bool().
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class ImportPersonenTest extends TestCase {

	public function testParseHeaderAndRows() {
		$t_csv = "personalnummer;nachname;vorname;abteilung\n900001;Mustermann;Max;Werkstatt\n900002;Beispiel;Erika;Büro";
		$t_rows = qt_import_personen_parse( $t_csv, ';' );
		self::assertCount( 2, $t_rows );
		self::assertSame( '900001', $t_rows[0]['personalnummer'] );
		self::assertSame( 'Mustermann', $t_rows[0]['nachname'] );
		self::assertSame( 'Büro', $t_rows[1]['abteilung'] );
	}

	public function testParseStripsBomAndBlankLines() {
		$t_csv = "\xEF\xBB\xBFpersonalnummer;nachname\n\n900003;Test\n";
		$t_rows = qt_import_personen_parse( $t_csv, ';' );
		self::assertCount( 1, $t_rows );
		self::assertSame( 'personalnummer', array_keys( $t_rows[0] )[0] );
		self::assertSame( '900003', $t_rows[0]['personalnummer'] );
	}

	public function testParseHandlesShortRows() {
		$t_csv = "personalnummer;nachname;abteilung\n900004;Kurz";
		$t_rows = qt_import_personen_parse( $t_csv, ';' );
		self::assertSame( '', $t_rows[0]['abteilung'] );
	}

	public function testBoolInterpretation() {
		self::assertSame( 1, qt_import_personen_bool( 'ja' ) );
		self::assertSame( 1, qt_import_personen_bool( '1' ) );
		self::assertSame( 0, qt_import_personen_bool( 'nein' ) );
		self::assertSame( 0, qt_import_personen_bool( '0' ) );
		self::assertSame( 1, qt_import_personen_bool( '', 1 ) );
		self::assertSame( 0, qt_import_personen_bool( '', 0 ) );
	}

	public function testMapRowDefaultsTypToIntern() {
		$t_data = qt_import_personen_map_row( array( 'personalnummer' => ' 900001 ', 'nachname' => 'Test' ) );
		self::assertSame( '900001', $t_data['personalnummer'] );
		self::assertSame( 'intern', $t_data['typ'] );
		self::assertSame( 1, $t_data['aktiv'] );
		self::assertArrayHasKey( 'vorgesetzter', $t_data );
	}

	public function testMapRowKeepsSupervisorUsername() {
		$t_data = qt_import_personen_map_row( array(
			'personalnummer' => '900002', 'nachname' => 'Chef', 'vorgesetzter' => 'manager1', 'aktiv' => 'nein' ) );
		self::assertSame( 'manager1', $t_data['vorgesetzter'] );
		self::assertSame( 0, $t_data['aktiv'] );
	}
}
