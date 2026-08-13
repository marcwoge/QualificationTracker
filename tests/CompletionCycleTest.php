<?php
/**
 * Unit tests for the pure completion and cohort helpers (F2.8).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class CompletionCycleTest extends TestCase {

	/* ---- qt_completion_gueltig_bis ------------------------------------ */

	public function testExternUsesProvidedDate() {
		self::assertSame( '2028-05-01',
			qt_completion_gueltig_bis( 'extern', '2026-05-01', null, 24, 42, null, null, '2028-05-01' ) );
	}

	public function testExternWithoutDateIsNull() {
		self::assertNull(
			qt_completion_gueltig_bis( 'extern', '2026-05-01', null, 24, 42, null, null, '' ) );
	}

	public function testRollingUsesCalculatedNextDue() {
		self::assertSame( '2027-08-13',
			qt_completion_gueltig_bis( 'rollierend', '2026-08-13', null, 12, 42, null, null, null ) );
	}

	public function testCalendarYearUsesYearEnd() {
		self::assertSame( '2027-12-31',
			qt_completion_gueltig_bis( 'kalenderjahr', '2026-08-13', null, 12, 42, null, null, null ) );
	}

	public function testAnchorIsRespectedInCompletion() {
		# Performed within grace before the target -> keep the anchor 30 Apr.
		self::assertSame( '2027-04-30',
			qt_completion_gueltig_bis( 'rollierend', '2026-03-20', '2026-04-30', 12, 42, null, null, null ) );
	}

	/* ---- qt_generator_cycle_termin ------------------------------------ */

	private function measure( $p_modus, $p_stichmonat = null ) {
		return array( 'faelligkeitsmodus' => $p_modus, 'stichmonat' => $p_stichmonat );
	}

	public function testCycleTerminCalendarYear() {
		self::assertSame( '2027-12-31',
			qt_generator_cycle_termin( $this->measure( 'kalenderjahr' ), 2027, null ) );
	}

	public function testCycleTerminReferenceMonthFromMeasure() {
		self::assertSame( '2027-11-30',
			qt_generator_cycle_termin( $this->measure( 'stichmonat', 11 ), 2027, null ) );
	}

	public function testCycleTerminDepartmentOverrideWins() {
		self::assertSame( '2027-03-31',
			qt_generator_cycle_termin( $this->measure( 'stichmonat', 11 ), 2027, 3 ) );
	}

	public function testCycleTerminNullForEventDrivenModes() {
		self::assertNull( qt_generator_cycle_termin( $this->measure( 'rollierend' ), 2027, null ) );
		self::assertNull( qt_generator_cycle_termin( $this->measure( 'extern' ), 2027, null ) );
	}
}
