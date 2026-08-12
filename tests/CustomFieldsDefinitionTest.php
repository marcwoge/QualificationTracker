<?php
/**
 * Unit tests for the pure custom-field definitions (F1.5).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class CustomFieldsDefinitionTest extends TestCase {

	private function names() {
		return array_column( qt_custom_fields_definitions(), 'name' );
	}

	public function testAllConceptFieldsArePresent() {
		$t_expected = array(
			'mitarbeiter', 'personalnummer', 'massnahmenschluessel', 'rechtsgrundlage',
			'durchgefuehrt_am', 'gueltig_bis', 'intervall_monate', 'faelligkeitsmodus',
			'soll_termin', 'durchfuehrender', 'nachweisart', 'abteilung', 'veranstaltung_id',
		);
		self::assertSame( $t_expected, $this->names() );
	}

	public function testNamesAreUnique() {
		$t_names = $this->names();
		self::assertSame( count( $t_names ), count( array_unique( $t_names ) ) );
	}

	public function testNoFreeTextFindingsField() {
		# Data protection: occupational-health proofs must not be able to store a
		# diagnosis. There must be no findings field.
		foreach( $this->names() as $t_name ) {
			self::assertStringNotContainsStringIgnoringCase( 'befund', $t_name );
			self::assertStringNotContainsStringIgnoringCase( 'diagnos', $t_name );
		}
	}

	public function testDateFieldsHaveDateType() {
		foreach( qt_custom_fields_definitions() as $t_def ) {
			if( in_array( $t_def['name'], array( 'durchgefuehrt_am', 'gueltig_bis', 'soll_termin' ), true ) ) {
				self::assertSame( CUSTOM_FIELD_TYPE_DATE, $t_def['type'], $t_def['name'] );
			}
		}
	}

	public function testIntervalIsNumeric() {
		$t_def = $this->defByName( 'intervall_monate' );
		self::assertSame( CUSTOM_FIELD_TYPE_NUMERIC, $t_def['type'] );
	}

	public function testModeEnumMatchesCalculatorModes() {
		$t_def = $this->defByName( 'faelligkeitsmodus' );
		self::assertSame( CUSTOM_FIELD_TYPE_ENUM, $t_def['type'] );
		self::assertSame( implode( '|', qt_catalog_modes() ), $t_def['possible_values'] );
	}

	private function defByName( $p_name ) {
		foreach( qt_custom_fields_definitions() as $t_def ) {
			if( $t_def['name'] === $p_name ) {
				return $t_def;
			}
		}
		self::fail( "definition $p_name not found" );
	}
}
