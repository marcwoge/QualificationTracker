<?php
/**
 * Unit tests for the pure participant helpers (F3.2):
 * qt_teilnehmer_capacity_state(), qt_teilnehmer_status_valid() and
 * qt_teilnehmer_art_matches().
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class ParticipantCapacityTest extends TestCase {

	public function testUnsetCapacityMeansUnlimited() {
		self::assertSame( 'unbegrenzt', qt_teilnehmer_capacity_state( null, 0 ) );
		self::assertSame( 'unbegrenzt', qt_teilnehmer_capacity_state( 0, 50 ) );
	}

	public function testRoomLeftIsFree() {
		self::assertSame( 'frei', qt_teilnehmer_capacity_state( 10, 0 ) );
		self::assertSame( 'frei', qt_teilnehmer_capacity_state( 10, 9 ) );
	}

	public function testExactlyAtCapacityIsFull() {
		self::assertSame( 'voll', qt_teilnehmer_capacity_state( 10, 10 ) );
	}

	public function testBeyondCapacityIsOverbooked() {
		self::assertSame( 'ueberbucht', qt_teilnehmer_capacity_state( 10, 11 ) );
		self::assertSame( 'ueberbucht', qt_teilnehmer_capacity_state( 1, 2 ) );
	}

	public function testCapacityStateCastsStringInputs() {
		self::assertSame( 'voll', qt_teilnehmer_capacity_state( '5', '5' ) );
		self::assertSame( 'ueberbucht', qt_teilnehmer_capacity_state( '5', '6' ) );
	}

	public function testKnownStatusesAreValid() {
		foreach( array( 'eingeplant', 'teilgenommen', 'abwesend' ) as $t_status ) {
			self::assertTrue( qt_teilnehmer_status_valid( $t_status ), $t_status );
		}
	}

	public function testUnknownStatusIsRejected() {
		self::assertFalse( qt_teilnehmer_status_valid( 'anwesend' ) );
		self::assertFalse( qt_teilnehmer_status_valid( '' ) );
	}

	public function testArtMatchesEmptyFilterAcceptsAll() {
		self::assertTrue( qt_teilnehmer_art_matches( 'fehlt', '' ) );
		self::assertTrue( qt_teilnehmer_art_matches( 'abgelaufen', '' ) );
	}

	public function testArtMatchesSpecificFilter() {
		self::assertTrue( qt_teilnehmer_art_matches( 'offen', 'offen' ) );
		self::assertFalse( qt_teilnehmer_art_matches( 'offen', 'fehlt' ) );
	}
}
