<?php
/**
 * Unit tests for the pure retention/deletion helpers (F7.3):
 * qt_loesch_retention_months(), qt_loesch_anchor(), qt_loesch_delete_on(),
 * qt_loesch_is_due() and qt_loesch_state_final().
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class DeletionTest extends TestCase {

	public function testRetentionUsesTypeOverrideWhenPresent() {
		self::assertSame( 60, qt_loesch_retention_months( 'VO', array( 'VO' => 60 ), 36 ) );
	}

	public function testRetentionFallsBackToDefault() {
		self::assertSame( 36, qt_loesch_retention_months( 'UW', array( 'VO' => 60 ), 36 ) );
		self::assertSame( 36, qt_loesch_retention_months( 'UW', array(), 36 ) );
	}

	public function testRetentionIgnoresEmptyOrNonPositiveOverride() {
		self::assertSame( 36, qt_loesch_retention_months( 'UW', array( 'UW' => '' ), 36 ) );
		self::assertSame( 36, qt_loesch_retention_months( 'UW', array( 'UW' => 0 ), 36 ) );
	}

	public function testStateFinalOnlyForFinishedCycles() {
		self::assertTrue( qt_loesch_state_final( 'abgelaufen' ) );
		self::assertTrue( qt_loesch_state_final( 'entfallen' ) );
		self::assertFalse( qt_loesch_state_final( 'gueltig' ) );
		self::assertFalse( qt_loesch_state_final( 'offen' ) );
		self::assertFalse( qt_loesch_state_final( 'geplant' ) );
	}

	public function testAnchorForExpiredUsesValidityEnd() {
		self::assertSame( '2020-06-30', qt_loesch_anchor( 'abgelaufen', '2020-06-30', '2024-01-01' ) );
	}

	public function testAnchorForExpiredWithoutValidityFallsBackToModified() {
		self::assertSame( '2024-01-01', qt_loesch_anchor( 'abgelaufen', null, '2024-01-01' ) );
		self::assertSame( '2024-01-01', qt_loesch_anchor( 'abgelaufen', '', '2024-01-01' ) );
	}

	public function testAnchorForCancelledUsesModifiedDate() {
		# A cancelled proof has no validity relevance; the modification date anchors it.
		self::assertSame( '2023-03-15', qt_loesch_anchor( 'entfallen', '2020-06-30', '2023-03-15' ) );
	}

	public function testAnchorNullWhenNothingAvailable() {
		self::assertNull( qt_loesch_anchor( 'entfallen', null, null ) );
	}

	public function testDeleteOnAddsMonthsToAnchor() {
		self::assertSame( '2023-06-30', qt_loesch_delete_on( '2020-06-30', 36 ) );
	}

	public function testDeleteOnClampsShortMonth() {
		# 31 Jan + 1 month clamps to the last valid February day.
		self::assertSame( '2021-02-28', qt_loesch_delete_on( '2021-01-31', 1 ) );
	}

	public function testDeleteOnNullWhenNoAnchorOrNoPeriod() {
		self::assertNull( qt_loesch_delete_on( null, 36 ) );
		self::assertNull( qt_loesch_delete_on( '', 36 ) );
		self::assertNull( qt_loesch_delete_on( '2020-06-30', 0 ) );
	}

	public function testIsDueWhenDeletionDateReached() {
		self::assertTrue( qt_loesch_is_due( '2024-01-01', '2024-01-01' ) );
		self::assertTrue( qt_loesch_is_due( '2023-12-31', '2024-01-01' ) );
	}

	public function testIsNotDueBeforeDeletionDate() {
		self::assertFalse( qt_loesch_is_due( '2024-01-02', '2024-01-01' ) );
	}

	public function testIsNotDueWithoutDeletionDate() {
		self::assertFalse( qt_loesch_is_due( null, '2024-01-01' ) );
		self::assertFalse( qt_loesch_is_due( '', '2024-01-01' ) );
	}
}
