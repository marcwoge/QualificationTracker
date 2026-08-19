<?php
/**
 * QualificationTracker – retention & deletion concept (F7.3).
 *
 * Personal proof data must not be kept indefinitely (DSGVO Art. 17 / § 35 BDSG).
 * Each measure type carries a retention period (Aufbewahrungsfrist); once it has
 * elapsed after a finished proof's anchor date, the proof becomes a deletion
 * candidate. An administrator reviews the proposal list and confirms; every
 * executed deletion is written to an append-only deletion log (Löschprotokoll)
 * so the erasure itself stays auditable after the ticket is gone.
 *
 * Retention resolution and the date arithmetic are pure and unit-tested; the
 * candidate query and the execution read and write the database.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The proof states whose retention clock has started, i.e. finished cycles that
 * may eventually be deleted. Active states (offen/geplant/durchgefuehrt/gueltig)
 * are never candidates. Pure.
 *
 * @return array
 */
function qt_loesch_final_states() {
	return array( 'abgelaufen', 'entfallen' );
}

/**
 * Whether a proof state is a finished cycle eligible for retention. Pure.
 *
 * @param string $p_status
 * @return bool
 */
function qt_loesch_state_final( $p_status ) {
	return in_array( (string)$p_status, qt_loesch_final_states(), true );
}

/**
 * Resolve the retention period (in months) for a measure type from the config
 * map, falling back to the global default when the type has no explicit entry.
 * Pure.
 *
 * @param string $p_typ
 * @param array  $p_map     Map measure type => months.
 * @param int    $p_default Global default months.
 * @return int
 */
function qt_loesch_retention_months( $p_typ, array $p_map, $p_default ) {
	if( isset( $p_map[$p_typ] ) && $p_map[$p_typ] !== '' && (int)$p_map[$p_typ] > 0 ) {
		return (int)$p_map[$p_typ];
	}
	return (int)$p_default;
}

/**
 * The date from which the retention period is counted for a finished proof.
 * For an expired proof this is the end of validity; for a cancelled one there
 * is no validity, so the modification date is used. Pure.
 *
 * @param string      $p_status
 * @param string|null $p_gueltig_bis   ISO date or null/empty.
 * @param string|null $p_modified_iso  ISO date of the last modification.
 * @return string|null ISO date, or null when no anchor can be determined.
 */
function qt_loesch_anchor( $p_status, $p_gueltig_bis, $p_modified_iso ) {
	if( $p_status === 'abgelaufen' && $p_gueltig_bis !== null && $p_gueltig_bis !== '' ) {
		return substr( (string)$p_gueltig_bis, 0, 10 );
	}
	if( $p_modified_iso !== null && $p_modified_iso !== '' ) {
		return substr( (string)$p_modified_iso, 0, 10 );
	}
	return null;
}

/**
 * The earliest date on which a proof may be deleted: its anchor plus the
 * retention period. Pure; reuses the calendar-correct month arithmetic of the
 * due-date calculator.
 *
 * @param string|null $p_anchor_iso
 * @param int         $p_retention_months
 * @return string|null ISO date, or null when there is no anchor or no period.
 */
function qt_loesch_delete_on( $p_anchor_iso, $p_retention_months ) {
	if( $p_anchor_iso === null || $p_anchor_iso === '' || (int)$p_retention_months <= 0 ) {
		return null;
	}
	return QT_DueDateCalculator::add_months( substr( (string)$p_anchor_iso, 0, 10 ), (int)$p_retention_months );
}

/**
 * Whether a proof is due for deletion as of the key date. Pure.
 *
 * @param string|null $p_delete_on_iso
 * @param string      $p_today ISO date.
 * @return bool
 */
function qt_loesch_is_due( $p_delete_on_iso, $p_today ) {
	return $p_delete_on_iso !== null && $p_delete_on_iso !== '' && $p_delete_on_iso <= $p_today;
}

/**
 * The proofs whose retention period has elapsed as of the key date, joined with
 * person and measure for display. Each row is annotated with the resolved
 * retention period, its anchor date and the computed deletion date.
 *
 * @param string $p_today ISO date.
 * @return array
 */
function qt_loesch_candidates( $p_today ) {
	$t_map     = (array)plugin_config_get( 'aufbewahrung_monate_typ' );
	$t_default = (int)plugin_config_get( 'aufbewahrung_monate_default' );

	$t_placeholders = array();
	$t_params = array();
	foreach( qt_loesch_final_states() as $t_state ) {
		$t_placeholders[] = db_param();
		$t_params[] = $t_state;
	}

	$t_query = 'SELECT n.*, p.nachname, p.vorname, p.personalnummer, p.abteilung,'
		. ' m.schluessel, m.bezeichnung, m.typ'
		. ' FROM ' . plugin_table( 'nachweis' ) . ' n'
		. ' LEFT JOIN ' . plugin_table( 'person' ) . ' p ON p.id = n.person_id'
		. ' LEFT JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = n.massnahme_id'
		. ' WHERE n.status IN ( ' . implode( ', ', $t_placeholders ) . ' )'
		. ' ORDER BY n.gueltig_bis, p.nachname, p.vorname';
	$t_result = db_query( $t_query, $t_params );

	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_typ = (string)$t_row['typ'];
		$t_months = qt_loesch_retention_months( $t_typ, $t_map, $t_default );
		$t_modified = (int)$t_row['date_modified'] > 0 ? date( 'Y-m-d', (int)$t_row['date_modified'] ) : null;
		$t_anchor = qt_loesch_anchor( (string)$t_row['status'], $t_row['gueltig_bis'], $t_modified );
		$t_delete_on = qt_loesch_delete_on( $t_anchor, $t_months );

		if( !qt_loesch_is_due( $t_delete_on, $p_today ) ) {
			continue;
		}

		$t_row['aufbewahrung_monate'] = $t_months;
		$t_row['anker']               = $t_anchor;
		$t_row['loeschdatum']         = $t_delete_on;
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Load a single proof-index row by id (for the execution path).
 *
 * @param int $p_id
 * @return array|false
 */
function qt_loesch_nachweis_get( $p_id ) {
	$t_result = db_query( 'SELECT n.*, p.personalnummer, m.schluessel, m.typ'
		. ' FROM ' . plugin_table( 'nachweis' ) . ' n'
		. ' LEFT JOIN ' . plugin_table( 'person' ) . ' p ON p.id = n.person_id'
		. ' LEFT JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = n.massnahme_id'
		. ' WHERE n.id = ' . db_param(), array( (int)$p_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row === false ? false : $t_row;
}

/**
 * Record one executed deletion in the append-only deletion log. Keeps the
 * business identifiers (personnel number, measure key, ticket id, validity end)
 * as a self-contained protocol so the erasure stays provable after the ticket
 * and the master data are gone.
 *
 * @param array  $p_nachweis Row from qt_loesch_nachweis_get().
 * @param string $p_grund    Reason / legal basis note.
 * @return void
 */
function qt_loesch_log( array $p_nachweis, $p_grund ) {
	db_query( 'INSERT INTO ' . plugin_table( 'loeschung' )
		. ' ( bug_id, person_id, massnahme_id, personalnummer, massnahme_schluessel,'
		. ' gueltig_bis, grund, user_id, date_created )'
		. ' VALUES ( ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param()
		. ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ' )',
		array(
			(int)$p_nachweis['bug_id'],
			(int)$p_nachweis['person_id'],
			(int)$p_nachweis['massnahme_id'],
			substr( (string)$p_nachweis['personalnummer'], 0, 64 ),
			substr( (string)$p_nachweis['schluessel'], 0, 64 ),
			( $p_nachweis['gueltig_bis'] === null || $p_nachweis['gueltig_bis'] === '' )
				? null : substr( (string)$p_nachweis['gueltig_bis'], 0, 10 ),
			substr( (string)$p_grund, 0, 191 ),
			(int)auth_get_current_user_id(),
			time(),
		) );
}

/**
 * Delete the selected proof instances: remove the underlying MantisBT ticket
 * (with its notes, custom-field values, relationships and attachments), the
 * derived index row and any event-participant link, and write one deletion-log
 * entry per proof. Only proofs whose retention has genuinely elapsed as of the
 * key date are deleted; anything else in the list is skipped defensively.
 *
 * @param array  $p_ids   Proof-index ids selected for deletion.
 * @param string $p_grund Reason / legal basis note stored in the log.
 * @param string $p_today ISO date used to re-check eligibility.
 * @return array Summary: deleted (count), skipped (count), bug_ids (list).
 */
function qt_loesch_execute( array $p_ids, $p_grund, $p_today ) {
	$t_map     = (array)plugin_config_get( 'aufbewahrung_monate_typ' );
	$t_default = (int)plugin_config_get( 'aufbewahrung_monate_default' );

	$t_deleted = 0;
	$t_skipped = 0;
	$t_bug_ids = array();

	foreach( $p_ids as $t_id ) {
		$t_row = qt_loesch_nachweis_get( (int)$t_id );
		if( $t_row === false ) {
			$t_skipped++;
			continue;
		}

		# Re-check eligibility server-side: never trust the submitted id alone.
		$t_months = qt_loesch_retention_months( (string)$t_row['typ'], $t_map, $t_default );
		$t_modified = (int)$t_row['date_modified'] > 0 ? date( 'Y-m-d', (int)$t_row['date_modified'] ) : null;
		$t_anchor = qt_loesch_anchor( (string)$t_row['status'], $t_row['gueltig_bis'], $t_modified );
		$t_delete_on = qt_loesch_delete_on( $t_anchor, $t_months );
		if( !qt_loesch_state_final( (string)$t_row['status'] ) || !qt_loesch_is_due( $t_delete_on, $p_today ) ) {
			$t_skipped++;
			continue;
		}

		# Log first, so the protocol survives even if a later step fails.
		qt_loesch_log( $t_row, $p_grund );

		$t_bug = (int)$t_row['bug_id'];
		if( $t_bug > 0 && bug_exists( $t_bug ) ) {
			bug_delete( $t_bug );
			$t_bug_ids[] = $t_bug;
		}

		# Remove any event-participant link pointing at the deleted ticket.
		db_query( 'DELETE FROM ' . plugin_table( 'teilnehmer' )
			. ' WHERE bug_id = ' . db_param(), array( $t_bug ) );

		db_query( 'DELETE FROM ' . plugin_table( 'nachweis' )
			. ' WHERE id = ' . db_param(), array( (int)$t_row['id'] ) );

		$t_deleted++;
	}

	return array( 'deleted' => $t_deleted, 'skipped' => $t_skipped, 'bug_ids' => $t_bug_ids );
}

/**
 * Load the most recent deletion-log rows, newest first.
 *
 * @param int $p_limit
 * @return array
 */
function qt_loesch_log_load( $p_limit = 100 ) {
	$t_result = db_query( 'SELECT * FROM ' . plugin_table( 'loeschung' )
		. ' ORDER BY date_created DESC, id DESC', array(), (int)$p_limit );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}
