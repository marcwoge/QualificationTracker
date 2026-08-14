<?php
/**
 * Unit tests for qt_ruhen_should_rest() (F5.4).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class RuhenTest extends TestCase {

	public function testRestsWhenSafetyPrereqInvalid() {
		self::assertTrue( qt_ruhen_should_rest( array(
			array( 'sicherheitsrelevant' => true, 'valid' => false ),
		) ) );
	}

	public function testDoesNotRestWhenSafetyPrereqValid() {
		self::assertFalse( qt_ruhen_should_rest( array(
			array( 'sicherheitsrelevant' => true, 'valid' => true ),
		) ) );
	}

	public function testInvalidNonSafetyPrereqDoesNotSuspend() {
		self::assertFalse( qt_ruhen_should_rest( array(
			array( 'sicherheitsrelevant' => false, 'valid' => false ),
		) ) );
	}

	public function testAnyInvalidSafetyPrereqSuspends() {
		self::assertTrue( qt_ruhen_should_rest( array(
			array( 'sicherheitsrelevant' => false, 'valid' => false ),
			array( 'sicherheitsrelevant' => true,  'valid' => true ),
			array( 'sicherheitsrelevant' => true,  'valid' => false ),
		) ) );
	}

	public function testNoPrerequisitesNeverSuspends() {
		self::assertFalse( qt_ruhen_should_rest( array() ) );
	}
}
