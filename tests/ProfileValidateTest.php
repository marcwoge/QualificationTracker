<?php
/**
 * Unit tests for qt_profil_validate() (F2.1).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class ProfileValidateTest extends TestCase {

	public function testValidProfileHasNoErrors() {
		self::assertSame( array(), qt_profil_validate( array( 'name' => 'Hubarbeitsbühnenführer' ) ) );
	}

	public function testMissingNameIsRejected() {
		self::assertContains( 'error_profil_name_required',
			qt_profil_validate( array( 'name' => '   ' ) ) );
		self::assertContains( 'error_profil_name_required',
			qt_profil_validate( array() ) );
	}

	public function testOverlongNameIsRejected() {
		self::assertContains( 'error_profil_name_length',
			qt_profil_validate( array( 'name' => str_repeat( 'A', 129 ) ) ) );
	}

	public function testNameOfExactly128CharsIsAccepted() {
		self::assertSame( array(),
			qt_profil_validate( array( 'name' => str_repeat( 'A', 128 ) ) ) );
	}
}
