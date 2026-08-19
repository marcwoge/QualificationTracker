<?php
/**
 * Unit tests for the pure records-of-processing helper (F7.6):
 * qt_verzeichnis_aufbewahrung_zeilen().
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class RegisterTest extends TestCase {

	public function testFirstRowIsTheDefault() {
		$t_rows = qt_verzeichnis_aufbewahrung_zeilen( 36, array(), array( 'UW', 'VO' ) );
		self::assertTrue( $t_rows[0]['is_default'] );
		self::assertSame( '', $t_rows[0]['typ'] );
		self::assertSame( 36, $t_rows[0]['monate'] );
	}

	public function testOneRowPerType() {
		$t_types = array( 'UW', 'QU', 'QB', 'BE', 'VO' );
		$t_rows = qt_verzeichnis_aufbewahrung_zeilen( 36, array(), $t_types );
		# default row + one per type
		self::assertCount( count( $t_types ) + 1, $t_rows );
	}

	public function testTypeOverrideIsReflected() {
		$t_rows = qt_verzeichnis_aufbewahrung_zeilen( 36, array( 'VO' => 60 ), array( 'UW', 'VO' ) );
		$t_map = array();
		foreach( $t_rows as $r ) { if( !$r['is_default'] ) { $t_map[$r['typ']] = $r['monate']; } }
		self::assertSame( 36, $t_map['UW'] );
		self::assertSame( 60, $t_map['VO'] );
	}

	public function testDefaultAppliesWhenTypeHasNoOverride() {
		$t_rows = qt_verzeichnis_aufbewahrung_zeilen( 24, array( 'BE' => 0 ), array( 'BE' ) );
		# 0 override is treated as "use default" by the retention resolver.
		self::assertSame( 24, $t_rows[1]['monate'] );
	}
}
