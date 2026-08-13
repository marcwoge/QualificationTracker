<?php
/**
 * Unit tests for the pure matrix helpers qt_matrix_cell() and
 * qt_matrix_warn_days() (F4.1).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class MatrixCellTest extends TestCase {

	private const TODAY = '2026-08-13';

	private function nachweis( array $p ) {
		return array_merge( array(
			'status'      => 'gueltig',
			'gueltig_bis' => null,
			'bug_id'      => 0,
			'massnahme_id' => 1,
			'person_id'   => 1,
		), $p );
	}

	public function testWarnDaysUsesLargestPositiveStage() {
		self::assertSame( 90, qt_matrix_warn_days( array( 90, 30, 0, -30 ) ) );
		self::assertSame( 120, qt_matrix_warn_days( array( 30, 120, 0 ) ) );
	}

	public function testWarnDaysFallsBackTo90() {
		self::assertSame( 90, qt_matrix_warn_days( array() ) );
		self::assertSame( 90, qt_matrix_warn_days( array( 0, -30 ) ) );
		self::assertSame( 90, qt_matrix_warn_days( 'nonsense' ) );
	}

	public function testMissingProofIsFehlt() {
		$t = qt_matrix_cell( array(), self::TODAY, 90 );
		self::assertSame( 'fehlt', $t['state'] );
		self::assertNull( $t['rest'] );
	}

	public function testValidFarAwayIsGueltigWithRemainingDays() {
		$t = qt_matrix_cell(
			array( $this->nachweis( array( 'gueltig_bis' => '2027-12-31', 'bug_id' => 7 ) ) ),
			self::TODAY, 90 );
		self::assertSame( 'gueltig', $t['state'] );
		self::assertSame( 7, $t['bug_id'] );
		self::assertGreaterThan( 90, $t['rest'] );
	}

	public function testValidWithNoEndDateIsGueltigInfinite() {
		$t = qt_matrix_cell(
			array( $this->nachweis( array( 'gueltig_bis' => null ) ) ),
			self::TODAY, 90 );
		self::assertSame( 'gueltig', $t['state'] );
		self::assertNull( $t['rest'] );
	}

	public function testValidWithinWindowIsBald() {
		$t = qt_matrix_cell(
			array( $this->nachweis( array( 'gueltig_bis' => '2026-09-01' ) ) ),  # 19 days
			self::TODAY, 90 );
		self::assertSame( 'bald', $t['state'] );
		self::assertLessThanOrEqual( 90, $t['rest'] );
	}

	public function testExpiredProofIsAbgelaufen() {
		$t = qt_matrix_cell(
			array( $this->nachweis( array( 'status' => 'gueltig', 'gueltig_bis' => '2026-01-01' ) ) ),
			self::TODAY, 90 );
		self::assertSame( 'abgelaufen', $t['state'] );
	}

	public function testOpenProofIsOffen() {
		$t = qt_matrix_cell(
			array( $this->nachweis( array( 'status' => 'offen', 'gueltig_bis' => null, 'bug_id' => 12 ) ) ),
			self::TODAY, 90 );
		self::assertSame( 'offen', $t['state'] );
		self::assertSame( 12, $t['bug_id'] );
	}

	public function testValidWinsOverOpenAndLinksToValidTicket() {
		$t = qt_matrix_cell( array(
			$this->nachweis( array( 'status' => 'offen', 'gueltig_bis' => null, 'bug_id' => 5 ) ),
			$this->nachweis( array( 'status' => 'gueltig', 'gueltig_bis' => '2028-06-30', 'bug_id' => 9 ) ),
		), self::TODAY, 90 );
		self::assertSame( 'gueltig', $t['state'] );
		self::assertSame( 9, $t['bug_id'] );
	}

	public function testCancelledProofsAreIgnored() {
		$t = qt_matrix_cell(
			array( $this->nachweis( array( 'status' => 'entfallen', 'bug_id' => 3 ) ) ),
			self::TODAY, 90 );
		self::assertSame( 'fehlt', $t['state'] );
	}

	public function testRowHasStateFindsMatch() {
		$t_cells = array(
			10 => array( 'state' => 'gueltig' ),
			11 => array( 'state' => 'abgelaufen' ),
			12 => array( 'state' => 'offen' ),
		);
		self::assertTrue( qt_matrix_row_has_state( $t_cells, 'abgelaufen' ) );
		self::assertTrue( qt_matrix_row_has_state( $t_cells, 'gueltig' ) );
	}

	public function testRowHasStateReturnsFalseWhenAbsent() {
		$t_cells = array( 10 => array( 'state' => 'gueltig' ) );
		self::assertFalse( qt_matrix_row_has_state( $t_cells, 'fehlt' ) );
		self::assertFalse( qt_matrix_row_has_state( array(), 'gueltig' ) );
	}

	public function testAuditRateComputesPercentage() {
		self::assertSame( 100.0, qt_audit_rate( 8, 8 ) );
		self::assertSame( 50.0, qt_audit_rate( 5, 10 ) );
		self::assertSame( 33.3, qt_audit_rate( 1, 3 ) );
	}

	public function testAuditRateIsZeroWhenNothingRequired() {
		self::assertSame( 0.0, qt_audit_rate( 0, 0 ) );
		self::assertSame( 0.0, qt_audit_rate( 5, 0 ) );
	}
}
