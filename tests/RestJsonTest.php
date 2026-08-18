<?php
/**
 * Unit tests for the pure REST serialisers qt_rest_person_json() and
 * qt_rest_nachweis_json() (F6.3).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class RestJsonTest extends TestCase {

	public function testPersonJsonShapeAndTypes() {
		$t = qt_rest_person_json( array(
			'id' => '7', 'personalnummer' => '900001', 'typ' => 'intern', 'fremdfirma' => '',
			'nachname' => 'Mustermann', 'vorname' => 'Max', 'abteilung' => 'Werkstatt',
			'eintritt' => '2020-01-15', 'austritt' => null,
			'vorgesetzter_user_id' => '3', 'aktiv' => '1' ) );

		self::assertSame( 7, $t['id'] );
		self::assertSame( '900001', $t['personalnummer'] );
		self::assertSame( 3, $t['vorgesetzter_user_id'] );
		self::assertTrue( $t['aktiv'] );
		self::assertNull( $t['austritt'] );
		self::assertSame(
			array( 'id', 'personalnummer', 'typ', 'fremdfirma', 'nachname', 'vorname',
				'abteilung', 'eintritt', 'austritt', 'vorgesetzter_user_id', 'aktiv' ),
			array_keys( $t ) );
	}

	public function testPersonJsonInactive() {
		$t = qt_rest_person_json( array(
			'id' => 1, 'personalnummer' => '', 'typ' => 'fremdfirma', 'fremdfirma' => 'ACME',
			'nachname' => 'X', 'vorname' => '', 'abteilung' => '', 'eintritt' => null,
			'austritt' => null, 'vorgesetzter_user_id' => 0, 'aktiv' => 0 ) );
		self::assertFalse( $t['aktiv'] );
	}

	public function testNachweisJsonShapeAndTypes() {
		$t = qt_rest_nachweis_json( array(
			'id' => '12', 'person_id' => '7', 'massnahme_id' => '84', 'bug_id' => '30',
			'soll_termin' => '2027-12-31', 'gueltig_bis' => null, 'status' => 'gueltig', 'zyklus' => '2027' ) );

		self::assertSame( 12, $t['id'] );
		self::assertSame( 84, $t['massnahme_id'] );
		self::assertSame( 'gueltig', $t['status'] );
		self::assertNull( $t['gueltig_bis'] );
		self::assertSame(
			array( 'id', 'person_id', 'massnahme_id', 'bug_id', 'soll_termin', 'gueltig_bis', 'status', 'zyklus' ),
			array_keys( $t ) );
	}
}
