<?php
/**
 * Unit tests for QT_DueDateCalculator (F1.8 / F1.9).
 *
 * Covers every edge case from the specification: the four modes, month-end
 * clamping, leap years, anchor retention with the grace window (including the
 * boundary), late performance, the initial cycle, and back-dated import.
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class DueDateCalculatorTest extends TestCase {

	/* ---- days_in_month ------------------------------------------------- */

	public function testDaysInMonth() {
		self::assertSame( 29, QT_DueDateCalculator::days_in_month( 2024, 2 ) ); # leap
		self::assertSame( 28, QT_DueDateCalculator::days_in_month( 2023, 2 ) );
		self::assertSame( 30, QT_DueDateCalculator::days_in_month( 2026, 4 ) );
		self::assertSame( 31, QT_DueDateCalculator::days_in_month( 2026, 12 ) );
	}

	/* ---- add_months: month-end clamping & leap years ------------------- */

	public function testAddMonthsKeepsSameDayWhenValid() {
		self::assertSame( '2027-03-31', QT_DueDateCalculator::add_months( '2026-03-31', 12 ) );
		self::assertSame( '2027-04-30', QT_DueDateCalculator::add_months( '2026-04-30', 12 ) );
	}

	public function testAddMonthsClampsToShorterMonth() {
		self::assertSame( '2026-02-28', QT_DueDateCalculator::add_months( '2026-01-31', 1 ) );
		self::assertSame( '2026-06-30', QT_DueDateCalculator::add_months( '2026-05-31', 1 ) );
	}

	public function testAddMonthsClampsIntoLeapFebruary() {
		self::assertSame( '2024-02-29', QT_DueDateCalculator::add_months( '2024-01-31', 1 ) );
	}

	public function testAddMonthsLeapDayToNonLeapYear() {
		self::assertSame( '2025-02-28', QT_DueDateCalculator::add_months( '2024-02-29', 12 ) );
		self::assertSame( '2029-02-28', QT_DueDateCalculator::add_months( '2028-02-29', 12 ) );
	}

	public function testAddMonthsAcrossYearBoundary() {
		self::assertSame( '2027-01-15', QT_DueDateCalculator::add_months( '2026-11-15', 2 ) );
		self::assertSame( '2028-02-15', QT_DueDateCalculator::add_months( '2026-02-15', 24 ) );
		self::assertSame( '2027-01-31', QT_DueDateCalculator::add_months( '2026-12-31', 1 ) );
	}

	public function testAddMonthsNegativeOffset() {
		self::assertSame( '2025-12-31', QT_DueDateCalculator::add_months( '2026-01-31', -1 ) );
		self::assertSame( '2025-01-15', QT_DueDateCalculator::add_months( '2026-03-15', -14 ) );
	}

	/* ---- last day helpers --------------------------------------------- */

	public function testLastDayOfYear() {
		self::assertSame( '2027-12-31', QT_DueDateCalculator::last_day_of_year( '2027-04-30' ) );
	}

	public function testLastDayOfMonth() {
		self::assertSame( '2027-11-30', QT_DueDateCalculator::last_day_of_month( 2027, 11 ) );
		self::assertSame( '2028-02-29', QT_DueDateCalculator::last_day_of_month( 2028, 2 ) );
	}

	/* ---- day_diff ------------------------------------------------------ */

	public function testDayDiff() {
		self::assertSame( 41, QT_DueDateCalculator::day_diff( '2026-03-20', '2026-04-30' ) );
		self::assertSame( 0, QT_DueDateCalculator::day_diff( '2026-04-30', '2026-04-30' ) );
	}

	/* ---- base_date: anchor retention (F1.9) --------------------------- */

	public function testAnchorRetainedWhenTimelyWithinGrace() {
		# performed 41 days before target, grace 42 -> keep the anchor
		self::assertSame( '2026-04-30',
			QT_DueDateCalculator::base_date( '2026-03-20', '2026-04-30', 42 ) );
	}

	public function testAnchorRetainedAtExactGraceBoundary() {
		# 2026-03-19 -> 2026-04-30 is exactly 42 days; <= 42 keeps the anchor
		self::assertSame( 42, QT_DueDateCalculator::day_diff( '2026-03-19', '2026-04-30' ) );
		self::assertSame( '2026-04-30',
			QT_DueDateCalculator::base_date( '2026-03-19', '2026-04-30', 42 ) );
	}

	public function testAnchorLostJustOutsideGrace() {
		# 43 days before target -> drop the anchor, use the actual date
		self::assertSame( '2026-03-18',
			QT_DueDateCalculator::base_date( '2026-03-18', '2026-04-30', 42 ) );
	}

	public function testAnchorLostOnLatePerformance() {
		# performed after the target date -> cycle shifts back, base = actual
		self::assertSame( '2026-05-10',
			QT_DueDateCalculator::base_date( '2026-05-10', '2026-04-30', 42 ) );
	}

	public function testBaseDateWithoutAnchorUsesActual() {
		self::assertSame( '2026-03-20',
			QT_DueDateCalculator::base_date( '2026-03-20', null, 42 ) );
		self::assertSame( '2026-03-20',
			QT_DueDateCalculator::base_date( '2026-03-20', '', 42 ) );
	}

	/* ---- next_due: rollierend ----------------------------------------- */

	public function testNextDueRollingBasic() {
		self::assertSame( '2027-04-30',
			QT_DueDateCalculator::next_due( 'rollierend', '2026-04-30', null, 12, 42 ) );
	}

	public function testNextDueRollingKeepsAnchor() {
		# early but within grace -> stays on 2027-04-30, not 2027-03-20
		self::assertSame( '2027-04-30',
			QT_DueDateCalculator::next_due( 'rollierend', '2026-03-20', '2026-04-30', 12, 42 ) );
	}

	public function testNextDueRollingDropsAnchorOutsideGrace() {
		self::assertSame( '2027-03-01',
			QT_DueDateCalculator::next_due( 'rollierend', '2026-03-01', '2026-04-30', 12, 42 ) );
	}

	public function testNextDueRollingLatePerformanceShiftsBack() {
		self::assertSame( '2027-05-10',
			QT_DueDateCalculator::next_due( 'rollierend', '2026-05-10', '2026-04-30', 12, 42 ) );
	}

	/* ---- next_due: kalenderjahr --------------------------------------- */

	public function testNextDueCalendarYear() {
		self::assertSame( '2027-12-31',
			QT_DueDateCalculator::next_due( 'kalenderjahr', '2026-04-30', null, 12, 42 ) );
		self::assertSame( '2027-12-31',
			QT_DueDateCalculator::next_due( 'kalenderjahr', '2026-11-20', null, 12, 42 ) );
	}

	/* ---- next_due: stichmonat ----------------------------------------- */

	public function testNextDueReferenceMonthFromMeasure() {
		self::assertSame( '2027-11-30',
			QT_DueDateCalculator::next_due( 'stichmonat', '2026-04-30', null, 12, 42, 11 ) );
	}

	public function testNextDueReferenceMonthDepartmentOverrideWins() {
		self::assertSame( '2027-03-31',
			QT_DueDateCalculator::next_due( 'stichmonat', '2026-04-30', null, 12, 42, 11, 3 ) );
	}

	public function testNextDueReferenceMonthFebruaryLeap() {
		# base 2027-04-30 + 12 = 2028-04-30 -> last day of Feb 2028 (leap)
		self::assertSame( '2028-02-29',
			QT_DueDateCalculator::next_due( 'stichmonat', '2027-04-30', null, 12, 42, 2 ) );
	}

	public function testNextDueReferenceMonthMissingReturnsNull() {
		self::assertNull(
			QT_DueDateCalculator::next_due( 'stichmonat', '2026-04-30', null, 12, 42 ) );
	}

	/* ---- next_due: extern & guards ------------------------------------ */

	public function testNextDueExternReturnsNull() {
		self::assertNull(
			QT_DueDateCalculator::next_due( 'extern', '2026-04-30', '2026-04-30', 24, 42 ) );
	}

	public function testNextDueWithoutPerformedDateReturnsNull() {
		self::assertNull( QT_DueDateCalculator::next_due( 'rollierend', null, null, 12, 42 ) );
		self::assertNull( QT_DueDateCalculator::next_due( 'rollierend', '', null, 12, 42 ) );
	}

	public function testNextDueUnknownModeReturnsNull() {
		self::assertNull( QT_DueDateCalculator::next_due( 'quarterly', '2026-04-30', null, 3, 42 ) );
	}

	/* ---- back-dated import -------------------------------------------- */

	public function testBackDatedImportRolling() {
		self::assertSame( '2020-06-15',
			QT_DueDateCalculator::next_due( 'rollierend', '2019-06-15', null, 12, 42 ) );
	}

	public function testBackDatedImportCalendarYear() {
		self::assertSame( '2020-12-31',
			QT_DueDateCalculator::next_due( 'kalenderjahr', '2019-06-15', null, 12, 42 ) );
	}

	/* ---- initial_soll_termin (first cycle) ---------------------------- */

	public function testInitialSollTerminRolling() {
		self::assertSame( '2026-01-24',
			QT_DueDateCalculator::initial_soll_termin( '2026-01-10', 14, 'rollierend' ) );
	}

	public function testInitialSollTerminCalendarYearNoClamp() {
		self::assertSame( '2026-01-24',
			QT_DueDateCalculator::initial_soll_termin( '2026-01-10', 14, 'kalenderjahr' ) );
	}

	public function testInitialSollTerminCalendarYearClampsToYearEnd() {
		# entry 25 Dec + 14 days = 8 Jan next year -> clamp to 31 Dec of entry year
		self::assertSame( '2026-12-31',
			QT_DueDateCalculator::initial_soll_termin( '2026-12-25', 14, 'kalenderjahr' ) );
	}

	public function testInitialSollTerminCalendarYearNoClampWhenStillInYear() {
		self::assertSame( '2026-12-24',
			QT_DueDateCalculator::initial_soll_termin( '2026-12-10', 14, 'kalenderjahr' ) );
	}

	public function testInitialSollTerminRollingLateDecemberNoClamp() {
		self::assertSame( '2027-01-08',
			QT_DueDateCalculator::initial_soll_termin( '2026-12-25', 14, 'rollierend' ) );
	}
}
