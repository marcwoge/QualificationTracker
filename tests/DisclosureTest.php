<?php
/**
 * Unit tests for the pure disclosure helpers (F7.4):
 * qt_auskunft_person_fields() and qt_auskunft_filename().
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class DisclosureTest extends TestCase {

	private function person() {
		return array(
			'id'                        => 5,
			'personalnummer'            => '900123',
			'typ'                       => 'intern',
			'fremdfirma'                => '',
			'nachname'                  => 'Mustermann',
			'vorname'                   => 'Erika',
			'abteilung'                 => 'Logistik',
			'eintritt'                  => '2019-04-01',
			'austritt'                  => '',
			'vorgesetzter_user_id'      => 3,
			'vorgesetzter_name'         => 'chef',
			'verkuerztes_intervall_bis' => '',
			'aktiv'                     => 1,
		);
	}

	public function testFieldsCoverEveryMasterAttribute() {
		$t_keys = array_map( function( $f ) { return $f['key']; }, qt_auskunft_person_fields( $this->person() ) );
		foreach( array( 'col_personalnummer', 'label_person_typ', 'label_fremdfirma', 'label_nachname',
			'label_vorname', 'col_abteilung', 'label_eintritt', 'label_austritt', 'label_vorgesetzter',
			'label_jugendschutz', 'label_aktiv' ) as $t_expected ) {
			self::assertContains( $t_expected, $t_keys );
		}
	}

	public function testEmptyValuesBecomeDash() {
		$t_fields = qt_auskunft_person_fields( $this->person() );
		$t_map = array();
		foreach( $t_fields as $f ) { $t_map[$f['key']] = $f; }
		self::assertSame( '–', $t_map['label_fremdfirma']['value'] );
		self::assertSame( '–', $t_map['label_austritt']['value'] );
		self::assertSame( '–', $t_map['label_jugendschutz']['value'] );
	}

	public function testPlainValuesArePassedThrough() {
		$t_fields = qt_auskunft_person_fields( $this->person() );
		$t_map = array();
		foreach( $t_fields as $f ) { $t_map[$f['key']] = $f; }
		self::assertSame( '900123', $t_map['col_personalnummer']['value'] );
		self::assertSame( 'Mustermann', $t_map['label_nachname']['value'] );
		self::assertSame( 'Logistik', $t_map['col_abteilung']['value'] );
		self::assertSame( 'chef', $t_map['label_vorgesetzter']['value'] );
	}

	public function testActiveFieldIsATranslatableBoolean() {
		$t_fields = qt_auskunft_person_fields( $this->person() );
		$t_map = array();
		foreach( $t_fields as $f ) { $t_map[$f['key']] = $f; }
		self::assertTrue( $t_map['label_aktiv']['translate'] );
		self::assertSame( 'bool_yes', $t_map['label_aktiv']['value'] );

		$t_inactive = $this->person();
		$t_inactive['aktiv'] = 0;
		$t_fields2 = qt_auskunft_person_fields( $t_inactive );
		$t_map2 = array();
		foreach( $t_fields2 as $f ) { $t_map2[$f['key']] = $f; }
		self::assertSame( 'bool_no', $t_map2['label_aktiv']['value'] );
	}

	public function testSupervisorFallsBackToDashWithoutName() {
		$t_person = $this->person();
		unset( $t_person['vorgesetzter_name'] );
		$t_fields = qt_auskunft_person_fields( $t_person );
		$t_map = array();
		foreach( $t_fields as $f ) { $t_map[$f['key']] = $f; }
		self::assertSame( '–', $t_map['label_vorgesetzter']['value'] );
	}

	public function testFilenameIsDescriptiveAndSafe() {
		self::assertSame( 'Auskunft_Mustermann_Erika_900123_2026-08-19',
			qt_auskunft_filename( $this->person(), '2026-08-19' ) );
	}

	public function testFilenameSanitisesUnsafeCharacters() {
		$t_person = array( 'nachname' => 'Å/B ç', 'vorname' => 'Jörg*', 'personalnummer' => '' );
		$t_name = qt_auskunft_filename( $t_person, '2026-08-19' );
		self::assertMatchesRegularExpression( '/^[\w.\-]+$/u', $t_name );
		self::assertStringStartsWith( 'Auskunft_', $t_name );
		self::assertStringContainsString( '2026-08-19', $t_name );
	}

	public function testFilenameSkipsMissingParts() {
		$t_person = array( 'nachname' => 'Solo', 'vorname' => '', 'personalnummer' => '' );
		self::assertSame( 'Auskunft_Solo_2026-08-19', qt_auskunft_filename( $t_person, '2026-08-19' ) );
	}
}
