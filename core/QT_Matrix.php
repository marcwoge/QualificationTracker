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
 * The SQL fragment + params that restrict a person-joined query to the
 * abteilung and profil filters. The joined person table must be aliased "p".
 *
 * @param string $p_today
 * @param array  $p_filters
 * @return array [sql_fragment, params]
 */
function qt_matrix_person_filter_sql( $p_today, array $p_filters ) {
	$t_sql = '';
	$t_params = array();

	$t_abteilung = isset( $p_filters['abteilung'] ) ? (string)$p_filters['abteilung'] : '';
	if( $t_abteilung !== '' ) {
		$t_sql .= ' AND p.abteilung = ' . db_param();
		$t_params[] = $t_abteilung;
	}

	$t_profil_id = isset( $p_filters['profil_id'] ) ? (int)$p_filters['profil_id'] : 0;
	if( $t_profil_id > 0 ) {
		$t_sql .= ' AND p.id IN ( SELECT person_id FROM ' . plugin_table( 'zuordnung' )
			. ' WHERE profil_id = ' . db_param()
			. ' AND ( gueltig_bis IS NULL OR gueltig_bis >= ' . db_param() . ' ) )';
		$t_params[] = $t_profil_id;
		$t_params[] = $p_today;
	}

	return array( $t_sql, $t_params );
}

/**
 * All required person × measure pairs for the filtered population, in a single
 * aggregate query (avoids the per-person N+1 of the naive build). Returns a map
 * person_id => list of measure rows.
 *
 * @param string $p_today
 * @param array  $p_filters abteilung, profil_id, typ.
 * @return array
 */
function qt_matrix_required_pairs( $p_today, array $p_filters ) {
	list( $t_pfilter, $t_pparams ) = qt_matrix_person_filter_sql( $p_today, $p_filters );

	$t_sql = 'SELECT DISTINCT z.person_id AS qt_person_id, m.*'
		. ' FROM ' . plugin_table( 'zuordnung' ) . ' z'
		. ' JOIN ' . plugin_table( 'profil' ) . ' pr ON pr.id = z.profil_id AND pr.aktiv = 1'
		. ' JOIN ' . plugin_table( 'profil_massnahme' ) . ' pm ON pm.profil_id = z.profil_id'
		. ' JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = pm.massnahme_id AND m.aktiv = 1'
		. ' JOIN ' . plugin_table( 'person' ) . ' p ON p.id = z.person_id AND p.aktiv = 1'
		. ' WHERE ( z.gueltig_bis IS NULL OR z.gueltig_bis >= ' . db_param() . ' )';
	$t_params = array( $p_today );

	$t_typ = isset( $p_filters['typ'] ) ? (string)$p_filters['typ'] : '';
	if( $t_typ !== '' ) {
		$t_sql .= ' AND m.typ = ' . db_param();
		$t_params[] = $t_typ;
	}
	$t_sql .= $t_pfilter;
	$t_params = array_merge( $t_params, $t_pparams );

	$t_result = db_query( $t_sql, $t_params );
	$t_pairs = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_pid = (int)$t_row['qt_person_id'];
		unset( $t_row['qt_person_id'] );
		$t_pairs[$t_pid][] = $t_row;
	}
	return $t_pairs;
}

/**
 * All proofs of the filtered population, in a single aggregate query. Returns a
 * nested map person_id => massnahme_id => list of nachweis rows.
 *
 * @param string $p_today
 * @param array  $p_filters abteilung, profil_id.
 * @return array
 */
function qt_matrix_nachweise( $p_today, array $p_filters ) {
	list( $t_pfilter, $t_pparams ) = qt_matrix_person_filter_sql( $p_today, $p_filters );

	$t_sql = 'SELECT n.* FROM ' . plugin_table( 'nachweis' ) . ' n'
		. ' JOIN ' . plugin_table( 'person' ) . ' p ON p.id = n.person_id AND p.aktiv = 1'
		. ' WHERE 1 = 1' . $t_pfilter;

	$t_result = db_query( $t_sql, $t_pparams );
	$t_map = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_map[(int)$t_row['person_id']][(int)$t_row['massnahme_id']][] = $t_row;
	}
	return $t_map;
}

/**
 * The raw proof records for the filtered population, joined with person and
 * measure, for the CSV raw-data export (F4.4). One row per qt_nachweis entry.
 *
 * @param string $p_today
 * @param array  $p_filters abteilung, profil_id, typ.
 * @return array List of associative rows.
 */
function qt_matrix_raw_rows( $p_today, array $p_filters ) {
	list( $t_pfilter, $t_pparams ) = qt_matrix_person_filter_sql( $p_today, $p_filters );

	$t_sql = 'SELECT p.personalnummer, p.nachname, p.vorname, p.abteilung,'
		. ' m.schluessel, m.bezeichnung, m.typ,'
		. ' n.status, n.soll_termin, n.gueltig_bis, n.zyklus, n.bug_id'
		. ' FROM ' . plugin_table( 'nachweis' ) . ' n'
		. ' JOIN ' . plugin_table( 'person' ) . ' p ON p.id = n.person_id AND p.aktiv = 1'
		. ' JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = n.massnahme_id'
		. ' WHERE 1 = 1' . $t_pfilter;
	$t_params = $t_pparams;

	$t_typ = isset( $p_filters['typ'] ) ? (string)$p_filters['typ'] : '';
	if( $t_typ !== '' ) {
		$t_sql .= ' AND m.typ = ' . db_param();
		$t_params[] = $t_typ;
	}
	$t_sql .= ' ORDER BY p.nachname, p.vorname, m.schluessel';

	$t_result = db_query( $t_sql, $t_params );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Build the qualification matrix.
 *
 * Rows are the active persons that require at least one (filtered) measure;
 * columns are the union of those measures. Cells exist only for required
 * person × measure pairs – every other pair renders as 'na'.
 *
 * Data is gathered with three aggregate queries (persons, required pairs,
 * proofs) instead of two queries per person, so the build stays flat as the
 * population grows (F4.3). Rows are then paginated for rendering.
 *
 * Filters (all optional): 'abteilung', 'profil_id', 'typ', 'status'
 * (keep only persons with at least one cell in that state), plus pagination
 * 'page' (1-based) and 'per_page' (0 = all).
 *
 * @param string $p_today   ISO date "today".
 * @param array  $p_filters Filter map.
 * @return array persons[] (page slice), massnahmen[], cells[pid][mid], warn_days,
 *               total, page, per_page, page_count.
 */
function qt_matrix_build( $p_today, array $p_filters = array() ) {
	$t_warn = qt_matrix_warn_days( plugin_config_get( 'eskalation_stufen_tage' ) );

	$t_abteilung = isset( $p_filters['abteilung'] ) ? (string)$p_filters['abteilung'] : '';
	$t_status    = isset( $p_filters['status'] ) ? (string)$p_filters['status'] : '';
	$t_per_page  = isset( $p_filters['per_page'] ) ? max( 0, (int)$p_filters['per_page'] ) : 0;
	$t_page      = isset( $p_filters['page'] ) ? max( 1, (int)$p_filters['page'] ) : 1;

	$t_pairs = qt_matrix_required_pairs( $p_today, $p_filters );
	$t_nachweise = qt_matrix_nachweise( $p_today, $p_filters );

	# Assemble rows in person order (qt_person_load_all is ordered by name).
	$t_persons = array();
	$t_cells = array();
	foreach( qt_person_load_all( $t_abteilung ) as $t_person ) {
		$t_pid = (int)$t_person['id'];
		if( !isset( $t_pairs[$t_pid] ) ) {
			continue;
		}

		$t_row_cells = array();
		foreach( $t_pairs[$t_pid] as $t_m ) {
			$t_mid = (int)$t_m['id'];
			$t_rows = isset( $t_nachweise[$t_pid][$t_mid] ) ? $t_nachweise[$t_pid][$t_mid] : array();
			$t_row_cells[$t_mid] = qt_matrix_cell( $t_rows, $p_today, $t_warn );
			$t_row_cells[$t_mid]['massnahme'] = $t_m;
		}

		if( $t_status !== '' && !qt_matrix_row_has_state( $t_row_cells, $t_status ) ) {
			continue;
		}

		$t_persons[] = $t_person;
		$t_cells[$t_pid] = $t_row_cells;
	}

	# Columns: the measures present across all surviving rows (stable across pages).
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

	# Paginate the rows.
	$t_total = count( $t_persons );
	$t_page_count = ( $t_per_page > 0 ) ? (int)max( 1, ceil( $t_total / $t_per_page ) ) : 1;
	if( $t_page > $t_page_count ) {
		$t_page = $t_page_count;
	}
	if( $t_per_page > 0 ) {
		$t_persons = array_slice( $t_persons, ( $t_page - 1 ) * $t_per_page, $t_per_page );
	}

	return array(
		'persons'    => $t_persons,
		'massnahmen' => $t_measures,
		'cells'      => $t_cells,
		'warn_days'  => $t_warn,
		'total'      => $t_total,
		'page'       => $t_page,
		'per_page'   => $t_per_page,
		'page_count' => $t_page_count,
	);
}
