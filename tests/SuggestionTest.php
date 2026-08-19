<?php
/**
 * Unit tests for the pure event-suggestion helpers (F3.7):
 * qt_vorschlag_actionable(), qt_vorschlag_state_rank(), qt_vorschlag_sessions(),
 * qt_vorschlag_target_date() and qt_vorschlag_termin().
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class SuggestionTest extends TestCase {

	public function testActionableStates() {
		foreach( array( 'abgelaufen', 'fehlt', 'offen', 'bald' ) as $s ) {
			self::assertTrue( qt_vorschlag_actionable( $s ), $s );
		}
		self::assertFalse( qt_vorschlag_actionable( 'gueltig' ) );
		self::assertFalse( qt_vorschlag_actionable( 'na' ) );
	}

	public function testStateRankOrdersByUrgency() {
		self::assertGreaterThan( qt_vorschlag_state_rank( 'fehlt' ), qt_vorschlag_state_rank( 'abgelaufen' ) );
		self::assertGreaterThan( qt_vorschlag_state_rank( 'offen' ), qt_vorschlag_state_rank( 'fehlt' ) );
		self::assertGreaterThan( qt_vorschlag_state_rank( 'bald' ), qt_vorschlag_state_rank( 'offen' ) );
		self::assertSame( -1, qt_vorschlag_state_rank( 'gueltig' ) );
	}

	public function testSessionsSplitByCapacity() {
		$t = qt_vorschlag_sessions( range( 1, 25 ), 10 );
		self::assertCount( 3, $t );
		self::assertCount( 10, $t[0] );
		self::assertCount( 10, $t[1] );
		self::assertCount( 5, $t[2] );
	}

	public function testSessionsExactMultiple() {
		$t = qt_vorschlag_sessions( range( 1, 20 ), 10 );
		self::assertCount( 2, $t );
	}

	public function testSessionsUnlimitedWhenCapacityZero() {
		$t = qt_vorschlag_sessions( range( 1, 30 ), 0 );
		self::assertCount( 1, $t );
		self::assertCount( 30, $t[0] );
	}

	public function testSessionsEmptyInput() {
		self::assertSame( array(), qt_vorschlag_sessions( array(), 10 ) );
		self::assertSame( array(), qt_vorschlag_sessions( array(), 0 ) );
	}

	public function testTargetDatePrefersEarliestSoll() {
		$t_rows = array(
			array( 'soll_termin' => '2026-05-01', 'gueltig_bis' => '2026-04-01' ),
			array( 'soll_termin' => '2026-03-01', 'gueltig_bis' => '2027-03-01' ),
		);
		self::assertSame( '2026-03-01', qt_vorschlag_target_date( $t_rows ) );
	}

	public function testTargetDateFallsBackToGueltigBis() {
		$t_rows = array( array( 'soll_termin' => '', 'gueltig_bis' => '2026-09-30' ) );
		self::assertSame( '2026-09-30', qt_vorschlag_target_date( $t_rows ) );
	}

	public function testTargetDateEmptyWhenNoDates() {
		self::assertSame( '', qt_vorschlag_target_date( array( array( 'soll_termin' => '', 'gueltig_bis' => '' ) ) ) );
		self::assertSame( '', qt_vorschlag_target_date( array() ) );
	}

	public function testTerminSchedulesNearFutureDueDate() {
		# Due well beyond the lead (42 days out) -> propose the due date itself.
		self::assertSame( '2026-10-01', qt_vorschlag_termin( '2026-10-01', '2026-08-19', 14 ) );
	}

	public function testTerminUsesMinimumLeadWhenDuePast() {
		# Expired (due in the past) -> as soon as possible = today + lead.
		self::assertSame( '2026-09-02', qt_vorschlag_termin( '2026-01-01', '2026-08-19', 14 ) );
	}

	public function testTerminUsesMinimumLeadWhenNoDate() {
		self::assertSame( '2026-09-02', qt_vorschlag_termin( '', '2026-08-19', 14 ) );
	}

	public function testTerminUsesMinimumLeadWhenDueTooSoon() {
		# Due in 3 days but lead is 14 -> push out to today + lead.
		self::assertSame( '2026-09-02', qt_vorschlag_termin( '2026-08-22', '2026-08-19', 14 ) );
	}
}
