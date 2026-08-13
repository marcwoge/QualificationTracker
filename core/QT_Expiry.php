<?php
/**
 * QualificationTracker – expiry watchdog (F5.1).
 *
 * Transitions proofs whose validity has ended from 'gueltig' to 'abgelaufen'
 * (the derived index and the MantisBT ticket status). Intended for a nightly
 * run; the CLI runner (F5.5) and the run log (F5.6) build on qt_expiry_run().
 *
 * qt_expiry_is_expired() is pure and unit-tested; the sweep reads and writes the
 * database.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Has a valid proof's validity ended as of the given date? Pure.
 *
 * Only proofs currently 'gueltig' with a real end date in the past expire;
 * proofs without an end date (permanent) and open/other states never do.
 *
 * @param array  $p_nachweis
 * @param string $p_today ISO date.
 * @return bool
 */
function qt_expiry_is_expired( array $p_nachweis, $p_today ) {
	return $p_nachweis['status'] === 'gueltig'
		&& $p_nachweis['gueltig_bis'] !== null && $p_nachweis['gueltig_bis'] !== ''
		&& $p_nachweis['gueltig_bis'] < $p_today;
}

/**
 * The proofs that are due to expire as of the key date, joined with person and
 * measure for display (preview).
 *
 * @param string $p_today ISO date.
 * @return array
 */
function qt_expiry_find( $p_today ) {
	$t_query = 'SELECT n.*, p.nachname, p.vorname, p.personalnummer, p.abteilung,'
		. ' m.schluessel, m.bezeichnung'
		. ' FROM ' . plugin_table( 'nachweis' ) . ' n'
		. ' LEFT JOIN ' . plugin_table( 'person' ) . ' p ON p.id = n.person_id'
		. ' LEFT JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = n.massnahme_id'
		. " WHERE n.status = 'gueltig'"
		. ' AND n.gueltig_bis IS NOT NULL AND n.gueltig_bis < ' . db_param()
		. ' ORDER BY n.gueltig_bis, p.nachname, p.vorname';
	$t_result = db_query( $t_query, array( $p_today ) );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Run the expiry sweep: set every proof whose validity has ended to
 * 'abgelaufen', both in the derived index and on the MantisBT ticket.
 * Idempotent – an already-expired proof is not selected again.
 *
 * @param string $p_today ISO date.
 * @return array Summary: expired (count), bug_ids (list of updated tickets).
 */
function qt_expiry_run( $p_today ) {
	$t_rows = qt_expiry_find( $p_today );
	$t_bug_ids = array();
	$t_now = time();
	$t_mantis_status = qt_status_to_mantis( 'abgelaufen' );

	foreach( $t_rows as $t_row ) {
		db_query( 'UPDATE ' . plugin_table( 'nachweis' )
			. " SET status = 'abgelaufen', date_modified = " . db_param() . ' WHERE id = ' . db_param(),
			array( $t_now, (int)$t_row['id'] ) );

		$t_bug = (int)$t_row['bug_id'];
		if( $t_bug > 0 && bug_exists( $t_bug ) ) {
			bug_set_field( $t_bug, 'status', $t_mantis_status );
			$t_bug_ids[] = $t_bug;
		}
	}

	return array( 'expired' => count( $t_rows ), 'bug_ids' => $t_bug_ids );
}
