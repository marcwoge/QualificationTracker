<?php
/**
 * QualificationTracker – group event data layer (F3.1).
 *
 * Group events (Sammeltermine): one measure held for many people on a date. The
 * participant selection (F3.2), child tickets (F3.3) and mass completion (F3.4)
 * build on this. Produces no output, never reads $_POST; qt_event_validate() is
 * pure and unit-tested.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The event states.
 *
 * @return array
 */
function qt_event_statuses() {
	return array( 'geplant', 'durchgefuehrt', 'abgesagt' );
}

/**
 * Is the date part of a termin string valid? Accepts "YYYY-MM-DD" optionally
 * followed by a time ("T"/space + HH:MM). Pure.
 *
 * @param string $p_termin
 * @return bool
 */
function qt_event_valid_termin( $p_termin ) {
	$t_value = trim( (string)$p_termin );
	if( !preg_match( '/^(\d{4})-(\d{2})-(\d{2})([ T]\d{2}:\d{2}(:\d{2})?)?$/', $t_value, $t_m ) ) {
		return false;
	}
	return checkdate( (int)$t_m[2], (int)$t_m[3], (int)$t_m[1] );
}

/**
 * Validate event input. Pure function returning a list of error keys.
 *
 * @param array $p_data
 * @return array
 */
function qt_event_validate( array $p_data ) {
	$t_errors = array();

	if( (int)( $p_data['massnahme_id'] ?? 0 ) <= 0 ) {
		$t_errors[] = 'error_event_massnahme_required';
	}

	$t_titel = isset( $p_data['titel'] ) ? trim( (string)$p_data['titel'] ) : '';
	if( $t_titel === '' ) {
		$t_errors[] = 'error_event_titel_required';
	} else if( mb_strlen( $t_titel ) > 191 ) {
		$t_errors[] = 'error_event_titel_length';
	}

	$t_termin = isset( $p_data['termin'] ) ? trim( (string)$p_data['termin'] ) : '';
	if( $t_termin === '' ) {
		$t_errors[] = 'error_event_termin_required';
	} else if( !qt_event_valid_termin( $t_termin ) ) {
		$t_errors[] = 'error_event_termin_invalid';
	}

	if( isset( $p_data['kapazitaet'] ) && trim( (string)$p_data['kapazitaet'] ) !== '' ) {
		$t_kap = $p_data['kapazitaet'];
		if( !is_numeric( $t_kap ) || (int)$t_kap < 0 ) {
			$t_errors[] = 'error_event_kapazitaet_invalid';
		}
	}

	return $t_errors;
}

/**
 * Normalise a termin input to a datetime string "YYYY-MM-DD HH:MM:SS".
 *
 * @param string $p_termin
 * @return string
 */
function qt_event_normalise_termin( $p_termin ) {
	$t_value = str_replace( 'T', ' ', trim( (string)$p_termin ) );
	if( strlen( $t_value ) === 10 ) {
		return $t_value . ' 00:00:00';
	}
	if( strlen( $t_value ) === 16 ) {
		return $t_value . ':00';
	}
	return $t_value;
}

/**
 * Fetch an event by id.
 *
 * @param int $p_id
 * @return array|false
 */
function qt_event_get( $p_id ) {
	$t_result = db_query( 'SELECT * FROM ' . plugin_table( 'veranstaltung' ) . ' WHERE id = ' . db_param(),
		array( (int)$p_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row === false ? false : $t_row;
}

/**
 * Load events with the measure key/name, newest termin first.
 *
 * @return array
 */
function qt_event_load_all() {
	$t_query = 'SELECT v.*, m.schluessel, m.bezeichnung'
		. ' FROM ' . plugin_table( 'veranstaltung' ) . ' v'
		. ' LEFT JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = v.massnahme_id'
		. ' ORDER BY v.termin DESC, v.id DESC';
	$t_result = db_query( $t_query );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Ordered value list for INSERT/UPDATE, matching qt_event_columns().
 *
 * @param array $p_data
 * @return array
 */
function qt_event_bind_values( array $p_data ) {
	$t_str = function( $p_key ) use ( $p_data ) {
		$t_v = isset( $p_data[$p_key] ) ? trim( (string)$p_data[$p_key] ) : '';
		return $t_v === '' ? null : $t_v;
	};
	$t_status = isset( $p_data['status'] ) && in_array( $p_data['status'], qt_event_statuses(), true )
		? $p_data['status'] : 'geplant';

	return array(
		(int)$p_data['massnahme_id'],
		trim( (string)$p_data['titel'] ),
		qt_event_normalise_termin( $p_data['termin'] ),
		$t_str( 'ort' ),
		$t_str( 'unterweisender' ),
		( isset( $p_data['kapazitaet'] ) && trim( (string)$p_data['kapazitaet'] ) !== '' ) ? (int)$p_data['kapazitaet'] : null,
		$t_status,
	);
}

/**
 * Columns matching qt_event_bind_values().
 *
 * @return array
 */
function qt_event_columns() {
	return array( 'massnahme_id', 'titel', 'termin', 'ort', 'unterweisender', 'kapazitaet', 'status' );
}

/**
 * Insert a new event.
 *
 * @param array $p_data
 * @return int New id.
 */
function qt_event_create( array $p_data ) {
	$t_table = plugin_table( 'veranstaltung' );
	$t_columns = qt_event_columns();
	$t_values = qt_event_bind_values( $p_data );

	$t_now = time();
	$t_columns[] = 'date_created';  $t_values[] = $t_now;
	$t_columns[] = 'date_modified'; $t_values[] = $t_now;

	$t_placeholders = implode( ', ', array_fill( 0, count( $t_values ), db_param() ) );
	db_query( 'INSERT INTO ' . $t_table . ' ( ' . implode( ', ', $t_columns ) . ' ) VALUES ( '
		. $t_placeholders . ' )', $t_values );

	return db_insert_id( $t_table );
}

/**
 * Update an existing event.
 *
 * @param int   $p_id
 * @param array $p_data
 * @return void
 */
function qt_event_update( $p_id, array $p_data ) {
	$t_columns = qt_event_columns();
	$t_values = qt_event_bind_values( $p_data );

	$t_set = array();
	foreach( $t_columns as $t_column ) {
		$t_set[] = $t_column . ' = ' . db_param();
	}
	$t_set[] = 'date_modified = ' . db_param();
	$t_values[] = time();

	$t_values[] = (int)$p_id;
	db_query( 'UPDATE ' . plugin_table( 'veranstaltung' ) . ' SET ' . implode( ', ', $t_set )
		. ' WHERE id = ' . db_param(), $t_values );
}

/**
 * Delete an event.
 *
 * @param int $p_id
 * @return void
 */
function qt_event_delete( $p_id ) {
	db_query( 'DELETE FROM ' . plugin_table( 'veranstaltung' ) . ' WHERE id = ' . db_param(),
		array( (int)$p_id ) );
}

/**
 * Store the parent event ticket id on an event.
 *
 * @param int $p_id
 * @param int $p_bug_id
 * @return void
 */
function qt_event_set_eltern_bug( $p_id, $p_bug_id ) {
	db_query( 'UPDATE ' . plugin_table( 'veranstaltung' )
		. ' SET eltern_bug_id = ' . db_param() . ', date_modified = ' . db_param()
		. ' WHERE id = ' . db_param(),
		array( (int)$p_bug_id, time(), (int)$p_id ) );
}

/**
 * Ensure the group event has a parent MantisBT ticket (the "Sammeltermin"), the
 * container the per-participant proof tickets hang under (F3.3). Creates it once
 * and stores its id in eltern_bug_id; returns the existing one otherwise.
 *
 * Depends on the generator's category map; both files are loaded together on the
 * pages that call this.
 *
 * @param array $p_event        Event row.
 * @param array $p_massnahme     The event's measure row.
 * @param int   $p_project_id
 * @param array $p_category_ids  Category name => id, from qt_generator_ensure_categories().
 * @return int Bug id of the parent ticket.
 */
function qt_event_ensure_parent_ticket( array $p_event, array $p_massnahme, $p_project_id, array $p_category_ids ) {
	$t_existing = (int)$p_event['eltern_bug_id'];
	if( $t_existing > 0 && bug_exists( $t_existing ) ) {
		return $t_existing;
	}

	$t_category = qt_generator_category_map();
	$t_category_id = isset( $t_category[$p_massnahme['typ']] )
		? (int)$p_category_ids[$t_category[$p_massnahme['typ']]]
		: (int)reset( $p_category_ids );

	$t_termin = substr( (string)$p_event['termin'], 0, 16 );
	$t_summary = mb_substr( $p_massnahme['schluessel'] . ' ' . $p_event['titel']
		. ( $t_termin !== '' ? ' – ' . $t_termin : '' ), 0, 128 );

	$t_description = $p_massnahme['bezeichnung'] . "\n" . $p_event['titel']
		. ( $t_termin !== '' ? "\n" . $t_termin : '' )
		. ( $p_event['ort'] !== null && $p_event['ort'] !== '' ? "\n" . $p_event['ort'] : '' )
		. ( $p_event['unterweisender'] !== null && $p_event['unterweisender'] !== '' ? "\n" . $p_event['unterweisender'] : '' );

	$t_bug = new BugData;
	$t_bug->project_id  = (int)$p_project_id;
	$t_bug->reporter_id = auth_get_current_user_id();
	$t_bug->category_id = $t_category_id;
	$t_bug->summary     = $t_summary;
	$t_bug->description = $t_description;
	if( $p_event['termin'] !== null && $p_event['termin'] !== '' ) {
		$t_bug->due_date = strtotime( (string)$p_event['termin'] );
	}
	$t_bug_id = $t_bug->create();

	qt_event_set_eltern_bug( (int)$p_event['id'], $t_bug_id );
	return $t_bug_id;
}
