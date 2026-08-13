<?php
/**
 * QualificationTracker – proof completion and event-driven follow-up (F2.8).
 *
 * Completing a proof records durchgefuehrt_am / gueltig_bis / durchfuehrender,
 * sets the proof valid, and – for recurring measures – creates the follow-up
 * ticket (the event-driven strategy for rolling/external modes). This core is
 * reused by the mass completion in M3.
 *
 * qt_completion_gueltig_bis() is pure and unit-tested; the completion itself
 * uses the Mantis and generator APIs.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The validity end date of a completed proof. Pure.
 *
 * For 'extern' the date comes from the presented document; for every computing
 * mode it is the next due date from QT_DueDateCalculator (which also becomes the
 * follow-up cycle's target date).
 *
 * @param string      $p_modus
 * @param string      $p_durchgefuehrt_am
 * @param string|null $p_soll_termin
 * @param int         $p_intervall_monate
 * @param int         $p_karenz_tage
 * @param int|null    $p_massnahme_stichmonat
 * @param int|null    $p_abteilung_stichmonat
 * @param string|null $p_extern_input Date entered for the 'extern' mode.
 * @return string|null ISO date or null.
 */
function qt_completion_gueltig_bis( $p_modus, $p_durchgefuehrt_am, $p_soll_termin, $p_intervall_monate, $p_karenz_tage, $p_massnahme_stichmonat, $p_abteilung_stichmonat, $p_extern_input ) {
	if( $p_modus === 'extern' ) {
		return ( $p_extern_input !== null && $p_extern_input !== '' ) ? $p_extern_input : null;
	}
	return QT_DueDateCalculator::next_due(
		$p_modus, $p_durchgefuehrt_am, $p_soll_termin,
		(int)$p_intervall_monate, (int)$p_karenz_tage,
		$p_massnahme_stichmonat, $p_abteilung_stichmonat );
}

/**
 * Complete a single proof.
 *
 * @param int         $p_nachweis_id
 * @param string      $p_durchgefuehrt_am ISO date.
 * @param string|null $p_gueltig_bis_input For 'extern' measures.
 * @param string      $p_durchfuehrender
 * @return array Summary: completed, followup_created, gueltig_bis, errors[].
 */
function qt_completion_complete( $p_nachweis_id, $p_durchgefuehrt_am, $p_gueltig_bis_input, $p_durchfuehrender ) {
	$t_nw = qt_nachweis_get( $p_nachweis_id );
	if( $t_nw === false ) {
		return array( 'completed' => 0, 'followup_created' => 0, 'gueltig_bis' => null,
			'errors' => array( 'error_nachweis_not_found' ) );
	}
	$t_m = qt_massnahme_get( (int)$t_nw['massnahme_id'] );
	$t_person = qt_person_get( (int)$t_nw['person_id'] );
	if( $t_m === false || $t_person === false ) {
		return array( 'completed' => 0, 'followup_created' => 0, 'gueltig_bis' => null,
			'errors' => array( 'error_nachweis_not_found' ) );
	}

	$t_abt = qt_generator_abteilung_stichmonat( $t_person['abteilung'] );
	$t_bis = qt_completion_gueltig_bis(
		$t_m['faelligkeitsmodus'], $p_durchgefuehrt_am, $t_nw['soll_termin'],
		$t_m['intervall_monate'], $t_m['karenz_tage'], $t_m['stichmonat'], $t_abt, $p_gueltig_bis_input );

	# Mark the proof valid.
	db_query( 'UPDATE ' . plugin_table( 'nachweis' )
		. " SET status = 'gueltig', gueltig_bis = " . db_param() . ', date_modified = ' . db_param()
		. ' WHERE id = ' . db_param(),
		array( ( $t_bis === null || $t_bis === '' ) ? null : $t_bis, time(), (int)$t_nw['id'] ) );

	# Set the ticket fields and status.
	$t_bug = (int)$t_nw['bug_id'];
	if( $t_bug > 0 && bug_exists( $t_bug ) ) {
		$t_fids = qt_generator_field_ids();
		if( isset( $t_fids['durchgefuehrt_am'] ) ) {
			custom_field_set_value( $t_fids['durchgefuehrt_am'], $t_bug, strtotime( $p_durchgefuehrt_am . ' 00:00:00' ) );
		}
		if( $t_bis !== null && $t_bis !== '' && isset( $t_fids['gueltig_bis'] ) ) {
			custom_field_set_value( $t_fids['gueltig_bis'], $t_bug, strtotime( $t_bis . ' 00:00:00' ) );
		}
		if( $p_durchfuehrender !== '' && isset( $t_fids['durchfuehrender'] ) ) {
			custom_field_set_value( $t_fids['durchfuehrender'], $t_bug, $p_durchfuehrender );
		}
		bug_set_field( $t_bug, 'status', qt_status_to_mantis( 'gueltig' ) );
	}

	# Event-driven follow-up for recurring measures (F2.8).
	$t_followup = 0;
	if( !empty( $t_m['wiederkehrend'] ) && $t_bis !== null && $t_bis !== '' ) {
		$t_zyklus = substr( $t_bis, 0, 4 );
		$t_project_id = (int)plugin_config_get( 'zielprojekt_id' );
		if( $t_project_id > 0
			&& qt_nachweis_find_cycle( (int)$t_person['id'], (int)$t_m['id'], $t_zyklus ) === false ) {
			qt_custom_fields_link( $t_project_id );
			$t_cats = qt_generator_ensure_categories( $t_project_id );
			$t_fids2 = qt_generator_field_ids();
			qt_generator_place_ticket( $t_person, $t_m, $t_bis, $t_project_id, $t_cats, $t_fids2 );
			$t_followup = 1;
		}
	}

	return array( 'completed' => 1, 'followup_created' => $t_followup, 'gueltig_bis' => $t_bis, 'errors' => array() );
}

/**
 * Open proofs (offen / geplant / durchgefuehrt), with person and measure names,
 * optionally filtered by person.
 *
 * @param int $p_person_id 0 for all.
 * @return array
 */
function qt_completion_open_nachweise( $p_person_id = 0 ) {
	$t_query = 'SELECT n.*, p.nachname, p.vorname, p.personalnummer, p.abteilung,'
		. ' m.schluessel, m.bezeichnung, m.typ, m.faelligkeitsmodus'
		. ' FROM ' . plugin_table( 'nachweis' ) . ' n'
		. ' LEFT JOIN ' . plugin_table( 'person' ) . ' p ON p.id = n.person_id'
		. ' LEFT JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = n.massnahme_id'
		. " WHERE n.status IN ( 'offen', 'geplant', 'durchgefuehrt' )";
	$t_params = array();
	if( (int)$p_person_id > 0 ) {
		$t_query .= ' AND n.person_id = ' . db_param();
		$t_params[] = (int)$p_person_id;
	}
	$t_query .= ' ORDER BY p.nachname, p.vorname, m.schluessel';

	$t_result = db_query( $t_query, $t_params );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}
