<?php
/**
 * Unit tests for qt_moduswechsel_new_soll() (F5.7).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class ModeChangeTest extends TestCase {

	public function testKalenderjahrClampsToYearEnd() {
		self::assertSame( '2027-12-31', qt_moduswechsel_new_soll( '2027-03-15', 'kalenderjahr', 0 ) );
	}

	public function testStichmonatClampsToMonthEnd() {
		self::assertSame( '2027-11-30', qt_moduswechsel_new_soll( '2027-03-15', 'stichmonat', 11 ) );
		self::assertSame( '2027-02-28', qt_moduswechsel_new_soll( '2027-08-01', 'stichmonat', 2 ) );
	}

	public function testStichmonatLeapYearMonthEnd() {
		self::assertSame( '2028-02-29', qt_moduswechsel_new_soll( '2028-08-01', 'stichmonat', 2 ) );
	}

	public function testRollierendKeepsCurrentTarget() {
		self::assertSame( '2027-03-15', qt_moduswechsel_new_soll( '2027-03-15', 'rollierend', 0 ) );
	}

	public function testExternClearsTarget() {
		self::assertNull( qt_moduswechsel_new_soll( '2027-03-15', 'extern', 0 ) );
	}

	public function testInvalidStichmonatYieldsNull() {
		self::assertNull( qt_moduswechsel_new_soll( '2027-03-15', 'stichmonat', 0 ) );
		self::assertNull( qt_moduswechsel_new_soll( '2027-03-15', 'stichmonat', 13 ) );
	}

	public function testNullTargetStaysNull() {
		self::assertNull( qt_moduswechsel_new_soll( null, 'kalenderjahr', 0 ) );
		self::assertNull( qt_moduswechsel_new_soll( '', 'stichmonat', 6 ) );
	}
}
