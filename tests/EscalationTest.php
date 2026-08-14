<?php
/**
 * Unit tests for qt_eskalation_reached_count() (F5.3).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class EscalationTest extends TestCase {

	private const STUFEN = array( 90, 30, 0, -30 );

	public function testNoStageWellBeforeDue() {
		# 200 days before due → below the first (90) threshold.
		self::assertSame( 0, qt_eskalation_reached_count( '2027-03-01', '2026-08-13', self::STUFEN ) );
	}

	public function testFirstStageAt90Days() {
		# Exactly 90 days before → stage 1 reached.
		self::assertSame( 1, qt_eskalation_reached_count( '2026-11-11', '2026-08-13', self::STUFEN ) );
	}

	public function testSecondStageWithin30Days() {
		self::assertSame( 2, qt_eskalation_reached_count( '2026-09-01', '2026-08-13', self::STUFEN ) );
	}

	public function testThirdStageOnDueDate() {
		self::assertSame( 3, qt_eskalation_reached_count( '2026-08-13', '2026-08-13', self::STUFEN ) );
	}

	public function testFourthStageWhenOverdue() {
		# 40 days overdue → past the -30 threshold, all four stages.
		self::assertSame( 4, qt_eskalation_reached_count( '2026-07-04', '2026-08-13', self::STUFEN ) );
	}

	public function testFourthStageExactlyAtMinus30() {
		self::assertSame( 4, qt_eskalation_reached_count( '2026-07-14', '2026-08-13', self::STUFEN ) );
	}

	public function testNoTargetDateMeansNoStage() {
		self::assertSame( 0, qt_eskalation_reached_count( null, '2026-08-13', self::STUFEN ) );
		self::assertSame( 0, qt_eskalation_reached_count( '', '2026-08-13', self::STUFEN ) );
	}
}
