<?php
/**
 * QualificationTracker – expiry reactivation for time-limited qualifications
 * (F5.2).
 *
 * A time-limited qualification (type QB) is renewed via a follow-up proof ticket
 * (F2.8) whose due date is the expiry of the current cycle. Instead of leaving
 * that renewal open for the whole – often multi-year – validity, it is put on
 * hold ("zurückgestellt") and reactivated at expiry minus the lead time. The
 * wake date always comes from the due-date calculator.
 *
 * Reveille coupling (hand-off): when the Reveille plugin is installed the renewal
 * is parked on Reveille's deferred status with its due date set, and Reveille
 * wakes it. The native fallback (no Reveille) both defers and reactivates.
 *
 * qt_reactivation_wake_date(), qt_reactivation_vorlauf() and
 * qt_reactivation_is_dormant() are pure and unit-tested; the run reads and
 * writes the database.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The wake date of a renewal: its target date minus the lead time. Pure.
 *
 * @param string|null $p_soll_termin ISO date, the renewal's due date.
 * @param int         $p_vorlauf_tage Lead time in days.
 * @return string|null ISO wake date, or null when there is no target date.
 */
function qt_reactivation_wake_date( $p_soll_termin, $p_vorlauf_tage ) {
	if( $p_soll_termin === null || $p_soll_termin === '' ) {
		return null;
	}
	return QT_DueDateCalculator::add_days( $p_soll_termin, -1 * (int)$p_vorlauf_tage );
}

/**
 * The effective lead time for a measure: its own vorlaufzeit_tage when set,
 * otherwise the largest positive escalation stage (default 90). Pure.
 *
 * @param array $p_massnahme
 * @param mixed $p_eskalation_stufen
 * @return int
 */
function qt_reactivation_vorlauf( array $p_massnahme, $p_eskalation_stufen ) {
	$t_v = isset( $p_massnahme['vorlaufzeit_tage'] ) ? (int)$p_massnahme['vorlaufzeit_tage'] : 0;
	if( $t_v > 0 ) {
		return $t_v;
	}
	return qt_matrix_warn_days( $p_eskalation_stufen );
}

/**
 * Is a renewal still dormant as of today, i.e. its wake date is in the future?
 * Pure.
 *
 * @param string|null $p_wake_date
 * @param string      $p_today
 * @return bool
 */
function qt_reactivation_is_dormant( $p_wake_date, $p_today ) {
	return $p_wake_date !== null && $p_wake_date > $p_today;
}

/**
 * Open renewal proofs of time-limited qualifications (type QB) with a target
 * date and a ticket, joined with the measure fields needed for the lead time.
 *
 * @return array
 */
function qt_reactivation_candidates() {
	$t_query = 'SELECT n.*, m.typ, m.vorlaufzeit_tage, m.schluessel, m.bezeichnung,'
		. ' p.nachname, p.vorname, p.personalnummer, p.abteilung'
		. ' FROM ' . plugin_table( 'nachweis' ) . ' n'
		. ' JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = n.massnahme_id'
		. ' LEFT JOIN ' . plugin_table( 'person' ) . ' p ON p.id = n.person_id'
		. " WHERE m.typ = 'QB'"
		. " AND n.status IN ( 'offen', 'geplant' )"
		. ' AND n.soll_termin IS NOT NULL'
		. ' AND n.bug_id > 0'
		. ' ORDER BY n.soll_termin';
	$t_result = db_query( $t_query );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * The status a deferred renewal is parked at: Reveille's configured deferred
 * status when Reveille is installed (hand-off), else the native fallback config.
 *
 * @param bool $p_reveille
 * @return int
 */
function qt_reactivation_held_status( $p_reveille ) {
	if( $p_reveille ) {
		return (int)config_get( 'plugin_Reveille_deferred_status', 15 );
	}
	return (int)plugin_config_get( 'reactivation_held_status' );
}

/**
 * Run the reactivation pass. Defers dormant QB renewals and – in the native
 * fallback – reactivates those whose wake date has arrived. With Reveille
 * installed the waking is left to Reveille (hand-off). Idempotent.
 *
 * @param string $p_today ISO date.
 * @return array Summary: deferred, reactivated, reveille (0/1).
 */
function qt_reactivation_run( $p_today ) {
	$t_reveille = qt_integration_reveille();
	$t_held = qt_reactivation_held_status( $t_reveille );
	$t_active = qt_status_to_mantis( 'offen' );
	$t_stufen = plugin_config_get( 'eskalation_stufen_tage' );

	$t_summary = array( 'deferred' => 0, 'reactivated' => 0, 'reveille' => $t_reveille ? 1 : 0 );

	foreach( qt_reactivation_candidates() as $t_row ) {
		$t_bug = (int)$t_row['bug_id'];
		if( !bug_exists( $t_bug ) ) {
			continue;
		}
		$t_vorlauf = qt_reactivation_vorlauf( $t_row, $t_stufen );
		$t_wake = qt_reactivation_wake_date( $t_row['soll_termin'], $t_vorlauf );
		$t_status = (int)bug_get_field( $t_bug, 'status' );

		if( qt_reactivation_is_dormant( $t_wake, $p_today ) ) {
			# Should sleep until the wake date.
			if( $t_status !== $t_held ) {
				# Anchor the ticket due date on the target so Reveille wakes right.
				bug_set_field( $t_bug, 'due_date', strtotime( $t_row['soll_termin'] . ' 00:00:00' ) );
				bug_set_field( $t_bug, 'status', $t_held );
				$t_summary['deferred']++;
			}
		} else {
			# Wake date reached. In the fallback we wake it; with Reveille we
			# leave the waking to Reveille.
			if( !$t_reveille && $t_status === $t_held ) {
				bug_set_field( $t_bug, 'status', $t_active );
				$t_summary['reactivated']++;
			}
		}
	}

	return $t_summary;
}
