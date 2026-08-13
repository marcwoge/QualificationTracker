<?php
/**
 * Unit tests for qt_expiry_is_expired() (F5.1).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class ExpiryTest extends TestCase {

	private const TODAY = '2026-08-13';

	private function nachweis( array $p ) {
		return array_merge( array( 'status' => 'gueltig', 'gueltig_bis' => null, 'bug_id' => 0 ), $p );
	}

	public function testValidProofPastEndDateIsExpired() {
		self::assertTrue( qt_expiry_is_expired(
			$this->nachweis( array( 'gueltig_bis' => '2026-08-12' ) ), self::TODAY ) );
	}

	public function testValidProofEndingTodayIsNotYetExpired() {
		self::assertFalse( qt_expiry_is_expired(
			$this->nachweis( array( 'gueltig_bis' => self::TODAY ) ), self::TODAY ) );
	}

	public function testValidProofInFutureIsNotExpired() {
		self::assertFalse( qt_expiry_is_expired(
			$this->nachweis( array( 'gueltig_bis' => '2027-01-01' ) ), self::TODAY ) );
	}

	public function testPermanentProofNeverExpires() {
		self::assertFalse( qt_expiry_is_expired( $this->nachweis( array( 'gueltig_bis' => null ) ), self::TODAY ) );
		self::assertFalse( qt_expiry_is_expired( $this->nachweis( array( 'gueltig_bis' => '' ) ), self::TODAY ) );
	}

	public function testNonValidStatusesDoNotExpire() {
		foreach( array( 'offen', 'geplant', 'durchgefuehrt', 'abgelaufen', 'entfallen' ) as $t_status ) {
			self::assertFalse( qt_expiry_is_expired(
				$this->nachweis( array( 'status' => $t_status, 'gueltig_bis' => '2020-01-01' ) ), self::TODAY ),
				$t_status );
		}
	}
}
