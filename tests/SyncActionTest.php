<?php
/**
 * Unit tests for the pure profile-change decision (F2.7).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class SyncActionTest extends TestCase {

	public function testValidProofIsKept() {
		self::assertSame( 'behalten', qt_sync_obsolete_action( 'gueltig' ) );
	}

	public function testNonValidProofsAreCancelled() {
		foreach( array( 'offen', 'geplant', 'durchgefuehrt', 'abgelaufen' ) as $t_status ) {
			self::assertSame( 'entfallen', qt_sync_obsolete_action( $t_status ), $t_status );
		}
	}
}
