<?php
/**
 * Unit tests for qt_massnahme_validate() – the pure catalogue validator (F1.2).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class CatalogValidateTest extends TestCase {

	/**
	 * A minimal valid measure, overridable per test.
	 *
	 * @param array $p_overrides
	 * @return array
	 */
	private function measure( array $p_overrides = array() ) {
		return array_merge( array(
			'schluessel'        => 'UW-HAB',
			'bezeichnung'       => 'Jahresunterweisung Hubarbeitsbühne',
			'typ'               => 'UW',
			'faelligkeitsmodus' => 'kalenderjahr',
			'intervall_monate'  => 12,
			'stichmonat'        => '',
		), $p_overrides );
	}

	public function testValidMeasureHasNoErrors() {
		self::assertSame( array(), qt_massnahme_validate( $this->measure() ) );
	}

	public function testMissingKeyIsRejected() {
		self::assertContains( 'error_schluessel_required',
			qt_massnahme_validate( $this->measure( array( 'schluessel' => '   ' ) ) ) );
	}

	public function testOverlongKeyIsRejected() {
		$t_errors = qt_massnahme_validate( $this->measure( array( 'schluessel' => str_repeat( 'A', 65 ) ) ) );
		self::assertContains( 'error_schluessel_length', $t_errors );
	}

	public function testKeyOfExactly64CharsIsAccepted() {
		$t_errors = qt_massnahme_validate( $this->measure( array( 'schluessel' => str_repeat( 'A', 64 ) ) ) );
		self::assertNotContains( 'error_schluessel_length', $t_errors );
	}

	public function testMissingNameIsRejected() {
		self::assertContains( 'error_bezeichnung_required',
			qt_massnahme_validate( $this->measure( array( 'bezeichnung' => '' ) ) ) );
	}

	public function testInvalidTypeIsRejected() {
		self::assertContains( 'error_typ_invalid',
			qt_massnahme_validate( $this->measure( array( 'typ' => 'XX' ) ) ) );
	}

	public function testAllValidTypesAreAccepted() {
		foreach( qt_catalog_types() as $t_type ) {
			$t_errors = qt_massnahme_validate( $this->measure( array( 'typ' => $t_type ) ) );
			self::assertNotContains( 'error_typ_invalid', $t_errors, "type $t_type" );
		}
	}

	public function testInvalidModeIsRejected() {
		self::assertContains( 'error_modus_invalid',
			qt_massnahme_validate( $this->measure( array( 'faelligkeitsmodus' => 'wtf' ) ) ) );
	}

	public function testStichmonatRequiredWhenModeIsStichmonat() {
		$t_errors = qt_massnahme_validate( $this->measure( array(
			'faelligkeitsmodus' => 'stichmonat', 'stichmonat' => '' ) ) );
		self::assertContains( 'error_stichmonat_invalid', $t_errors );
	}

	public function testStichmonatOutOfRangeIsRejected() {
		self::assertContains( 'error_stichmonat_invalid',
			qt_massnahme_validate( $this->measure( array(
				'faelligkeitsmodus' => 'stichmonat', 'stichmonat' => 13, 'intervall_monate' => 12 ) ) ) );
		self::assertContains( 'error_stichmonat_invalid',
			qt_massnahme_validate( $this->measure( array(
				'faelligkeitsmodus' => 'stichmonat', 'stichmonat' => 0, 'intervall_monate' => 12 ) ) ) );
	}

	public function testStichmonatInRangeIsAccepted() {
		$t_errors = qt_massnahme_validate( $this->measure( array(
			'faelligkeitsmodus' => 'stichmonat', 'stichmonat' => 11, 'intervall_monate' => 12 ) ) );
		self::assertSame( array(), $t_errors );
	}

	public function testIntervalRequiredForComputingModes() {
		foreach( array( 'rollierend', 'kalenderjahr' ) as $t_mode ) {
			$t_errors = qt_massnahme_validate( $this->measure( array(
				'faelligkeitsmodus' => $t_mode, 'intervall_monate' => 0 ) ) );
			self::assertContains( 'error_intervall_required', $t_errors, "mode $t_mode" );
		}
	}

	public function testExternModeNeedsNoInterval() {
		$t_errors = qt_massnahme_validate( $this->measure( array(
			'faelligkeitsmodus' => 'extern', 'intervall_monate' => '' ) ) );
		self::assertSame( array(), $t_errors );
	}

	public function testIntervalUpperBoundIsEnforced() {
		self::assertContains( 'error_intervall_required',
			qt_massnahme_validate( $this->measure( array( 'intervall_monate' => 601 ) ) ) );
	}

	public function testInvalidModeDoesNotAlsoDemandInterval() {
		# A single, focused error for a bad mode – no spurious interval error.
		$t_errors = qt_massnahme_validate( $this->measure( array(
			'faelligkeitsmodus' => 'wtf', 'intervall_monate' => 0 ) ) );
		self::assertContains( 'error_modus_invalid', $t_errors );
		self::assertNotContains( 'error_intervall_required', $t_errors );
	}
}
