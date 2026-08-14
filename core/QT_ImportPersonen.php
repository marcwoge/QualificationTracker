<?php
/**
 * QualificationTracker – personnel master CSV import (F6.1).
 *
 * Imports persons from a semicolon-separated CSV with a header row, including
 * department and supervisor (a MantisBT username resolved to a user id). Existing
 * persons are matched by personnel number and updated (upsert). A dry run
 * validates without writing.
 *
 * qt_import_personen_parse() and qt_import_personen_map_row() are pure and
 * unit-tested; the import itself reads and writes the database.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The accepted CSV column headers (lower-case).
 *
 * @return array
 */
function qt_import_personen_columns() {
	return array( 'personalnummer', 'typ', 'fremdfirma', 'nachname', 'vorname',
		'abteilung', 'eintritt', 'austritt', 'vorgesetzter', 'verkuerztes_intervall_bis', 'aktiv' );
}

/**
 * Parse CSV text with a header row into a list of associative rows keyed by the
 * lower-cased header names. Pure.
 *
 * @param string $p_text
 * @param string $p_delimiter
 * @return array
 */
function qt_import_personen_parse( $p_text, $p_delimiter = ';' ) {
	# Strip a leading UTF-8 BOM (Excel).
	$t_text = preg_replace( '/^\xEF\xBB\xBF/', '', (string)$p_text );
	$t_lines = preg_split( '/\r\n|\r|\n/', $t_text );

	$t_header = null;
	$t_rows = array();
	foreach( $t_lines as $t_line ) {
		if( trim( $t_line ) === '' ) {
			continue;
		}
		$t_fields = str_getcsv( $t_line, $p_delimiter );
		if( $t_header === null ) {
			$t_header = array();
			foreach( $t_fields as $t_h ) {
				$t_header[] = strtolower( trim( $t_h ) );
			}
			continue;
		}
		$t_row = array();
		foreach( $t_header as $t_i => $t_key ) {
			$t_row[$t_key] = isset( $t_fields[$t_i] ) ? $t_fields[$t_i] : '';
		}
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Interpret a boolean-ish CSV value. Empty defaults to the given default. Pure.
 *
 * @param string $p_value
 * @param int    $p_default
 * @return int 0 or 1
 */
function qt_import_personen_bool( $p_value, $p_default = 1 ) {
	$t = strtolower( trim( (string)$p_value ) );
	if( $t === '' ) {
		return $p_default ? 1 : 0;
	}
	return in_array( $t, array( '1', 'ja', 'yes', 'y', 'true', 'wahr', 'x', 'aktiv' ), true ) ? 1 : 0;
}

/**
 * Map a parsed CSV row to a person data array (supervisor still by username in
 * 'vorgesetzter'; the id is resolved during the import). Pure.
 *
 * @param array $p_row
 * @return array
 */
function qt_import_personen_map_row( array $p_row ) {
	$t_get = function( $p_key ) use ( $p_row ) {
		return isset( $p_row[$p_key] ) ? trim( (string)$p_row[$p_key] ) : '';
	};
	$t_typ = strtolower( $t_get( 'typ' ) );
	if( $t_typ === '' ) {
		$t_typ = 'intern';
	}

	return array(
		'personalnummer'            => $t_get( 'personalnummer' ),
		'typ'                       => $t_typ,
		'fremdfirma'                => $t_get( 'fremdfirma' ),
		'nachname'                  => $t_get( 'nachname' ),
		'vorname'                   => $t_get( 'vorname' ),
		'abteilung'                 => $t_get( 'abteilung' ),
		'eintritt'                  => $t_get( 'eintritt' ),
		'austritt'                  => $t_get( 'austritt' ),
		'verkuerztes_intervall_bis' => $t_get( 'verkuerztes_intervall_bis' ),
		'vorgesetzter'              => $t_get( 'vorgesetzter' ),
		'aktiv'                     => qt_import_personen_bool( $t_get( 'aktiv' ), 1 ),
	);
}

/**
 * Import mapped person rows (upsert by personnel number). Resolves the
 * supervisor username to a user id and validates each row before writing.
 *
 * @param array $p_rows    Mapped rows from qt_import_personen_map_row().
 * @param bool  $p_dry_run When true, validate only – write nothing.
 * @return array Summary: created, updated, errors (list of zeile, name, fehler[]).
 */
function qt_import_personen_run( array $p_rows, $p_dry_run = false ) {
	$t_summary = array( 'created' => 0, 'updated' => 0, 'errors' => array() );

	foreach( $p_rows as $t_i => $t_data ) {
		$t_zeile = $t_i + 2;   # +1 header, +1 to 1-based
		$t_name = trim( $t_data['nachname'] . ', ' . $t_data['vorname'], ', ' );
		$t_errors = array();

		# Resolve the supervisor username to a user id.
		$t_vorgesetzter = isset( $t_data['vorgesetzter'] ) ? (string)$t_data['vorgesetzter'] : '';
		$t_uid = 0;
		if( $t_vorgesetzter !== '' ) {
			$t_uid = user_get_id_by_name( $t_vorgesetzter );
			if( $t_uid === false || $t_uid <= 0 ) {
				$t_errors[] = 'error_import_vorgesetzter_unknown';
				$t_uid = 0;
			}
		}
		$t_data['vorgesetzter_user_id'] = (int)$t_uid;

		$t_errors = array_merge( $t_errors, qt_person_validate( $t_data ) );
		if( !empty( $t_errors ) ) {
			$t_summary['errors'][] = array( 'zeile' => $t_zeile, 'name' => $t_name, 'fehler' => $t_errors );
			continue;
		}

		$t_existing = ( $t_data['personalnummer'] !== '' )
			? qt_person_get_by_personalnummer( $t_data['personalnummer'] ) : false;

		if( !$p_dry_run ) {
			if( $t_existing !== false ) {
				qt_person_update( (int)$t_existing['id'], $t_data );
			} else {
				qt_person_create( $t_data );
			}
		}
		if( $t_existing !== false ) {
			$t_summary['updated']++;
		} else {
			$t_summary['created']++;
		}
	}

	return $t_summary;
}
