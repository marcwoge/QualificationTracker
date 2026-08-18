<?php
/**
 * Unit tests for the pure occupational-health helpers (F7.2):
 * qt_vorsorge_project() and qt_vorsorge_field_allowed().
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class VorsorgeTest extends TestCase {

	public function testVoRoutesToVorsorgeProject() {
		self::assertSame( 9, qt_vorsorge_project( 'VO', 7, 9 ) );
	}

	public function testVoFallsBackToTargetWhenNoVorsorgeProject() {
		self::assertSame( 7, qt_vorsorge_project( 'VO', 7, 0 ) );
	}

	public function testNonVoAlwaysUsesTargetProject() {
		self::assertSame( 7, qt_vorsorge_project( 'UW', 7, 9 ) );
		self::assertSame( 7, qt_vorsorge_project( 'QB', 7, 9 ) );
	}

	public function testNonVoAllowsEveryField() {
		self::assertTrue( qt_vorsorge_field_allowed( 'rechtsgrundlage', 'UW' ) );
		self::assertTrue( qt_vorsorge_field_allowed( 'intervall_monate', 'BE' ) );
	}

	public function testVoAllowsOnlyMinimisedFields() {
		self::assertTrue( qt_vorsorge_field_allowed( 'mitarbeiter', 'VO' ) );
		self::assertTrue( qt_vorsorge_field_allowed( 'soll_termin', 'VO' ) );
		self::assertTrue( qt_vorsorge_field_allowed( 'gueltig_bis', 'VO' ) );
	}

	public function testVoDropsProgramMetadataFields() {
		self::assertFalse( qt_vorsorge_field_allowed( 'rechtsgrundlage', 'VO' ) );
		self::assertFalse( qt_vorsorge_field_allowed( 'intervall_monate', 'VO' ) );
		self::assertFalse( qt_vorsorge_field_allowed( 'faelligkeitsmodus', 'VO' ) );
	}
}
