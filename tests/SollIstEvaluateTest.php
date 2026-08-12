<?php
/**
 * Unit tests for the pure target/actual evaluation (F2.5).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class SollIstEvaluateTest extends TestCase {

	private function proof( $p_status, $p_bis = null ) {
		return array( 'status' => $p_status, 'gueltig_bis' => $p_bis );
	}

	/* ---- qt_sollist_is_valid ------------------------------------------ */

	public function testValidWhenGueltigWithoutEndDate() {
		self::assertTrue( qt_sollist_is_valid( $this->proof( 'gueltig', null ), '2026-08-12' ) );
	}

	public function testValidWhenGueltigAndNotYetExpired() {
		self::assertTrue( qt_sollist_is_valid( $this->proof( 'gueltig', '2027-01-01' ), '2026-08-12' ) );
	}

	public function testInvalidWhenExpired() {
		self::assertFalse( qt_sollist_is_valid( $this->proof( 'gueltig', '2026-01-01' ), '2026-08-12' ) );
	}

	public function testInvalidWhenNotGueltigStatus() {
		self::assertFalse( qt_sollist_is_valid( $this->proof( 'offen', null ), '2026-08-12' ) );
	}

	/* ---- qt_sollist_evaluate ------------------------------------------ */

	public function testNoProofsMeansFehlt() {
		self::assertSame( 'fehlt', qt_sollist_evaluate( array(), '2026-08-12' ) );
	}

	public function testOpenProofMeansOffen() {
		self::assertSame( 'offen', qt_sollist_evaluate( array( $this->proof( 'offen' ) ), '2026-08-12' ) );
		self::assertSame( 'offen', qt_sollist_evaluate( array( $this->proof( 'durchgefuehrt' ) ), '2026-08-12' ) );
	}

	public function testExpiredProofMeansAbgelaufen() {
		self::assertSame( 'abgelaufen', qt_sollist_evaluate( array( $this->proof( 'abgelaufen' ) ), '2026-08-12' ) );
		self::assertSame( 'abgelaufen', qt_sollist_evaluate( array( $this->proof( 'gueltig', '2026-01-01' ) ), '2026-08-12' ) );
	}

	public function testValidProofMeansNoGap() {
		self::assertSame( '', qt_sollist_evaluate( array( $this->proof( 'gueltig', '2027-01-01' ) ), '2026-08-12' ) );
	}

	public function testValidWinsOverOpen() {
		$t_rows = array( $this->proof( 'offen' ), $this->proof( 'gueltig', '2027-01-01' ) );
		self::assertSame( '', qt_sollist_evaluate( $t_rows, '2026-08-12' ) );
	}

	public function testCancelledProofsAreIgnored() {
		self::assertSame( 'fehlt', qt_sollist_evaluate( array( $this->proof( 'entfallen' ) ), '2026-08-12' ) );
	}
}
