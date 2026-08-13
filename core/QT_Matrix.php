<?php
/**
 * QualificationTracker – qualification matrix (F4.1).
 *
 * The person × measure matrix built on the derived qt_nachweis index. Each cell
 * carries a state (valid / expiring soon / in progress / expired / missing / not
 * applicable) and the ticket it links to. The gap evaluation is shared with the
 * target/actual report (F2.5) so both stay consistent.
 *
 * qt_matrix_cell() and qt_matrix_warn_days() are pure and unit-tested; the build
 * reads the database. Server-side pagination/aggregation for large populations
 * is F4.3.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The "expiring soon" window in days: the largest positive escalation stage, or
 * 90 as a fallback. Pure.
 *
 * @param mixed $p_eskalation_stufen The eskalation_stufen_tage config value.
 * @return int
 */
function qt_matrix_warn_days( $p_eskalation_stufen ) {
	$t_warn = 0;
	foreach( (array)$p_eskalation_stufen as $t_stufe ) {
		$t_v = (int)$t_stufe;
		if( $t_v > $t_warn ) {
			$t_warn = $t_v;
		}
	}
	return $t_warn > 0 ? $t_warn : 90;
}

/**
 * The matrix cell state for one person × measure, from that person's proofs for
 * the measure. Pure. The caller guarantees the measure is required by the person
 * (otherwise the cell is 'na' and this is not called).
 *
 * States: 'gueltig' (valid, comfortable), 'bald' (valid but expiring within the
 * warning window), 'offen' (in progress), 'abgelaufen' (expired), 'fehlt'
 * (no proof). Mirrors qt_sollist_evaluate() for the gap kinds.
 *
 * @param array  $p_rows      qt_nachweis rows for this person and measure.
 * @param string $p_today     ISO date.
 * @param int    $p_warn_days Expiring-soon window.
 * @return array state, bug_id, rest (days left for valid cells, else null).
 */
function qt_matrix_cell( array $p_rows, $p_today, $p_warn_days ) {
	$t_gap = qt_sollist_evaluate( $p_rows, $p_today );

	if( $t_gap === '' ) {
		$t_valid = null;
		foreach( $p_rows as $t_row ) {
			if( qt_sollist_is_valid( $t_row, $p_today ) ) {
				$t_valid = $t_row;
				break;
			}
		}
		$t_bis = ( $t_valid === null ) ? null : $t_valid['gueltig_bis'];
		$t_rest = ( $t_bis === null || $t_bis === '' ) ? null : QT_DueDateCalculator::day_diff( $p_today, $t_bis );
		$t_state = ( $t_rest !== null && $t_rest <= (int)$p_warn_days ) ? 'bald' : 'gueltig';
		return array(
			'state'  => $t_state,
			'bug_id' => ( $t_valid !== null ) ? (int)$t_valid['bug_id'] : 0,
			'rest'   => $t_rest,
		);
	}

	# A gap: link to the still-open proof ticket, if any.
	$t_bug = 0;
	foreach( $p_rows as $t_row ) {
		if( $t_row['status'] !== 'entfallen' && (int)$t_row['bug_id'] > 0 ) {
			$t_bug = (int)$t_row['bug_id'];
			break;
		}
	}
	return array( 'state' => $t_gap, 'bug_id' => $t_bug, 'rest' => null );
}

/**
 * Does a person's row contain at least one cell in the given state? Pure.
 *
 * @param array  $p_cells One person's cells (massnahme_id => cell).
 * @param string $p_state
 * @return bool
 */
function qt_matrix_row_has_state( array $p_cells, $p_state ) {
	foreach( $p_cells as $t_cell ) {
		if( isset( $t_cell['state'] ) && $t_cell['state'] === $p_state ) {
			return true;
		}
	}
	return false;
}

/**
 * The ids of persons with an active assignment to a profile (map id => true).
 * Active means no end date or an end date not in the past.
 *
 * @param int    $p_profil_id
 * @param string $p_today
 * @return array
 */
function qt_matrix_profil_person_ids( $p_profil_id, $p_today ) {
	$t_result = db_query( 'SELECT DISTINCT person_id FROM ' . plugin_table( 'zuordnung' )
		. ' WHERE profil_id = ' . db_param()
		. ' AND ( gueltig_bis IS NULL OR gueltig_bis >= ' . db_param() . ' )',
		array( (int)$p_profil_id, $p_today ) );
	$t_ids = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_ids[(int)$t_row['person_id']] = true;
	}
	return $t_ids;
}

/**
 * Build the qualification matrix.
 *
 * Rows are the active persons that require at least one (filtered) measure;
 * columns are the union of those measures. Cells exist only for required
 * person × measure pairs – every other pair renders as 'na'.
 *
 * Filters (all optional): 'abteilung' (department), 'profil_id' (only persons
 * assigned to that profile), 'typ' (only measures of that type), 'status'
 * (only persons that have at least one cell in that state).
 *
 * @param string $p_today   ISO date "today".
 * @param array  $p_filters Filter map.
 * @return array persons[], massnahmen[], cells[person_id][massnahme_id], warn_days.
 */
function qt_matrix_build( $p_today, array $p_filters = array() ) {
	$t_warn = qt_matrix_warn_days( plugin_config_get( 'eskalation_stufen_tage' ) );

	$t_abteilung = isset( $p_filters['abteilung'] ) ? (string)$p_filters['abteilung'] : '';
	$t_profil_id = isset( $p_filters['profil_id'] ) ? (int)$p_filters['profil_id'] : 0;
	$t_typ       = isset( $p_filters['typ'] ) ? (string)$p_filters['typ'] : '';
	$t_status    = isset( $p_filters['status'] ) ? (string)$p_filters['status'] : '';

	$t_profil_ids = $t_profil_id > 0 ? qt_matrix_profil_person_ids( $t_profil_id, $p_today ) : null;

	$t_persons = array();
	$t_cells = array();

	foreach( qt_person_load_all( $t_abteilung ) as $t_person ) {
		if( !$t_person['aktiv'] ) {
			continue;
		}
		$t_pid = (int)$t_person['id'];

		if( $t_profil_ids !== null && !isset( $t_profil_ids[$t_pid] ) ) {
			continue;
		}

		$t_required = qt_generator_required_massnahmen( $t_pid, $p_today );
		if( $t_typ !== '' ) {
			$t_required = array_filter( $t_required, function( $m ) use ( $t_typ ) {
				return $m['typ'] === $t_typ;
			} );
		}
		if( empty( $t_required ) ) {
			continue;
		}

		$t_by_massnahme = array();
		foreach( qt_nachweis_load_for_person( $t_pid ) as $t_nw ) {
			$t_by_massnahme[(int)$t_nw['massnahme_id']][] = $t_nw;
		}

		$t_row_cells = array();
		foreach( $t_required as $t_m ) {
			$t_mid = (int)$t_m['id'];
			$t_rows = isset( $t_by_massnahme[$t_mid] ) ? $t_by_massnahme[$t_mid] : array();
			$t_row_cells[$t_mid] = qt_matrix_cell( $t_rows, $p_today, $t_warn );
			$t_row_cells[$t_mid]['massnahme'] = $t_m;
		}

		# Status filter: keep only persons with a matching cell.
		if( $t_status !== '' && !qt_matrix_row_has_state( $t_row_cells, $t_status ) ) {
			continue;
		}

		$t_persons[] = $t_person;
		$t_cells[$t_pid] = $t_row_cells;
	}

	# Columns: the measures actually present in the surviving rows.
	$t_measure_map = array();
	foreach( $t_cells as $t_row_cells ) {
		foreach( $t_row_cells as $t_mid => $t_cell ) {
			$t_measure_map[$t_mid] = $t_cell['massnahme'];
		}
	}
	$t_measures = array_values( $t_measure_map );
	usort( $t_measures, function( $a, $b ) {
		return strcmp( $a['schluessel'], $b['schluessel'] );
	} );

	return array(
		'persons'    => $t_persons,
		'massnahmen' => $t_measures,
		'cells'      => $t_cells,
		'warn_days'  => $t_warn,
	);
}
