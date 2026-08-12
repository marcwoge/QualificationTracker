<?php
/**
 * QualificationTracker – profile assignment data layer (F2.2).
 *
 * Assigns persons to activity profiles (n:m) with an optional validity period,
 * so role changes stay historically traceable. Produces no output, never reads
 * $_POST; qt_zuordnung_validate() is pure and unit-tested.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Validate an assignment. Pure function returning a list of error keys.
 * Existence of the person/profile and duplicate checks need the database and
 * are done by the caller.
 *
 * @param array $p_data
 * @return array
 */
function qt_zuordnung_validate( array $p_data ) {
	$t_errors = array();

	if( (int)( $p_data['person_id'] ?? 0 ) <= 0 ) {
		$t_errors[] = 'error_zuordnung_person_required';
	}
	if( (int)( $p_data['profil_id'] ?? 0 ) <= 0 ) {
		$t_errors[] = 'error_zuordnung_profil_required';
	}

	if( !qt_person_valid_date( $p_data['gueltig_ab'] ?? '' ) ) {
		$t_errors[] = 'error_gueltig_ab_invalid';
	}
	if( !qt_person_valid_date( $p_data['gueltig_bis'] ?? '' ) ) {
		$t_errors[] = 'error_gueltig_bis_invalid';
	}

	$t_ab  = trim( (string)( $p_data['gueltig_ab'] ?? '' ) );
	$t_bis = trim( (string)( $p_data['gueltig_bis'] ?? '' ) );
	if( $t_ab !== '' && $t_bis !== ''
		&& qt_person_valid_date( $t_ab ) && qt_person_valid_date( $t_bis )
		&& $t_bis < $t_ab ) {
		$t_errors[] = 'error_gueltig_bis_before_ab';
	}

	return $t_errors;
}

/**
 * Fetch an assignment by id.
 *
 * @param int $p_id
 * @return array|false
 */
function qt_zuordnung_get( $p_id ) {
	$t_result = db_query( 'SELECT * FROM ' . plugin_table( 'zuordnung' ) . ' WHERE id = ' . db_param(),
		array( (int)$p_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row === false ? false : $t_row;
}

/**
 * Does an open (no end date) assignment of the same person to the same profile
 * already exist? Used to prevent an accidental duplicate while still allowing
 * historical, closed assignments.
 *
 * @param int $p_person_id
 * @param int $p_profil_id
 * @param int $p_exclude_id
 * @return bool
 */
function qt_zuordnung_open_exists( $p_person_id, $p_profil_id, $p_exclude_id = 0 ) {
	$t_result = db_query(
		'SELECT COUNT(*) AS c FROM ' . plugin_table( 'zuordnung' )
		. ' WHERE person_id = ' . db_param() . ' AND profil_id = ' . db_param()
		. ' AND id <> ' . db_param() . ' AND ( gueltig_bis IS NULL )',
		array( (int)$p_person_id, (int)$p_profil_id, (int)$p_exclude_id ) );
	return (int)db_result( $t_result ) > 0;
}

/**
 * Load assignments with person and profile names, optionally filtered by person.
 *
 * @param int $p_person_id 0 for all persons.
 * @return array
 */
function qt_zuordnung_load_all( $p_person_id = 0 ) {
	$t_query = 'SELECT z.*, p.nachname, p.vorname, p.personalnummer, pr.name AS profil_name'
		. ' FROM ' . plugin_table( 'zuordnung' ) . ' z'
		. ' LEFT JOIN ' . plugin_table( 'person' ) . ' p ON p.id = z.person_id'
		. ' LEFT JOIN ' . plugin_table( 'profil' ) . ' pr ON pr.id = z.profil_id';
	$t_params = array();
	if( (int)$p_person_id > 0 ) {
		$t_query .= ' WHERE z.person_id = ' . db_param();
		$t_params[] = (int)$p_person_id;
	}
	$t_query .= ' ORDER BY p.nachname, p.vorname, pr.name';

	$t_result = db_query( $t_query, $t_params );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Insert a new assignment.
 *
 * @param array $p_data
 * @return int New id.
 */
function qt_zuordnung_create( array $p_data ) {
	$t_table = plugin_table( 'zuordnung' );
	$t_now = time();
	db_query(
		'INSERT INTO ' . $t_table
		. ' ( person_id, profil_id, gueltig_ab, gueltig_bis, date_created, date_modified )'
		. ' VALUES ( ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param()
		. ', ' . db_param() . ', ' . db_param() . ' )',
		array(
			(int)$p_data['person_id'],
			(int)$p_data['profil_id'],
			qt_zuordnung_date( $p_data, 'gueltig_ab' ),
			qt_zuordnung_date( $p_data, 'gueltig_bis' ),
			$t_now,
			$t_now,
		) );
	return db_insert_id( $t_table );
}

/**
 * Update an assignment.
 *
 * @param int   $p_id
 * @param array $p_data
 * @return void
 */
function qt_zuordnung_update( $p_id, array $p_data ) {
	db_query(
		'UPDATE ' . plugin_table( 'zuordnung' )
		. ' SET person_id = ' . db_param() . ', profil_id = ' . db_param()
		. ', gueltig_ab = ' . db_param() . ', gueltig_bis = ' . db_param()
		. ', date_modified = ' . db_param() . ' WHERE id = ' . db_param(),
		array(
			(int)$p_data['person_id'],
			(int)$p_data['profil_id'],
			qt_zuordnung_date( $p_data, 'gueltig_ab' ),
			qt_zuordnung_date( $p_data, 'gueltig_bis' ),
			time(),
			(int)$p_id,
		) );
}

/**
 * Normalise an optional date field to a string or null.
 *
 * @param array  $p_data
 * @param string $p_key
 * @return string|null
 */
function qt_zuordnung_date( array $p_data, $p_key ) {
	$t_value = isset( $p_data[$p_key] ) ? trim( (string)$p_data[$p_key] ) : '';
	return $t_value === '' ? null : $t_value;
}

/**
 * Delete an assignment.
 *
 * @param int $p_id
 * @return void
 */
function qt_zuordnung_delete( $p_id ) {
	db_query( 'DELETE FROM ' . plugin_table( 'zuordnung' ) . ' WHERE id = ' . db_param(),
		array( (int)$p_id ) );
}
