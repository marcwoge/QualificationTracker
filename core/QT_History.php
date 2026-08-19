<?php
/**
 * QualificationTracker – change history for master data (Änderungsprotokoll, F7.5).
 *
 * MantisBT records the history of every ticket, but the plugin's own master data
 * (measure catalogue, activity profiles and their composition, assignments) live
 * in plugin tables and are invisible to that mechanism. This module logs their
 * create/update/delete changes into an append-only table, analogous to the bug
 * history: who changed which field from what to what, and when.
 *
 * qt_historie_diff() is pure and unit-tested; recording and loading read and
 * write the database. The CRUD layers call the record helpers through a lazy
 * require, so the pure data files stay loadable without this module.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The master-data entity types tracked by the change history. Pure.
 *
 * @return array
 */
function qt_historie_entities() {
	return array( 'massnahme', 'profil', 'zuordnung' );
}

/**
 * Compare two record snapshots over a field list and return the changed fields.
 * Pure: values are compared as strings (missing keys and null count as ''), so
 * the caller may pass raw database rows directly.
 *
 * @param array $p_old    Previous snapshot.
 * @param array $p_new    New snapshot.
 * @param array $p_fields Field names to compare.
 * @return array List of array( 'feld' => name, 'alt' => string, 'neu' => string ).
 */
function qt_historie_diff( array $p_old, array $p_new, array $p_fields ) {
	$t_changes = array();
	foreach( $p_fields as $t_field ) {
		$t_alt = array_key_exists( $t_field, $p_old ) && $p_old[$t_field] !== null ? (string)$p_old[$t_field] : '';
		$t_neu = array_key_exists( $t_field, $p_new ) && $p_new[$t_field] !== null ? (string)$p_new[$t_field] : '';
		if( $t_alt !== $t_neu ) {
			$t_changes[] = array( 'feld' => $t_field, 'alt' => $t_alt, 'neu' => $t_neu );
		}
	}
	return $t_changes;
}

/**
 * Record one change row.
 *
 * @param string $p_typ       Entity type (see qt_historie_entities()).
 * @param int    $p_entity_id Entity id.
 * @param string $p_aktion    'create' | 'update' | 'delete'.
 * @param string $p_feld      Changed field (empty for create/delete).
 * @param string $p_alt       Old value.
 * @param string $p_neu       New value.
 * @return void
 */
function qt_historie_log( $p_typ, $p_entity_id, $p_aktion, $p_feld, $p_alt, $p_neu ) {
	db_query( 'INSERT INTO ' . plugin_table( 'historie' )
		. ' ( entity_typ, entity_id, aktion, feld, alt_wert, neu_wert, user_id, date_created )'
		. ' VALUES ( ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param()
		. ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ' )',
		array(
			substr( (string)$p_typ, 0, 16 ),
			(int)$p_entity_id,
			substr( (string)$p_aktion, 0, 8 ),
			substr( (string)$p_feld, 0, 64 ),
			(string)$p_alt,
			(string)$p_neu,
			(int)auth_get_current_user_id(),
			time(),
		) );
}

/**
 * Record the creation of an entity; the label (e.g. the measure key) is stored
 * as the new value so it stays meaningful after the entity is gone.
 *
 * @param string $p_typ
 * @param int    $p_entity_id
 * @param string $p_label
 * @return void
 */
function qt_historie_created( $p_typ, $p_entity_id, $p_label = '' ) {
	qt_historie_log( $p_typ, $p_entity_id, 'create', '', '', $p_label );
}

/**
 * Record the deletion of an entity; the label is stored as the old value.
 *
 * @param string $p_typ
 * @param int    $p_entity_id
 * @param string $p_label
 * @return void
 */
function qt_historie_deleted( $p_typ, $p_entity_id, $p_label = '' ) {
	qt_historie_log( $p_typ, $p_entity_id, 'delete', '', $p_label, '' );
}

/**
 * Diff two snapshots and record one row per changed field.
 *
 * @param string $p_typ
 * @param int    $p_entity_id
 * @param array  $p_old
 * @param array  $p_new
 * @param array  $p_fields
 * @return int Number of changes recorded.
 */
function qt_historie_updated( $p_typ, $p_entity_id, array $p_old, array $p_new, array $p_fields ) {
	$t_changes = qt_historie_diff( $p_old, $p_new, $p_fields );
	foreach( $t_changes as $t_c ) {
		qt_historie_log( $p_typ, $p_entity_id, 'update', $t_c['feld'], $t_c['alt'], $t_c['neu'] );
	}
	return count( $t_changes );
}

/**
 * Record a single field change directly (used for composed values such as a
 * profile's measure set) when old and new differ.
 *
 * @param string $p_typ
 * @param int    $p_entity_id
 * @param string $p_feld
 * @param string $p_alt
 * @param string $p_neu
 * @return bool True when a change was recorded.
 */
function qt_historie_field( $p_typ, $p_entity_id, $p_feld, $p_alt, $p_neu ) {
	if( (string)$p_alt === (string)$p_neu ) {
		return false;
	}
	qt_historie_log( $p_typ, $p_entity_id, 'update', $p_feld, (string)$p_alt, (string)$p_neu );
	return true;
}

/**
 * Load recent history rows, newest first, optionally filtered by entity type
 * and/or a single entity.
 *
 * @param int    $p_limit
 * @param string $p_typ       Empty = all types.
 * @param int    $p_entity_id 0 = all entities.
 * @return array
 */
function qt_historie_load_recent( $p_limit = 100, $p_typ = '', $p_entity_id = 0 ) {
	$t_where = array();
	$t_params = array();
	if( $p_typ !== '' ) {
		$t_where[] = 'entity_typ = ' . db_param();
		$t_params[] = $p_typ;
	}
	if( (int)$p_entity_id > 0 ) {
		$t_where[] = 'entity_id = ' . db_param();
		$t_params[] = (int)$p_entity_id;
	}
	$t_query = 'SELECT * FROM ' . plugin_table( 'historie' );
	if( !empty( $t_where ) ) {
		$t_query .= ' WHERE ' . implode( ' AND ', $t_where );
	}
	$t_query .= ' ORDER BY date_created DESC, id DESC';

	$t_result = db_query( $t_query, $t_params, (int)$p_limit );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}
