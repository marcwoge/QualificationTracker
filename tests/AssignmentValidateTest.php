<?php
/**
 * Unit tests for qt_zuordnung_validate() (F2.2).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class AssignmentValidateTest extends TestCase {

	private function assignment( array $p_overrides = array() ) {
		return array_merge( array(
			'person_id'   => 5,
			'profil_id'   => 3,
			'gueltig_ab'  => '2026-01-01',
			'gueltig_bis' => '',
		), $p_overrides );
	}

	public function testValidAssignmentHasNoErrors() {
		self::assertSame( array(), qt_zuordnung_validate( $this->assignment() ) );
	}

	public function testMissingPersonIsRejected() {
		self::assertContains( 'error_zuordnung_person_required',
			qt_zuordnung_validate( $this->assignment( array( 'person_id' => 0 ) ) ) );
	}

	public function testMissingProfileIsRejected() {
		self::assertContains( 'error_zuordnung_profil_required',
			qt_zuordnung_validate( $this->assignment( array( 'profil_id' => 0 ) ) ) );
	}

	public function testInvalidFromDateIsRejected() {
		self::assertContains( 'error_gueltig_ab_invalid',
			qt_zuordnung_validate( $this->assignment( array( 'gueltig_ab' => '2026-02-30' ) ) ) );
	}

	public function testInvalidUntilDateIsRejected() {
		self::assertContains( 'error_gueltig_bis_invalid',
			qt_zuordnung_validate( $this->assignment( array( 'gueltig_bis' => 'nope' ) ) ) );
	}

	public function testUntilBeforeFromIsRejected() {
		self::assertContains( 'error_gueltig_bis_before_ab',
			qt_zuordnung_validate( $this->assignment( array(
				'gueltig_ab' => '2026-06-01', 'gueltig_bis' => '2026-05-31' ) ) ) );
	}

	public function testOpenAssignmentWithoutDatesIsValid() {
		self::assertSame( array(), qt_zuordnung_validate( $this->assignment( array(
			'gueltig_ab' => '', 'gueltig_bis' => '' ) ) ) );
	}

	public function testUntilOnFromDayIsAllowed() {
		self::assertNotContains( 'error_gueltig_bis_before_ab',
			qt_zuordnung_validate( $this->assignment( array(
				'gueltig_ab' => '2026-06-01', 'gueltig_bis' => '2026-06-01' ) ) ) );
	}
}
