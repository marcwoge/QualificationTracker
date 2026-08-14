<?php
/**
 * QualificationTracker – escalation stages (F5.3).
 *
 * As an open proof's due date approaches and passes, notifications fire at the
 * configured day offsets (default 90/30/0/-30 days). Each stage adds a bugnote
 * to the proof ticket – which MantisBT emails to the handler (the supervisor)
 * and monitors – and optionally widens the circle by adding the stage's
 * configured extra recipients as monitors. A per-proof counter records the
 * highest stage already fired, so each stage fires exactly once.
 *
 * qt_eskalation_reached_count() is pure and unit-tested; the run reads and
 * writes the database and sends the notifications.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * How many escalation stages have been reached as of the key date. Pure.
 *
 * The stages are day offsets relative to the due date, descending (positive =
 * days before due, negative = days after). A stage is reached once the days
 * remaining until due drop to its offset or below. Because the offsets are
 * descending the reached stages form a prefix, so the count is the stage level:
 * 0 = none yet, N = all stages reached.
 *
 * @param string|null $p_soll_termin ISO due date.
 * @param string      $p_today       ISO date.
 * @param array       $p_stufen      Day offsets, descending.
 * @return int
 */
function qt_eskalation_reached_count( $p_soll_termin, $p_today, array $p_stufen ) {
	if( $p_soll_termin === null || $p_soll_termin === '' ) {
		return 0;
	}
	$t_soll  = substr( $p_soll_termin, 0, 10 );
	$t_today = substr( $p_today, 0, 10 );

	# Signed days until due: positive when the due date is still in the future.
	$t_mag = QT_DueDateCalculator::day_diff( $t_today, $t_soll );
	$t_days = ( $t_soll < $t_today ) ? -$t_mag : $t_mag;

	$t_count = 0;
	foreach( $p_stufen as $t_stufe ) {
		if( $t_days <= (int)$t_stufe ) {
			$t_count++;
		}
	}
	return $t_count;
}

/**
 * The open proofs subject to escalation: not yet valid/cancelled, with a due
 * date and a ticket, joined with person and measure.
 *
 * @return array
 */
function qt_eskalation_candidates() {
	$t_query = 'SELECT n.*, p.nachname, p.vorname, p.vorgesetzter_user_id,'
		. ' m.schluessel, m.bezeichnung'
		. ' FROM ' . plugin_table( 'nachweis' ) . ' n'
		. ' LEFT JOIN ' . plugin_table( 'person' ) . ' p ON p.id = n.person_id'
		. ' LEFT JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = n.massnahme_id'
		. " WHERE n.status IN ( 'offen', 'geplant', 'durchgefuehrt' )"
		. ' AND n.soll_termin IS NOT NULL AND n.bug_id > 0'
		. ' ORDER BY n.soll_termin';
	$t_result = db_query( $t_query );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Run the escalation pass. For every open proof whose reached stage exceeds the
 * highest already recorded, fire each newly-crossed stage (bugnote + monitors)
 * and update the recorded stage. Idempotent.
 *
 * @param string $p_today ISO date.
 * @return array Summary: notified (proofs), stages (bugnotes fired).
 */
function qt_eskalation_run( $p_today ) {
	$t_stufen = (array)plugin_config_get( 'eskalation_stufen_tage' );
	$t_empf   = (array)plugin_config_get( 'eskalation_empfaenger' );
	$t_max    = count( $t_stufen );

	$t_summary = array( 'notified' => 0, 'stages' => 0 );

	foreach( qt_eskalation_candidates() as $t_row ) {
		$t_bug = (int)$t_row['bug_id'];
		if( !bug_exists( $t_bug ) ) {
			continue;
		}
		$t_reached = qt_eskalation_reached_count( $t_row['soll_termin'], $p_today, $t_stufen );
		$t_stored = (int)$t_row['eskalation_stufe'];
		if( $t_reached <= $t_stored ) {
			continue;
		}

		# Fire each newly-crossed stage (stored .. reached-1, zero-based).
		for( $t_i = $t_stored; $t_i < $t_reached; $t_i++ ) {
			$t_recipients = isset( $t_empf[$t_i] ) ? (array)$t_empf[$t_i] : array();
			foreach( $t_recipients as $t_user_id ) {
				if( (int)$t_user_id > 0 && user_exists( (int)$t_user_id ) ) {
					bug_monitor( $t_bug, (int)$t_user_id );
				}
			}
			$t_note = sprintf( plugin_lang_get( 'eskalation_note' ),
				$t_i + 1, $t_max, (int)$t_stufen[$t_i], $t_row['soll_termin'] );
			bugnote_add( $t_bug, $t_note, '0:00', false, BUGNOTE );
			$t_summary['stages']++;
		}

		db_query( 'UPDATE ' . plugin_table( 'nachweis' ) . ' SET eskalation_stufe = ' . db_param()
			. ', date_modified = ' . db_param() . ' WHERE id = ' . db_param(),
			array( $t_reached, time(), (int)$t_row['id'] ) );
		$t_summary['notified']++;
	}

	return $t_summary;
}
