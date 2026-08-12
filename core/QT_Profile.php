<?php
/**
 * QualificationTracker – activity-profile data layer (F2.1).
 *
 * A profile is a named set of measures (e.g. "Hubarbeitsbühnenführer"). This
 * file produces no output and never reads $_POST; qt_profil_validate() is pure
 * and unit-tested, persistence uses the Mantis database API.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Validate profile input. Pure function returning a list of error keys.
 * Uniqueness of the name needs the database and is checked by the caller.
 *
 * @param array $p_data
 * @return array
 */
function qt_profil_validate( array $p_data ) {
	$t_errors = array();

	$t_name = isset( $p_data['name'] ) ? trim( (string)$p_data['name'] ) : '';
	if( $t_name === '' ) {
		$t_errors[] = 'error_profil_name_required';
	} else if( mb_strlen( $t_name ) > 128 ) {
		$t_errors[] = 'error_profil_name_length';
	}

	return $t_errors;
}

/**
 * Fetch a profile by id.
 *
 * @param int $p_id
 * @return array|false
 */
function qt_profil_get( $p_id ) {
	$t_result = db_query( 'SELECT * FROM ' . plugin_table( 'profil' ) . ' WHERE id = ' . db_param(),
		array( (int)$p_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row === false ? false : $t_row;
}

/**
 * Fetch a profile by name, optionally excluding one id (uniqueness check).
 *
 * @param string $p_name
 * @param int    $p_exclude_id
 * @return array|false
 */
function qt_profil_get_by_name( $p_name, $p_exclude_id = 0 ) {
	$t_result = db_query( 'SELECT * FROM ' . plugin_table( 'profil' )
		. ' WHERE name = ' . db_param() . ' AND id <> ' . db_param(),
		array( (string)$p_name, (int)$p_exclude_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row === false ? false : $t_row;
}

/**
 * Load all profiles ordered by name.
 *
 * @param bool $p_include_inactive
 * @return array
 */
function qt_profil_load_all( $p_include_inactive = true ) {
	$t_query = 'SELECT * FROM ' . plugin_table( 'profil' );
	if( !$p_include_inactive ) {
		$t_query .= ' WHERE aktiv = 1';
	}
	$t_query .= ' ORDER BY name';
	$t_result = db_query( $t_query );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Insert a new profile.
 *
 * @param array $p_data
 * @return int New id.
 */
function qt_profil_create( array $p_data ) {
	$t_table = plugin_table( 'profil' );
	$t_now = time();
	db_query(
		'INSERT INTO ' . $t_table . ' ( name, beschreibung, aktiv, date_created, date_modified )'
		. ' VALUES ( ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ' )',
		array(
			trim( (string)$p_data['name'] ),
			qt_profil_beschreibung( $p_data ),
			!empty( $p_data['aktiv'] ) ? 1 : 0,
			$t_now,
			$t_now,
		) );
	return db_insert_id( $t_table );
}

/**
 * Update an existing profile.
 *
 * @param int   $p_id
 * @param array $p_data
 * @return void
 */
function qt_profil_update( $p_id, array $p_data ) {
	db_query(
		'UPDATE ' . plugin_table( 'profil' )
		. ' SET name = ' . db_param() . ', beschreibung = ' . db_param()
		. ', aktiv = ' . db_param() . ', date_modified = ' . db_param() . ' WHERE id = ' . db_param(),
		array(
			trim( (string)$p_data['name'] ),
			qt_profil_beschreibung( $p_data ),
			!empty( $p_data['aktiv'] ) ? 1 : 0,
			time(),
			(int)$p_id,
		) );
}

/**
 * Normalise the (optional) description to a string.
 *
 * @param array $p_data
 * @return string
 */
function qt_profil_beschreibung( array $p_data ) {
	return isset( $p_data['beschreibung'] ) ? trim( (string)$p_data['beschreibung'] ) : '';
}

/**
 * Measure ids assigned to a profile.
 *
 * @param int $p_profil_id
 * @return array List of ints.
 */
function qt_profil_get_massnahmen( $p_profil_id ) {
	$t_result = db_query( 'SELECT massnahme_id FROM ' . plugin_table( 'profil_massnahme' )
		. ' WHERE profil_id = ' . db_param(), array( (int)$p_profil_id ) );
	$t_ids = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_ids[] = (int)$t_row['massnahme_id'];
	}
	return $t_ids;
}

/**
 * Replace the measure set of a profile. Non-positive and duplicate ids are
 * dropped.
 *
 * @param int   $p_profil_id
 * @param array $p_massnahme_ids
 * @return void
 */
function qt_profil_set_massnahmen( $p_profil_id, array $p_massnahme_ids ) {
	$t_id = (int)$p_profil_id;
	$t_table = plugin_table( 'profil_massnahme' );

	db_query( 'DELETE FROM ' . $t_table . ' WHERE profil_id = ' . db_param(), array( $t_id ) );

	$t_seen = array();
	foreach( $p_massnahme_ids as $t_mid ) {
		$t_mid = (int)$t_mid;
		if( $t_mid <= 0 || isset( $t_seen[$t_mid] ) ) {
			continue;
		}
		$t_seen[$t_mid] = true;
		db_query( 'INSERT INTO ' . $t_table . ' ( profil_id, massnahme_id ) VALUES ( '
			. db_param() . ', ' . db_param() . ' )', array( $t_id, $t_mid ) );
	}
}

/**
 * Is the profile assigned to any person? Such profiles must not be
 * hard-deleted; the page offers deactivation instead.
 *
 * @param int $p_id
 * @return bool
 */
function qt_profil_is_referenced( $p_id ) {
	$t_result = db_query( 'SELECT COUNT(*) AS c FROM ' . plugin_table( 'zuordnung' )
		. ' WHERE profil_id = ' . db_param(), array( (int)$p_id ) );
	return (int)db_result( $t_result ) > 0;
}

/**
 * Delete a profile and its measure links.
 *
 * @param int $p_id
 * @return void
 */
function qt_profil_delete( $p_id ) {
	$t_id = (int)$p_id;
	db_query( 'DELETE FROM ' . plugin_table( 'profil_massnahme' ) . ' WHERE profil_id = ' . db_param(),
		array( $t_id ) );
	db_query( 'DELETE FROM ' . plugin_table( 'profil' ) . ' WHERE id = ' . db_param(),
		array( $t_id ) );
}
