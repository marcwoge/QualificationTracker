<?php
/**
 * Unit tests for qt_person_validate() and qt_person_valid_date() (F1.4).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class PersonValidateTest extends TestCase {

	private function person( array $p_overrides = array() ) {
		return array_merge( array(
			'personalnummer'            => '900001',
			'typ'                       => 'intern',
			'fremdfirma'                => '',
			'nachname'                  => 'Mustermann',
			'vorname'                   => 'Max',
			'abteilung'                 => 'Werkstatt',
			'eintritt'                  => '2020-01-15',
			'austritt'                  => '',
			'vorgesetzter_user_id'      => 0,
			'verkuerztes_intervall_bis' => '',
		), $p_overrides );
	}

	public function testValidInternalPersonHasNoErrors() {
		self::assertSame( array(), qt_person_validate( $this->person() ) );
	}

	public function testMissingLastNameIsRejected() {
		self::assertContains( 'error_nachname_required',
			qt_person_validate( $this->person( array( 'nachname' => '  ' ) ) ) );
	}

	public function testInvalidTypeIsRejected() {
		self::assertContains( 'error_person_typ_invalid',
			qt_person_validate( $this->person( array( 'typ' => 'ghost' ) ) ) );
	}

	public function testExternalStaffNeedEmployer() {
		foreach( array( 'leiharbeit', 'fremdfirma' ) as $t_typ ) {
			$t_errors = qt_person_validate( $this->person( array(
				'typ' => $t_typ, 'personalnummer' => '', 'fremdfirma' => '' ) ) );
			self::assertContains( 'error_fremdfirma_required', $t_errors, "typ $t_typ" );
		}
	}

	public function testExternalStaffWithEmployerAndNoNumberIsValid() {
		$t_errors = qt_person_validate( $this->person( array(
			'typ' => 'leiharbeit', 'personalnummer' => '', 'fremdfirma' => 'Zeitarbeit GmbH' ) ) );
		self::assertSame( array(), $t_errors );
	}

	public function testInternalStaffNeedNoEmployer() {
		$t_errors = qt_person_validate( $this->person( array( 'fremdfirma' => '' ) ) );
		self::assertNotContains( 'error_fremdfirma_required', $t_errors );
	}

	public function testOverlongPersonnelNumberIsRejected() {
		self::assertContains( 'error_personalnummer_length',
			qt_person_validate( $this->person( array( 'personalnummer' => str_repeat( '9', 65 ) ) ) ) );
	}

	public function testInvalidEntryDateIsRejected() {
		self::assertContains( 'error_eintritt_invalid',
			qt_person_validate( $this->person( array( 'eintritt' => '2020-13-01' ) ) ) );
	}

	public function testExitBeforeEntryIsRejected() {
		self::assertContains( 'error_austritt_before_eintritt',
			qt_person_validate( $this->person( array(
				'eintritt' => '2020-06-01', 'austritt' => '2020-05-31' ) ) ) );
	}

	public function testExitOnEntryDayIsAllowed() {
		$t_errors = qt_person_validate( $this->person( array(
			'eintritt' => '2020-06-01', 'austritt' => '2020-06-01' ) ) );
		self::assertNotContains( 'error_austritt_before_eintritt', $t_errors );
	}

	public function testInvalidYouthProtectionDateIsRejected() {
		self::assertContains( 'error_jugendschutz_invalid',
			qt_person_validate( $this->person( array( 'verkuerztes_intervall_bis' => 'soon' ) ) ) );
	}

	public function testValidDateHelperAcceptsEmptyAndLeapDay() {
		self::assertTrue( qt_person_valid_date( '' ) );
		self::assertTrue( qt_person_valid_date( '2024-02-29' ) );   # leap year
	}

	public function testValidDateHelperRejectsBadDates() {
		self::assertFalse( qt_person_valid_date( '2023-02-29' ) );  # not a leap year
		self::assertFalse( qt_person_valid_date( '01.02.2023' ) );  # wrong format
		self::assertFalse( qt_person_valid_date( '2023-00-10' ) );
	}
}
