<?php
/**
 * Unit tests for qt_lauf_format_result() (F5.6).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class RunLogTest extends TestCase {

	public function testFormatsScalarCounts() {
		self::assertSame( 'expired: 3', qt_lauf_format_result( array( 'expired' => 3 ) ) );
		self::assertSame( 'deferred: 1, reactivated: 2',
			qt_lauf_format_result( array( 'deferred' => 1, 'reactivated' => 2 ) ) );
	}

	public function testArrayValuesBecomeCounts() {
		self::assertSame( 'expired: 2, bug_ids: 3',
			qt_lauf_format_result( array( 'expired' => 2, 'bug_ids' => array( 10, 11, 12 ) ) ) );
	}

	public function testBooleanBecomesBinary() {
		self::assertSame( 'deferred: 0, reactivated: 0, reveille: 1',
			qt_lauf_format_result( array( 'deferred' => 0, 'reactivated' => 0, 'reveille' => true ) ) );
	}

	public function testEmptyResultIsEmptyString() {
		self::assertSame( '', qt_lauf_format_result( array() ) );
	}
}
