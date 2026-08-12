<?php
/**
 * QualificationTracker – measure catalogue data layer (F1.2).
 *
 * CRUD and validation for qt_massnahme. This file produces no output and never
 * reads $_POST; pages pass already-extracted values in. qt_massnahme_validate()
 * is pure (no database, no Mantis session) and is unit-tested; the persistence
 * helpers use the Mantis database API exclusively (db_query + db_param()).
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The five measure types (Maßnahmentypen).
 *
 * @return array
 */
function qt_catalog_types() {
	return array( 'UW', 'QU', 'QB', 'BE', 'VO' );
}

/**
 * The four due-date modes (Fälligkeitsmodi).
 *
 * @return array
 */
function qt_catalog_modes() {
	return array( 'rollierend', 'kalenderjahr', 'stichmonat', 'extern' );
}

/**
 * Validate measure input. Pure function: returns a list of error message keys
 * (resolved via plugin_lang_get on the page), empty when the data is valid.
 *
 * Uniqueness of the key is NOT checked here (it needs the database); the caller
 * checks it separately with qt_massnahme_get_by_schluessel().
 *
 * @param array $p_data Associative array with the submitted fields.
 * @return array List of error keys.
 */
function qt_massnahme_validate( array $p_data ) {
	$t_errors = array();

	$t_schluessel = isset( $p_data['schluessel'] ) ? trim( (string)$p_data['schluessel'] ) : '';
	if( $t_schluessel === '' ) {
		$t_errors[] = 'error_schluessel_required';
	} else if( mb_strlen( $t_schluessel ) > 64 ) {
		$t_errors[] = 'error_schluessel_length';
	}

	$t_bezeichnung = isset( $p_data['bezeichnung'] ) ? trim( (string)$p_data['bezeichnung'] ) : '';
	if( $t_bezeichnung === '' ) {
		$t_errors[] = 'error_bezeichnung_required';
	}

	$t_typ = isset( $p_data['typ'] ) ? (string)$p_data['typ'] : '';
	if( !in_array( $t_typ, qt_catalog_types(), true ) ) {
		$t_errors[] = 'error_typ_invalid';
	}

	$t_modus = isset( $p_data['faelligkeitsmodus'] ) ? (string)$p_data['faelligkeitsmodus'] : '';
	if( !in_array( $t_modus, qt_catalog_modes(), true ) ) {
		$t_errors[] = 'error_modus_invalid';
	} else {
		# Sub-rules only make sense for a valid mode.
		if( $t_modus === 'stichmonat' ) {
			$t_sm = isset( $p_data['stichmonat'] ) ? (int)$p_data['stichmonat'] : 0;
			if( $t_sm < 1 || $t_sm > 12 ) {
				$t_errors[] = 'error_stichmonat_invalid';
			}
		}
		# Every computing mode needs an interval; only 'extern' takes its date
		# from the document and therefore needs none.
		if( $t_modus !== 'extern' ) {
			$t_iv = isset( $p_data['intervall_monate'] ) ? (int)$p_data['intervall_monate'] : 0;
			if( $t_iv < 1 || $t_iv > 600 ) {
				$t_errors[] = 'error_intervall_required';
			}
		}
	}

	return $t_errors;
}

/**
 * Normalise submitted data into the exact column value list for binding.
 * Nullable numeric columns become null when not applicable.
 *
 * @param array $p_data
 * @return array Ordered values matching qt_massnahme_columns().
 */
function qt_massnahme_bind_values( array $p_data ) {
	$t_modus = (string)$p_data['faelligkeitsmodus'];

	$t_intervall = null;
	if( $t_modus !== 'extern' && isset( $p_data['intervall_monate'] ) && $p_data['intervall_monate'] !== '' ) {
		$t_intervall = (int)$p_data['intervall_monate'];
	}

	$t_stichmonat = null;
	if( $t_modus === 'stichmonat' && isset( $p_data['stichmonat'] ) && $p_data['stichmonat'] !== '' ) {
		$t_stichmonat = (int)$p_data['stichmonat'];
	}

	return array(
		trim( (string)$p_data['schluessel'] ),
		trim( (string)$p_data['bezeichnung'] ),
		(string)$p_data['typ'],
		$t_intervall,
		$t_modus,
		$t_stichmonat,
		isset( $p_data['karenz_tage'] ) && $p_data['karenz_tage'] !== '' ? (int)$p_data['karenz_tage'] : 0,
		isset( $p_data['vorlaufzeit_tage'] ) && $p_data['vorlaufzeit_tage'] !== '' ? (int)$p_data['vorlaufzeit_tage'] : 0,
		!empty( $p_data['wiederkehrend'] ) ? 1 : 0,
		!empty( $p_data['sicherheitsrelevant'] ) ? 1 : 0,
		isset( $p_data['rechtsgrundlage'] ) && trim( (string)$p_data['rechtsgrundlage'] ) !== '' ? trim( (string)$p_data['rechtsgrundlage'] ) : null,
		isset( $p_data['nachweisart'] ) && trim( (string)$p_data['nachweisart'] ) !== '' ? trim( (string)$p_data['nachweisart'] ) : null,
		!empty( $p_data['aktiv'] ) ? 1 : 0,
	);
}

/**
 * Column list matching qt_massnahme_bind_values(), for INSERT/UPDATE.
 *
 * @return array
 */
function qt_massnahme_columns() {
	return array(
		'schluessel', 'bezeichnung', 'typ', 'intervall_monate', 'faelligkeitsmodus',
		'stichmonat', 'karenz_tage', 'vorlaufzeit_tage', 'wiederkehrend',
		'sicherheitsrelevant', 'rechtsgrundlage', 'nachweisart', 'aktiv',
	);
}

/**
 * Fetch a single measure by id.
 *
 * @param int $p_id
 * @return array|false Row array or false when not found.
 */
function qt_massnahme_get( $p_id ) {
	$t_query = 'SELECT * FROM ' . plugin_table( 'massnahme' ) . ' WHERE id = ' . db_param();
	$t_result = db_query( $t_query, array( (int)$p_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row === false ? false : $t_row;
}

/**
 * Fetch a measure by its key, optionally excluding one id (for uniqueness
 * checks on update).
 *
 * @param string $p_schluessel
 * @param int    $p_exclude_id
 * @return array|false
 */
function qt_massnahme_get_by_schluessel( $p_schluessel, $p_exclude_id = 0 ) {
	$t_query = 'SELECT * FROM ' . plugin_table( 'massnahme' )
		. ' WHERE schluessel = ' . db_param() . ' AND id <> ' . db_param();
	$t_result = db_query( $t_query, array( (string)$p_schluessel, (int)$p_exclude_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row === false ? false : $t_row;
}

/**
 * Load all measures ordered by key.
 *
 * @param bool $p_include_inactive Include measures flagged inactive.
 * @return array List of row arrays.
 */
function qt_massnahme_load_all( $p_include_inactive = true ) {
	$t_query = 'SELECT * FROM ' . plugin_table( 'massnahme' );
	if( !$p_include_inactive ) {
		$t_query .= ' WHERE aktiv = 1';
	}
	$t_query .= ' ORDER BY schluessel';
	$t_result = db_query( $t_query );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Insert a new measure.
 *
 * @param array $p_data Validated data.
 * @return int New id.
 */
function qt_massnahme_create( array $p_data ) {
	$t_table = plugin_table( 'massnahme' );
	$t_columns = qt_massnahme_columns();
	$t_values = qt_massnahme_bind_values( $p_data );

	$t_now = time();
	$t_columns[] = 'date_created';  $t_values[] = $t_now;
	$t_columns[] = 'date_modified'; $t_values[] = $t_now;

	$t_placeholders = implode( ', ', array_fill( 0, count( $t_values ), db_param() ) );
	$t_query = 'INSERT INTO ' . $t_table . ' ( ' . implode( ', ', $t_columns ) . ' ) VALUES ( ' . $t_placeholders . ' )';
	db_query( $t_query, $t_values );

	return db_insert_id( $t_table );
}

/**
 * Update an existing measure.
 *
 * @param int   $p_id
 * @param array $p_data Validated data.
 * @return void
 */
function qt_massnahme_update( $p_id, array $p_data ) {
	$t_table = plugin_table( 'massnahme' );
	$t_columns = qt_massnahme_columns();
	$t_values = qt_massnahme_bind_values( $p_data );

	$t_set = array();
	foreach( $t_columns as $t_column ) {
		$t_set[] = $t_column . ' = ' . db_param();
	}
	$t_set[] = 'date_modified = ' . db_param();
	$t_values[] = time();

	$t_values[] = (int)$p_id;
	$t_query = 'UPDATE ' . $t_table . ' SET ' . implode( ', ', $t_set ) . ' WHERE id = ' . db_param();
	db_query( $t_query, $t_values );
}

/**
 * Is the measure referenced by a profile or an event? Such measures must not be
 * hard-deleted; the page offers deactivation instead.
 *
 * @param int $p_id
 * @return bool
 */
function qt_massnahme_is_referenced( $p_id ) {
	$t_id = (int)$p_id;

	$t_result = db_query( 'SELECT COUNT(*) AS c FROM ' . plugin_table( 'profil_massnahme' )
		. ' WHERE massnahme_id = ' . db_param(), array( $t_id ) );
	if( (int)db_result( $t_result ) > 0 ) {
		return true;
	}

	$t_result = db_query( 'SELECT COUNT(*) AS c FROM ' . plugin_table( 'veranstaltung' )
		. ' WHERE massnahme_id = ' . db_param(), array( $t_id ) );
	return (int)db_result( $t_result ) > 0;
}

/**
 * Delete a measure by id.
 *
 * @param int $p_id
 * @return void
 */
function qt_massnahme_delete( $p_id ) {
	db_query( 'DELETE FROM ' . plugin_table( 'massnahme' ) . ' WHERE id = ' . db_param(),
		array( (int)$p_id ) );
}
