<?php
/**
 * QualificationTracker – suspension note ("Ruhensvermerk") for dependent
 * appointments (F5.4).
 *
 * A safety-relevant qualification that lapses (no valid proof) must suspend the
 * appointments (type BE) that depend on it: the person may no longer operate the
 * equipment. This pass sets a suspension note and status on the dependent
 * appointment's ticket, and lifts it again once the prerequisite is valid.
 *
 * qt_ruhen_should_rest() is pure and unit-tested; the run reads and writes the
 * database.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Should an appointment rest, given the state of its prerequisites? Pure.
 *
 * It rests as soon as any safety-relevant prerequisite is not valid.
 *
 * @param array $p_prereqs List of ['sicherheitsrelevant' => bool, 'valid' => bool].
 * @return bool
 */
function qt_ruhen_should_rest( array $p_prereqs ) {
	foreach( $p_prereqs as $t_p ) {
		if( !empty( $t_p['sicherheitsrelevant'] ) && empty( $t_p['valid'] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The appointment proofs (type BE) that could rest: non-cancelled, with a
 * ticket, joined with person and measure.
 *
 * @return array
 */
function qt_ruhen_candidates() {
	$t_query = 'SELECT n.*, p.nachname, p.vorname, m.schluessel, m.bezeichnung'
		. ' FROM ' . plugin_table( 'nachweis' ) . ' n'
		. ' LEFT JOIN ' . plugin_table( 'person' ) . ' p ON p.id = n.person_id'
		. ' JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = n.massnahme_id'
		. " WHERE m.typ = 'BE' AND n.status <> 'entfallen' AND n.bug_id > 0"
		. ' ORDER BY p.nachname, p.vorname';
	$t_result = db_query( $t_query );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * The prerequisite states of an appointment for a person: for each prerequisite
 * measure, whether it is safety-relevant and whether the person currently has a
 * valid proof for it.
 *
 * @param int    $p_massnahme_id Appointment measure.
 * @param array  $p_person_nachweise The person's proofs grouped by measure id.
 * @param string $p_today
 * @return array List of ['sicherheitsrelevant' => bool, 'valid' => bool].
 */
function qt_ruhen_prereq_states( $p_massnahme_id, array $p_person_nachweise, $p_today ) {
	$t_states = array();
	foreach( qt_vorbedingung_get_for( $p_massnahme_id ) as $t_prereq_id ) {
		$t_m = qt_massnahme_get( $t_prereq_id );
		if( $t_m === false ) {
			continue;
		}
		$t_rows = isset( $p_person_nachweise[$t_prereq_id] ) ? $p_person_nachweise[$t_prereq_id] : array();
		$t_states[] = array(
			'sicherheitsrelevant' => !empty( $t_m['sicherheitsrelevant'] ),
			'valid'               => ( qt_sollist_evaluate( $t_rows, $p_today ) === '' ),
		);
	}
	return $t_states;
}

/**
 * Run the suspension pass. Suspends dependent appointments whose safety-relevant
 * prerequisite has lapsed, and lifts the suspension once all such prerequisites
 * are valid again. Idempotent via the qt_nachweis.ruht flag.
 *
 * @param string $p_today ISO date.
 * @return array Summary: suspended, lifted.
 */
function qt_ruhen_run( $p_today ) {
	$t_ruhens_status = (int)plugin_config_get( 'ruhens_status' );
	$t_summary = array( 'suspended' => 0, 'lifted' => 0 );
	$t_cache = array();

	foreach( qt_ruhen_candidates() as $t_row ) {
		$t_bug = (int)$t_row['bug_id'];
		if( !bug_exists( $t_bug ) ) {
			continue;
		}
		$t_person_id = (int)$t_row['person_id'];

		if( !isset( $t_cache[$t_person_id] ) ) {
			$t_by_massnahme = array();
			foreach( qt_nachweis_load_for_person( $t_person_id ) as $t_nw ) {
				$t_by_massnahme[(int)$t_nw['massnahme_id']][] = $t_nw;
			}
			$t_cache[$t_person_id] = $t_by_massnahme;
		}

		$t_states = qt_ruhen_prereq_states( (int)$t_row['massnahme_id'], $t_cache[$t_person_id], $p_today );
		$t_rest = qt_ruhen_should_rest( $t_states );
		$t_is_ruht = !empty( $t_row['ruht'] );

		if( $t_rest && !$t_is_ruht ) {
			db_query( 'UPDATE ' . plugin_table( 'nachweis' ) . ' SET ruht = 1, date_modified = ' . db_param()
				. ' WHERE id = ' . db_param(), array( time(), (int)$t_row['id'] ) );
			bug_set_field( $t_bug, 'status', $t_ruhens_status );
			bugnote_add( $t_bug, plugin_lang_get( 'ruhen_note_set' ), '0:00', false, BUGNOTE );
			$t_summary['suspended']++;
		} else if( !$t_rest && $t_is_ruht ) {
			db_query( 'UPDATE ' . plugin_table( 'nachweis' ) . ' SET ruht = 0, date_modified = ' . db_param()
				. ' WHERE id = ' . db_param(), array( time(), (int)$t_row['id'] ) );
			bug_set_field( $t_bug, 'status', qt_status_to_mantis( $t_row['status'] ) );
			bugnote_add( $t_bug, plugin_lang_get( 'ruhen_note_lifted' ), '0:00', false, BUGNOTE );
			$t_summary['lifted']++;
		}
	}

	return $t_summary;
}
