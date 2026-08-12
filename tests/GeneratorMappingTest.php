<?php
/**
 * Unit tests for the pure parts of the generator (F2.3): status mapping and
 * category mapping.
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class GeneratorMappingTest extends TestCase {

	public function testDefaultStatusMappingCoversAllDomainStates() {
		$t_map = qt_status_default_mapping();
		foreach( array( 'offen', 'geplant', 'durchgefuehrt', 'gueltig', 'abgelaufen', 'entfallen' ) as $t_state ) {
			self::assertArrayHasKey( $t_state, $t_map, $t_state );
			self::assertIsInt( $t_map[$t_state] );
		}
	}

	public function testDefaultMappingUsesStandardStatusValues() {
		$t_map = qt_status_default_mapping();
		self::assertSame( 10, $t_map['offen'] );      # NEW_
		self::assertSame( 80, $t_map['gueltig'] );    # resolved
		self::assertSame( 90, $t_map['entfallen'] );  # closed
	}

	public function testCategoryMapCoversAllMeasureTypes() {
		$t_map = qt_generator_category_map();
		foreach( array( 'UW', 'QU', 'QB', 'BE', 'VO' ) as $t_typ ) {
			self::assertArrayHasKey( $t_typ, $t_map, $t_typ );
			self::assertNotSame( '', $t_map[$t_typ] );
		}
	}

	public function testQualificationTypesShareOneCategory() {
		$t_map = qt_generator_category_map();
		self::assertSame( $t_map['QU'], $t_map['QB'] );
	}
}
