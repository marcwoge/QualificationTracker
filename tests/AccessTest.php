<?php
/**
 * Unit test for the pure access helper qt_access_effective_abteilung() (F7.1).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class AccessTest extends TestCase {

	public function testRestrictionWinsOverRequest() {
		self::assertSame( 'Werkstatt', qt_access_effective_abteilung( '', 'Werkstatt' ) );
		self::assertSame( 'Werkstatt', qt_access_effective_abteilung( 'Büro', 'Werkstatt' ) );
	}

	public function testRequestUsedWhenNoRestriction() {
		self::assertSame( 'Büro', qt_access_effective_abteilung( 'Büro', '' ) );
		self::assertSame( '', qt_access_effective_abteilung( '', '' ) );
	}
}
