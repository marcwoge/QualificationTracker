<?php
/**
 * QualificationTracker – person register data layer (F1.4).
 *
 * Persons are kept independently of MantisBT user accounts (most shop-floor
 * staff have no login). Produces no output, never reads $_POST.
 * qt_person_validate() is pure and unit-tested; persistence uses the Mantis
 * database API (db_query + db_param()).
 *
 * Data-model decisions (see schema()): E1 surrogate id leads, personalnummer is
 * nullable and unique only where present; E2 external staff via the `typ`
 * discriminator + `fremdfirma`; E5 youth-protection short interval via the
 * single date `verkuerztes_intervall_bis` (no date of birth is stored).
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The person types (Personentypen).
 *
 * @return array
 */
function qt_person_types() {
	return array( 'intern', 'leiharbeit', 'fremdfirma' );
}

/**
 * Is the string a valid ISO date (YYYY-MM-DD)? The empty string is treated as
 * "no date" and is considered valid (the caller decides whether it is required).
 * Pure function.
 *
 * @param string $p_value
 * @return bool
 */
function qt_person_valid_date( $p_value ) {
	$t_value = trim( (string)$p_value );
	if( $t_value === '' ) {
		return true;
	}
	if( !preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $t_value, $t_m ) ) {
		return false;
	}
	return checkdate( (int)$t_m[2], (int)$t_m[3], (int)$t_m[1] );
}

/**
 * Validate person input. Pure function: returns a list of error message keys,
 * empty when valid. Uniqueness of the personnel number needs the database and
 * is checked separately by the caller.
 *
 * @param array $p_data
 * @return array
 */
function qt_person_validate( array $p_data ) {
	$t_errors = array();

	$t_nachname = isset( $p_data['nachname'] ) ? trim( (string)$p_data['nachname'] ) : '';
	if( $t_nachname === '' ) {
		$t_errors[] = 'error_nachname_required';
	}

	$t_typ = isset( $p_data['typ'] ) ? (string)$p_data['typ'] : '';
	if( !in_array( $t_typ, qt_person_types(), true ) ) {
		$t_errors[] = 'error_person_typ_invalid';
	} else if( $t_typ !== 'intern' ) {
		# External staff must name their employer.
		$t_firma = isset( $p_data['fremdfirma'] ) ? trim( (string)$p_data['fremdfirma'] ) : '';
		if( $t_firma === '' ) {
			$t_errors[] = 'error_fremdfirma_required';
		}
	}

	$t_pnr = isset( $p_data['personalnummer'] ) ? trim( (string)$p_data['personalnummer'] ) : '';
	if( mb_strlen( $t_pnr ) > 64 ) {
		$t_errors[] = 'error_personalnummer_length';
	}

	if( !qt_person_valid_date( $p_data['eintritt'] ?? '' ) ) {
		$t_errors[] = 'error_eintritt_invalid';
	}
	if( !qt_person_valid_date( $p_data['austritt'] ?? '' ) ) {
		$t_errors[] = 'error_austritt_invalid';
	}
	if( !qt_person_valid_date( $p_data['verkuerztes_intervall_bis'] ?? '' ) ) {
		$t_errors[] = 'error_jugendschutz_invalid';
	}

	# Exit must not precede entry (only when both are present and valid).
	$t_eintritt = trim( (string)( $p_data['eintritt'] ?? '' ) );
	$t_austritt = trim( (string)( $p_data['austritt'] ?? '' ) );
	if( $t_eintritt !== '' && $t_austritt !== ''
		&& qt_person_valid_date( $t_eintritt ) && qt_person_valid_date( $t_austritt )
		&& $t_austritt < $t_eintritt ) {
		$t_errors[] = 'error_austritt_before_eintritt';
	}

	return $t_errors;
}

/**
 * Column list for INSERT/UPDATE, matching qt_person_bind_values().
 *
 * @return array
 */
function qt_person_columns() {
	return array(
		'personalnummer', 'typ', 'fremdfirma', 'nachname', 'vorname', 'abteilung',
		'eintritt', 'austritt', 'vorgesetzter_user_id', 'verkuerztes_intervall_bis', 'aktiv',
	);
}

/**
 * Normalise submitted data to the bound value list. Empty optional fields
 * become null.
 *
 * @param array $p_data
 * @return array
 */
function qt_person_bind_values( array $p_data ) {
	$t_str = function( $p_key ) use ( $p_data ) {
		$t_v = isset( $p_data[$p_key] ) ? trim( (string)$p_data[$p_key] ) : '';
		return $t_v === '' ? null : $t_v;
	};

	$t_sup = isset( $p_data['vorgesetzter_user_id'] ) ? (int)$p_data['vorgesetzter_user_id'] : 0;

	return array(
		$t_str( 'personalnummer' ),
		(string)$p_data['typ'],
		$t_str( 'fremdfirma' ),
		trim( (string)$p_data['nachname'] ),
		isset( $p_data['vorname'] ) ? trim( (string)$p_data['vorname'] ) : '',
		isset( $p_data['abteilung'] ) ? trim( (string)$p_data['abteilung'] ) : '',
		$t_str( 'eintritt' ),
		$t_str( 'austritt' ),
		$t_sup > 0 ? $t_sup : null,
		$t_str( 'verkuerztes_intervall_bis' ),
		!empty( $p_data['aktiv'] ) ? 1 : 0,
	);
}

/**
 * Fetch a person by id.
 *
 * @param int $p_id
 * @return array|false
 */
function qt_person_get( $p_id ) {
	$t_result = db_query( 'SELECT * FROM ' . plugin_table( 'person' ) . ' WHERE id = ' . db_param(),
		array( (int)$p_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row === false ? false : $t_row;
}

/**
 * Fetch a person by personnel number, optionally excluding one id.
 *
 * @param string $p_personalnummer
 * @param int    $p_exclude_id
 * @return array|false
 */
function qt_person_get_by_personalnummer( $p_personalnummer, $p_exclude_id = 0 ) {
	$t_result = db_query( 'SELECT * FROM ' . plugin_table( 'person' )
		. ' WHERE personalnummer = ' . db_param() . ' AND id <> ' . db_param(),
		array( (string)$p_personalnummer, (int)$p_exclude_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row === false ? false : $t_row;
}

/**
 * Load persons, optionally filtered by department, ordered by name.
 *
 * @param string $p_abteilung Empty for all departments.
 * @return array
 */
function qt_person_load_all( $p_abteilung = '' ) {
	$t_query = 'SELECT * FROM ' . plugin_table( 'person' );
	$t_params = array();
	if( $p_abteilung !== '' ) {
		$t_query .= ' WHERE abteilung = ' . db_param();
		$t_params[] = (string)$p_abteilung;
	}
	$t_query .= ' ORDER BY nachname, vorname';
	$t_result = db_query( $t_query, $t_params );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Distinct, non-empty department names, ordered.
 *
 * @return array
 */
function qt_person_distinct_abteilungen() {
	$t_result = db_query( 'SELECT DISTINCT abteilung FROM ' . plugin_table( 'person' )
		. " WHERE abteilung <> '' ORDER BY abteilung" );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row['abteilung'];
	}
	return $t_rows;
}

/**
 * Insert a new person.
 *
 * @param array $p_data
 * @return int New id.
 */
function qt_person_create( array $p_data ) {
	$t_table = plugin_table( 'person' );
	$t_columns = qt_person_columns();
	$t_values = qt_person_bind_values( $p_data );

	$t_now = time();
	$t_columns[] = 'date_created';  $t_values[] = $t_now;
	$t_columns[] = 'date_modified'; $t_values[] = $t_now;

	$t_placeholders = implode( ', ', array_fill( 0, count( $t_values ), db_param() ) );
	db_query( 'INSERT INTO ' . $t_table . ' ( ' . implode( ', ', $t_columns ) . ' ) VALUES ( '
		. $t_placeholders . ' )', $t_values );

	return db_insert_id( $t_table );
}

/**
 * Update an existing person.
 *
 * @param int   $p_id
 * @param array $p_data
 * @return void
 */
function qt_person_update( $p_id, array $p_data ) {
	$t_table = plugin_table( 'person' );
	$t_columns = qt_person_columns();
	$t_values = qt_person_bind_values( $p_data );

	$t_set = array();
	foreach( $t_columns as $t_column ) {
		$t_set[] = $t_column . ' = ' . db_param();
	}
	$t_set[] = 'date_modified = ' . db_param();
	$t_values[] = time();

	$t_values[] = (int)$p_id;
	db_query( 'UPDATE ' . $t_table . ' SET ' . implode( ', ', $t_set ) . ' WHERE id = ' . db_param(),
		$t_values );
}

/**
 * Is the person referenced by a profile assignment? Such persons must not be
 * hard-deleted; the page offers deactivation instead.
 *
 * @param int $p_id
 * @return bool
 */
function qt_person_is_referenced( $p_id ) {
	$t_result = db_query( 'SELECT COUNT(*) AS c FROM ' . plugin_table( 'zuordnung' )
		. ' WHERE person_id = ' . db_param(), array( (int)$p_id ) );
	return (int)db_result( $t_result ) > 0;
}

/**
 * Delete a person by id.
 *
 * @param int $p_id
 * @return void
 */
function qt_person_delete( $p_id ) {
	db_query( 'DELETE FROM ' . plugin_table( 'person' ) . ' WHERE id = ' . db_param(),
		array( (int)$p_id ) );
}
