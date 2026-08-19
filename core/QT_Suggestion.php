<?php
/**
 * QualificationTracker – group-event suggestions (Terminvorschlag, F3.7).
 *
 * Looks at the persons who currently need a given measure (missing, expired,
 * in progress, or expiring soon) and proposes bundling them into group events
 * (Veranstaltungen): a suggested date and, respecting an optimal capacity, one
 * or more sessions with their participant lists. A planning aid – it writes
 * nothing; the safety officer turns a proposal into an actual event.
 *
 * The bucketing and date helpers are pure and unit-tested; the build reuses the
 * matrix aggregates (required pairs + proof index) to find the candidates.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Whether a matrix cell state calls for action (i.e. the person should be booked
 * into an event). Valid cells and 'na' do not. Pure.
 *
 * @param string $p_state
 * @return bool
 */
function qt_vorschlag_actionable( $p_state ) {
	return in_array( $p_state, array( 'abgelaufen', 'fehlt', 'offen', 'bald' ), true );
}

/**
 * Urgency rank of an actionable state (higher = more urgent). Pure.
 *
 * @param string $p_state
 * @return int
 */
function qt_vorschlag_state_rank( $p_state ) {
	$t_rank = array( 'abgelaufen' => 3, 'fehlt' => 2, 'offen' => 1, 'bald' => 0 );
	return isset( $t_rank[$p_state] ) ? $t_rank[$p_state] : -1;
}

/**
 * Split an (already ordered) candidate list into sessions of at most the given
 * capacity. A capacity of 0 or less means a single unlimited session. Pure.
 *
 * @param array $p_candidates
 * @param int   $p_capacity
 * @return array List of sessions (each a list of candidates).
 */
function qt_vorschlag_sessions( array $p_candidates, $p_capacity ) {
	$t_cap = (int)$p_capacity;
	$t_list = array_values( $p_candidates );
	if( $t_cap <= 0 || empty( $t_list ) ) {
		return empty( $t_list ) ? array() : array( $t_list );
	}
	return array_chunk( $t_list, $t_cap );
}

/**
 * The earliest relevant date among a person's proofs for a measure: the earliest
 * target date (soll_termin), else the earliest validity end. Pure. '' when none.
 *
 * @param array $p_rows qt_nachweis rows.
 * @return string ISO date or ''.
 */
function qt_vorschlag_target_date( array $p_rows ) {
	$t_soll = null;
	$t_bis  = null;
	foreach( $p_rows as $t_row ) {
		$t_s = isset( $t_row['soll_termin'] ) ? (string)$t_row['soll_termin'] : '';
		if( preg_match( '/^\d{4}-\d{2}-\d{2}/', $t_s ) ) {
			$t_s = substr( $t_s, 0, 10 );
			if( $t_soll === null || $t_s < $t_soll ) { $t_soll = $t_s; }
		}
		$t_g = isset( $t_row['gueltig_bis'] ) ? (string)$t_row['gueltig_bis'] : '';
		if( preg_match( '/^\d{4}-\d{2}-\d{2}/', $t_g ) ) {
			$t_g = substr( $t_g, 0, 10 );
			if( $t_bis === null || $t_g < $t_bis ) { $t_bis = $t_g; }
		}
	}
	if( $t_soll !== null ) { return $t_soll; }
	return $t_bis !== null ? $t_bis : '';
}

/**
 * Propose an event date: near the earliest due date, but never sooner than a
 * minimum organising lead from today. Pure. A due date in the past (or none)
 * yields "as soon as possible" = today + lead.
 *
 * @param string $p_earliest ISO date or ''.
 * @param string $p_today    ISO date.
 * @param int    $p_lead     Minimum organising lead in days.
 * @return string ISO date.
 */
function qt_vorschlag_termin( $p_earliest, $p_today, $p_lead ) {
	$t_min = QT_DueDateCalculator::add_days( substr( (string)$p_today, 0, 10 ), max( 0, (int)$p_lead ) );
	$t_e = (string)$p_earliest;
	if( preg_match( '/^\d{4}-\d{2}-\d{2}/', $t_e ) && substr( $t_e, 0, 10 ) >= $t_min ) {
		return substr( $t_e, 0, 10 );
	}
	return $t_min;
}

/**
 * Build the event suggestions for the filtered population.
 *
 * @param string $p_today    ISO date.
 * @param int    $p_capacity Optimal participants per session (0 = unlimited).
 * @param array  $p_filters  abteilung, profil_id, typ.
 * @param int    $p_lead     Minimum organising lead in days (default 14).
 * @return array List of proposals, most urgent first. Each: measure, total,
 *               termin, sessions (each a list of candidate rows).
 */
function qt_vorschlag_build( $p_today, $p_capacity, array $p_filters = array(), $p_lead = 14 ) {
	$t_warn = qt_matrix_warn_days( plugin_config_get( 'eskalation_stufen_tage' ) );
	$t_abteilung = isset( $p_filters['abteilung'] ) ? (string)$p_filters['abteilung'] : '';

	$t_pairs = qt_matrix_required_pairs( $p_today, $p_filters );
	$t_nachweise = qt_matrix_nachweise( $p_today, $p_filters );

	$t_persons = array();
	foreach( qt_person_load_all( $t_abteilung ) as $t_p ) {
		$t_persons[(int)$t_p['id']] = $t_p;
	}

	# Gather actionable candidates per measure.
	$t_by_measure = array();
	foreach( $t_pairs as $t_pid => $t_measures ) {
		if( !isset( $t_persons[$t_pid] ) ) {
			continue;
		}
		foreach( $t_measures as $t_m ) {
			$t_mid = (int)$t_m['id'];
			$t_rows = isset( $t_nachweise[$t_pid][$t_mid] ) ? $t_nachweise[$t_pid][$t_mid] : array();
			$t_state = qt_matrix_cell( $t_rows, $p_today, $t_warn )['state'];
			if( !qt_vorschlag_actionable( $t_state ) ) {
				continue;
			}
			if( !isset( $t_by_measure[$t_mid] ) ) {
				$t_by_measure[$t_mid] = array( 'measure' => $t_m, 'candidates' => array() );
			}
			$t_by_measure[$t_mid]['candidates'][] = array(
				'person'      => $t_persons[$t_pid],
				'state'       => $t_state,
				'rank'        => qt_vorschlag_state_rank( $t_state ),
				'target_date' => qt_vorschlag_target_date( $t_rows ),
			);
		}
	}

	# Turn each measure's candidates into a proposal.
	$t_proposals = array();
	foreach( $t_by_measure as $t_info ) {
		$t_cands = $t_info['candidates'];

		# Order by urgency, then earliest date, then name.
		usort( $t_cands, function( $a, $b ) {
			if( $a['rank'] !== $b['rank'] ) { return $b['rank'] - $a['rank']; }
			$t_ad = $a['target_date'] !== '' ? $a['target_date'] : '9999-12-31';
			$t_bd = $b['target_date'] !== '' ? $b['target_date'] : '9999-12-31';
			if( $t_ad !== $t_bd ) { return strcmp( $t_ad, $t_bd ); }
			return strcmp( (string)$a['person']['nachname'], (string)$b['person']['nachname'] );
		} );

		# Earliest concrete due date across candidates drives the suggested date.
		$t_earliest = '';
		foreach( $t_cands as $t_c ) {
			if( $t_c['target_date'] !== '' && ( $t_earliest === '' || $t_c['target_date'] < $t_earliest ) ) {
				$t_earliest = $t_c['target_date'];
			}
		}

		$t_max_rank = 0;
		foreach( $t_cands as $t_c ) {
			if( $t_c['rank'] > $t_max_rank ) { $t_max_rank = $t_c['rank']; }
		}

		$t_proposals[] = array(
			'measure'  => $t_info['measure'],
			'total'    => count( $t_cands ),
			'termin'   => qt_vorschlag_termin( $t_earliest, $p_today, $p_lead ),
			'max_rank' => $t_max_rank,
			'sessions' => qt_vorschlag_sessions( $t_cands, $p_capacity ),
		);
	}

	# Most urgent (highest rank), then largest group, first.
	usort( $t_proposals, function( $a, $b ) {
		if( $a['max_rank'] !== $b['max_rank'] ) { return $b['max_rank'] - $a['max_rank']; }
		if( $a['total'] !== $b['total'] ) { return $b['total'] - $a['total']; }
		return strcmp( (string)$a['measure']['schluessel'], (string)$b['measure']['schluessel'] );
	} );

	return $t_proposals;
}
