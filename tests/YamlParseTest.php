<?php
/**
 * Unit tests for the restricted YAML reader and the bundled catalogue (F1.7).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class YamlParseTest extends TestCase {

	public function testScalarTypes() {
		self::assertSame( 'plain', qt_yaml_scalar( 'plain' ) );
		self::assertSame( 'quoted value', qt_yaml_scalar( '"quoted value"' ) );
		self::assertSame( "single", qt_yaml_scalar( "'single'" ) );
		self::assertTrue( qt_yaml_scalar( 'true' ) );
		self::assertFalse( qt_yaml_scalar( 'false' ) );
		self::assertSame( 12, qt_yaml_scalar( '12' ) );
		self::assertSame( -30, qt_yaml_scalar( '-30' ) );
		self::assertSame( '', qt_yaml_scalar( '' ) );
	}

	public function testQuotedValueWithSpecialCharsKeptWhole() {
		# Commas and section signs inside a quoted value must survive intact.
		self::assertSame( 'DGUV Vorschrift 1 § 4, ArbSchG § 12',
			qt_yaml_scalar( '"DGUV Vorschrift 1 § 4, ArbSchG § 12"' ) );
	}

	public function testParseSequenceOfMappings() {
		$t_yaml = "- schluessel: A\n  typ: UW\n  intervall_monate: 12\n- schluessel: B\n  typ: QU\n";
		$t_rows = qt_yaml_parse_simple( $t_yaml );
		self::assertCount( 2, $t_rows );
		self::assertSame( 'A', $t_rows[0]['schluessel'] );
		self::assertSame( 12, $t_rows[0]['intervall_monate'] );
		self::assertSame( 'B', $t_rows[1]['schluessel'] );
		self::assertSame( 'QU', $t_rows[1]['typ'] );
	}

	public function testCommentsAndBlankLinesIgnored() {
		$t_yaml = "# header comment\n\n- schluessel: A\n  # inline comment\n\n  typ: UW\n";
		$t_rows = qt_yaml_parse_simple( $t_yaml );
		self::assertCount( 1, $t_rows );
		self::assertSame( array( 'schluessel' => 'A', 'typ' => 'UW' ), $t_rows[0] );
	}

	public function testEmptyInputYieldsNoRows() {
		self::assertSame( array(), qt_yaml_parse_simple( "# only a comment\n\n" ) );
	}

	public function testBundledCatalogueParses() {
		$t_path = qt_catalog_seed_path();
		self::assertFileExists( $t_path );

		$t_rows = qt_yaml_parse_simple( file_get_contents( $t_path ) );

		# Eleven measures, keyed and typed as expected.
		self::assertCount( 11, $t_rows );

		$t_by_key = array();
		foreach( $t_rows as $t_row ) {
			$t_by_key[$t_row['schluessel']] = $t_row;
		}

		self::assertArrayHasKey( 'UW-JAHR', $t_by_key );
		self::assertSame( 'UW', $t_by_key['UW-JAHR']['typ'] );
		self::assertSame( 12, $t_by_key['UW-JAHR']['intervall_monate'] );
		self::assertTrue( $t_by_key['UW-JAHR']['wiederkehrend'] );

		# The Hubarbeitsbühne chain carries its prerequisite keys.
		self::assertSame( 'QU-HAB', $t_by_key['BE-HAB']['vorbedingungen'] );
		self::assertSame( 'BE-HAB', $t_by_key['UW-HAB']['vorbedingungen'] );

		# An external measure (Ersthelfer) has mode "extern" and no interval.
		self::assertSame( 'extern', $t_by_key['QB-ERSTEHILFE']['faelligkeitsmodus'] );
		self::assertArrayNotHasKey( 'intervall_monate', $t_by_key['QB-ERSTEHILFE'] );
	}
}
