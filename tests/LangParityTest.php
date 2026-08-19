<?php
/**
 * Language-file completeness guard (F8.3).
 *
 * Parses the shipped German and English string files and asserts they stay in
 * lock-step: identical key sets, no duplicate definitions, no empty values. This
 * catches the common drift of adding a key to one language and forgetting the
 * other.
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class LangParityTest extends TestCase {

	/**
	 * Parse a plugin language file into key => value, and collect any keys that
	 * appear more than once.
	 *
	 * @param string $p_path
	 * @param array  $p_duplicates Out: duplicate keys.
	 * @return array key => value
	 */
	private function parse( $p_path, array &$p_duplicates ) {
		self::assertFileExists( $p_path );
		$t_map = array();
		$p_duplicates = array();
		foreach( file( $p_path, FILE_IGNORE_NEW_LINES ) as $t_line ) {
			if( !preg_match( '/^\s*\$s_plugin_QualificationTracker_([A-Za-z0-9_]+)\s*=\s*\'(.*)\'\s*;\s*$/', $t_line, $t_m ) ) {
				continue;
			}
			$t_key = $t_m[1];
			if( array_key_exists( $t_key, $t_map ) ) {
				$p_duplicates[] = $t_key;
			}
			$t_map[$t_key] = $t_m[2];
		}
		return $t_map;
	}

	private function german() {
		return dirname( __DIR__ ) . '/lang/strings_german.txt';
	}

	private function english() {
		return dirname( __DIR__ ) . '/lang/strings_english.txt';
	}

	public function testBothFilesDefineKeys() {
		$t_dup = array();
		$t_de = $this->parse( $this->german(), $t_dup );
		$t_en = $this->parse( $this->english(), $t_dup );
		self::assertGreaterThan( 100, count( $t_de ) );
		self::assertGreaterThan( 100, count( $t_en ) );
	}

	public function testNoKeyMissingInEnglish() {
		$t_dup = array();
		$t_de = $this->parse( $this->german(), $t_dup );
		$t_en = $this->parse( $this->english(), $t_dup );
		$t_missing = array_diff( array_keys( $t_de ), array_keys( $t_en ) );
		self::assertSame( array(), array_values( $t_missing ),
			'Keys in German but missing in English: ' . implode( ', ', $t_missing ) );
	}

	public function testNoKeyMissingInGerman() {
		$t_dup = array();
		$t_de = $this->parse( $this->german(), $t_dup );
		$t_en = $this->parse( $this->english(), $t_dup );
		$t_missing = array_diff( array_keys( $t_en ), array_keys( $t_de ) );
		self::assertSame( array(), array_values( $t_missing ),
			'Keys in English but missing in German: ' . implode( ', ', $t_missing ) );
	}

	public function testNoDuplicateDefinitions() {
		$t_dup_de = array();
		$this->parse( $this->german(), $t_dup_de );
		$t_dup_en = array();
		$this->parse( $this->english(), $t_dup_en );
		self::assertSame( array(), $t_dup_de, 'Duplicate German keys: ' . implode( ', ', $t_dup_de ) );
		self::assertSame( array(), $t_dup_en, 'Duplicate English keys: ' . implode( ', ', $t_dup_en ) );
	}

	public function testNoEmptyValues() {
		foreach( array( $this->german(), $this->english() ) as $t_path ) {
			$t_dup = array();
			$t_empty = array();
			foreach( $this->parse( $t_path, $t_dup ) as $t_key => $t_value ) {
				if( trim( $t_value ) === '' ) {
					$t_empty[] = $t_key;
				}
			}
			self::assertSame( array(), $t_empty,
				basename( $t_path ) . ' has empty values: ' . implode( ', ', $t_empty ) );
		}
	}
}
