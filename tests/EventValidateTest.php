<?php
/**
 * Unit tests for qt_event_validate(), qt_event_valid_termin() and
 * qt_event_normalise_termin() (F3.1).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class EventValidateTest extends TestCase {

	private function event( array $p_overrides = array() ) {
		return array_merge( array(
			'massnahme_id'   => 5,
			'titel'          => 'Jahresunterweisung 2026',
			'termin'         => '2026-03-15T09:00',
			'ort'            => 'Schulungsraum 1',
			'unterweisender' => 'Frau Beispiel',
			'kapazitaet'     => '20',
			'status'         => 'geplant',
		), $p_overrides );
	}

	public function testValidEventHasNoErrors() {
		self::assertSame( array(), qt_event_validate( $this->event() ) );
	}

	public function testMissingMeasureIsRejected() {
		self::assertContains( 'error_event_massnahme_required',
			qt_event_validate( $this->event( array( 'massnahme_id' => 0 ) ) ) );
	}

	public function testMissingTitleIsRejected() {
		self::assertContains( 'error_event_titel_required',
			qt_event_validate( $this->event( array( 'titel' => '   ' ) ) ) );
	}

	public function testOverlongTitleIsRejected() {
		self::assertContains( 'error_event_titel_length',
			qt_event_validate( $this->event( array( 'titel' => str_repeat( 'x', 192 ) ) ) ) );
	}

	public function testTitleAtLimitIsAllowed() {
		self::assertNotContains( 'error_event_titel_length',
			qt_event_validate( $this->event( array( 'titel' => str_repeat( 'x', 191 ) ) ) ) );
	}

	public function testMissingTerminIsRejected() {
		self::assertContains( 'error_event_termin_required',
			qt_event_validate( $this->event( array( 'termin' => '' ) ) ) );
	}

	public function testInvalidTerminIsRejected() {
		self::assertContains( 'error_event_termin_invalid',
			qt_event_validate( $this->event( array( 'termin' => '2026-02-30' ) ) ) );
	}

	public function testNegativeCapacityIsRejected() {
		self::assertContains( 'error_event_kapazitaet_invalid',
			qt_event_validate( $this->event( array( 'kapazitaet' => '-1' ) ) ) );
	}

	public function testNonNumericCapacityIsRejected() {
		self::assertContains( 'error_event_kapazitaet_invalid',
			qt_event_validate( $this->event( array( 'kapazitaet' => 'viele' ) ) ) );
	}

	public function testEmptyCapacityIsAllowed() {
		self::assertSame( array(),
			qt_event_validate( $this->event( array( 'kapazitaet' => '' ) ) ) );
	}

	public function testValidTerminAcceptsDateOnlyAndWithTime() {
		self::assertTrue( qt_event_valid_termin( '2026-03-15' ) );
		self::assertTrue( qt_event_valid_termin( '2026-03-15T09:00' ) );
		self::assertTrue( qt_event_valid_termin( '2026-03-15 09:00:00' ) );
		self::assertTrue( qt_event_valid_termin( '2024-02-29' ) );   # leap year
	}

	public function testValidTerminRejectsBadValues() {
		self::assertFalse( qt_event_valid_termin( '2023-02-29' ) );  # not a leap year
		self::assertFalse( qt_event_valid_termin( '15.03.2026' ) );  # wrong format
		self::assertFalse( qt_event_valid_termin( '2026-13-01' ) );
		self::assertFalse( qt_event_valid_termin( '' ) );
	}

	public function testNormaliseTerminFillsTime() {
		self::assertSame( '2026-03-15 00:00:00', qt_event_normalise_termin( '2026-03-15' ) );
		self::assertSame( '2026-03-15 09:00:00', qt_event_normalise_termin( '2026-03-15T09:00' ) );
		self::assertSame( '2026-03-15 09:00:00', qt_event_normalise_termin( '2026-03-15 09:00' ) );
	}
}
