<?php
/**
 * QualificationTracker – due-date mode change simulation (F5.7).
 *
 * Changing a measure's due-date mode while proofs already exist must not silently
 * shift target dates. This module simulates the effect on the OPEN proofs and,
 * on confirmation, applies it. Completed cycles (valid / expired / cancelled) are
 * never recomputed – rewriting them would break the audit trail.
 *
 * The new target keeps the proof's cycle year and only re-places the due date
 * within it according to the new mode: 'kalenderjahr' -> 31 Dec, 'stichmonat' ->
 * month-end, 'extern' -> no computed date, 'rollierend' -> unchanged until the
 * next completion recomputes it.
 *
 * qt_moduswechsel_new_soll() is pure and unit-tested; simulate/apply read and
 * write the database.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The proof states that are recomputed on a mode change (still pending). The
 * finished states – gueltig, abgelaufen, entfallen – are preserved.
 *
 * @return array
 */
function qt_moduswechsel_open_stati() {
	return array( 'offen', 'geplant', 'durchgefuehrt' );
}

/**
 * The new target date of an open proof under a new mode. Pure.
 *
 * @param string|null $p_old_soll      Current target date (ISO) or null.
 * @param string      $p_new_modus
 * @param int|null    $p_new_stichmonat Reference month for 'stichmonat'.
 * @return string|null New ISO target date, or null when the mode computes none.
 */
function qt_moduswechsel_new_soll( $p_old_soll, $p_new_modus, $p_new_stichmonat ) {
	if( $p_new_modus === 'extern' ) {
		return null;
	}
	if( $p_old_soll === null || $p_old_soll === '' ) {
		return null;
	}
	$t_year = (int)substr( $p_old_soll, 0, 4 );

	switch( $p_new_modus ) {
		case 'kalenderjahr':
			return QT_DueDateCalculator::last_day_of_year( $p_old_soll );
		case 'stichmonat':
			$t_monat = (int)$p_new_stichmonat;
			if( $t_monat < 1 || $t_monat > 12 ) {
				return null;
			}
			return QT_DueDateCalculator::last_day_of_month( $t_year, $t_monat );
		case 'rollierend':
			return substr( $p_old_soll, 0, 10 );
	}
	return null;
}

/**
 * Simulate a mode change for a measure: the open proofs with their old and new
 * target date, plus the count of preserved (finished) proofs.
 *
 * @param int      $p_massnahme_id
 * @param string   $p_new_modus
 * @param int|null $p_new_stichmonat
 * @return array massnahme, affected[], preserved (count).
 */
function qt_moduswechsel_simulate( $p_massnahme_id, $p_new_modus, $p_new_stichmonat ) {
	$t_massnahme = qt_massnahme_get( $p_massnahme_id );
	$t_open = qt_moduswechsel_open_stati();

	$t_query = 'SELECT n.*, p.nachname, p.vorname, p.personalnummer'
		. ' FROM ' . plugin_table( 'nachweis' ) . ' n'
		. ' LEFT JOIN ' . plugin_table( 'person' ) . ' p ON p.id = n.person_id'
		. ' WHERE n.massnahme_id = ' . db_param()
		. ' ORDER BY p.nachname, p.vorname';
	$t_result = db_query( $t_query, array( (int)$p_massnahme_id ) );

	$t_affected = array();
	$t_preserved = 0;
	while( $t_row = db_fetch_array( $t_result ) ) {
		if( !in_array( $t_row['status'], $t_open, true ) ) {
			$t_preserved++;
			continue;
		}
		$t_new = qt_moduswechsel_new_soll( $t_row['soll_termin'], $p_new_modus, $p_new_stichmonat );
		$t_affected[] = array(
			'nachweis_id'    => (int)$t_row['id'],
			'bug_id'         => (int)$t_row['bug_id'],
			'person'         => trim( $t_row['nachname'] . ', ' . $t_row['vorname'], ', ' ),
			'personalnummer' => $t_row['personalnummer'],
			'status'         => $t_row['status'],
			'alt'            => $t_row['soll_termin'],
			'neu'            => $t_new,
			'changed'        => ( (string)$t_row['soll_termin'] !== (string)$t_new ),
		);
	}

	return array( 'massnahme' => $t_massnahme, 'affected' => $t_affected, 'preserved' => $t_preserved );
}

/**
 * Apply a mode change: update the measure's mode (and reference month), then
 * recompute the open proofs' target date, ticket due date and soll_termin field.
 * Finished proofs are left untouched.
 *
 * @param int      $p_massnahme_id
 * @param string   $p_new_modus
 * @param int|null $p_new_stichmonat
 * @return array updated (count), preserved (count).
 */
function qt_moduswechsel_apply( $p_massnahme_id, $p_new_modus, $p_new_stichmonat ) {
	$t_sim = qt_moduswechsel_simulate( $p_massnahme_id, $p_new_modus, $p_new_stichmonat );
	$t_field_ids = qt_generator_field_ids();
	$t_soll_field = isset( $t_field_ids['soll_termin'] ) ? (int)$t_field_ids['soll_termin'] : 0;
	$t_now = time();

	foreach( $t_sim['affected'] as $t_a ) {
		$t_new = $t_a['neu'];
		db_query( 'UPDATE ' . plugin_table( 'nachweis' ) . ' SET soll_termin = ' . db_param()
			. ', date_modified = ' . db_param() . ' WHERE id = ' . db_param(),
			array( ( $t_new === null || $t_new === '' ) ? null : $t_new, $t_now, (int)$t_a['nachweis_id'] ) );

		$t_bug = (int)$t_a['bug_id'];
		if( $t_bug > 0 && bug_exists( $t_bug ) ) {
			bug_set_field( $t_bug, 'due_date', ( $t_new === null || $t_new === '' ) ? 1 : strtotime( $t_new . ' 00:00:00' ) );
			if( $t_soll_field > 0 ) {
				custom_field_set_value( $t_soll_field, $t_bug,
					( $t_new === null || $t_new === '' ) ? '' : strtotime( $t_new . ' 00:00:00' ) );
			}
		}
	}

	# Update the measure's mode last (so the simulation read the previous state).
	$t_stichmonat = ( $p_new_modus === 'stichmonat' && (int)$p_new_stichmonat >= 1 && (int)$p_new_stichmonat <= 12 )
		? (int)$p_new_stichmonat : null;
	db_query( 'UPDATE ' . plugin_table( 'massnahme' ) . ' SET faelligkeitsmodus = ' . db_param()
		. ', stichmonat = ' . db_param() . ', date_modified = ' . db_param() . ' WHERE id = ' . db_param(),
		array( $p_new_modus, $t_stichmonat, $t_now, (int)$p_massnahme_id ) );

	return array( 'updated' => count( $t_sim['affected'] ), 'preserved' => (int)$t_sim['preserved'] );
}
