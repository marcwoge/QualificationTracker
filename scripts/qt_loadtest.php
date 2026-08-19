<?php
/**
 * QualificationTracker – load test for the target figures (F8.2).
 *
 * Proves the scaling targets: 500 persons, >= 25,000 proof instances, matrix in
 * under 2 seconds. It creates synthetic persons (personnel numbers 800000+), a
 * profile covering the whole active catalogue, one assignment each, and enough
 * proof-index rows to reach the target volume, then times the three heaviest
 * read paths (full matrix, one matrix page, compliance report).
 *
 * Note on "25,000 tickets": by design (decision G5) the matrix reads the derived
 * proof index qt_nachweis, not the MantisBT bug table – one index row per proof
 * instance corresponds to one ticket. The load test therefore populates 25,000
 * index rows, which is exactly what the matrix aggregates; it does not create
 * 25,000 core bug rows (that would benchmark MantisBT core, not this plugin).
 *
 * All data is synthetic (personnel numbers in the 800000 block). Pass --cleanup
 * to remove it afterwards.
 *
 * Usage:
 *   php plugins/QualificationTracker/scripts/qt_loadtest.php [options]
 *     --persons=<n>     number of persons (default 500)
 *     --nachweise=<n>   target proof-index rows (default 25000)
 *     --cleanup         remove all load-test data and exit
 *     --user=<name>     MantisBT user to act as (default administrator)
 *
 * Exit code 0 when every timed path stays under the 2 s budget, 1 otherwise.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

if( php_sapi_name() !== 'cli' ) {
	http_response_code( 403 );
	die( "qt_loadtest.php must be run from the command line.\n" );
}

$g_persons   = 500;
$g_nachweise = 25000;
$g_cleanup   = false;
$g_user      = '';
foreach( $argv as $t_i => $t_arg ) {
	if( $t_i === 0 ) {
		continue;
	}
	if( $t_arg === '--help' || $t_arg === '-h' ) {
		echo "QualificationTracker load test\n";
		echo "Usage: php qt_loadtest.php [--persons=500] [--nachweise=25000] [--cleanup] [--user=<name>]\n";
		exit( 0 );
	} else if( strpos( $t_arg, '--persons=' ) === 0 ) {
		$g_persons = max( 1, (int)substr( $t_arg, 10 ) );
	} else if( strpos( $t_arg, '--nachweise=' ) === 0 ) {
		$g_nachweise = max( 0, (int)substr( $t_arg, 12 ) );
	} else if( $t_arg === '--cleanup' ) {
		$g_cleanup = true;
	} else if( strpos( $t_arg, '--user=' ) === 0 ) {
		$g_user = substr( $t_arg, 7 );
	}
}

require_once( dirname( __DIR__, 3 ) . DIRECTORY_SEPARATOR . 'core.php' );
require_api( 'authentication_api.php' );
require_api( 'config_api.php' );
require_api( 'database_api.php' );

function qt_lt_log( $p_message ) {
	echo '[loadtest] ' . $p_message . "\n";
}

const QT_LT_PROFILE = 'Lasttest';
const QT_LT_PNR_BASE = 800000;

plugin_push_current( 'QualificationTracker' );
$t_user = $g_user !== '' ? $g_user : 'administrator';
if( !auth_attempt_script_login( $t_user ) ) {
	fwrite( STDERR, "qt_loadtest: cannot log in as '" . $t_user . "'.\n" );
	exit( 2 );
}

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Profile.php' );
plugin_require_api( 'core/QT_Assignment.php' );
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_SollIst.php' );
plugin_require_api( 'core/QT_Matrix.php' );

/**
 * Remove all load-test data: the 800000 person block with their assignments and
 * proofs, and the Lasttest profile.
 *
 * @return void
 */
function qt_lt_cleanup() {
	$t_ids = array();
	$t_res = db_query( 'SELECT id FROM ' . plugin_table( 'person' )
		. ' WHERE personalnummer >= ' . db_param() . ' AND personalnummer < ' . db_param(),
		array( (string)QT_LT_PNR_BASE, (string)( QT_LT_PNR_BASE + 100000 ) ) );
	while( $t_row = db_fetch_array( $t_res ) ) {
		$t_ids[] = (int)$t_row['id'];
	}
	foreach( array_chunk( $t_ids, 200 ) as $t_chunk ) {
		if( empty( $t_chunk ) ) {
			continue;
		}
		$t_in = implode( ',', array_map( 'intval', $t_chunk ) );
		db_query( 'DELETE FROM ' . plugin_table( 'nachweis' ) . ' WHERE person_id IN (' . $t_in . ')' );
		db_query( 'DELETE FROM ' . plugin_table( 'zuordnung' ) . ' WHERE person_id IN (' . $t_in . ')' );
		db_query( 'DELETE FROM ' . plugin_table( 'person' ) . ' WHERE id IN (' . $t_in . ')' );
	}
	$t_p = qt_profil_get_by_name( QT_LT_PROFILE, 0 );
	if( $t_p !== false ) {
		qt_profil_delete( (int)$t_p['id'] );
	}
	qt_lt_log( 'cleanup: removed ' . count( $t_ids ) . ' load-test persons and their data' );
}

if( $g_cleanup ) {
	qt_lt_cleanup();
	exit( 0 );
}

$t_setup_start = microtime( true );

# --- Profile over the whole active catalogue --------------------------------
$t_measures = qt_massnahme_load_all( false );
if( empty( $t_measures ) ) {
	fwrite( STDERR, "qt_loadtest: no measures – import the catalogue first (qt_seed.php).\n" );
	exit( 2 );
}
$t_measure_ids = array();
foreach( $t_measures as $t_m ) {
	$t_measure_ids[] = (int)$t_m['id'];
}

$t_existing = qt_profil_get_by_name( QT_LT_PROFILE, 0 );
$t_profile_id = $t_existing !== false ? (int)$t_existing['id']
	: qt_profil_create( array( 'name' => QT_LT_PROFILE, 'beschreibung' => 'Load test', 'aktiv' => 1 ) );
qt_profil_set_massnahmen( $t_profile_id, $t_measure_ids );

# --- Persons + assignments --------------------------------------------------
$t_person_ids = array();
for( $i = 0; $i < $g_persons; $i++ ) {
	$t_pnr = (string)( QT_LT_PNR_BASE + $i );
	$t_data = array(
		'personalnummer' => $t_pnr, 'typ' => 'intern', 'fremdfirma' => '',
		'nachname' => 'Last' . $i, 'vorname' => 'Test', 'abteilung' => 'Abt' . ( $i % 10 ),
		'eintritt' => '2020-01-01', 'austritt' => '', 'vorgesetzter_user_id' => 0,
		'verkuerztes_intervall_bis' => '', 'aktiv' => 1,
	);
	$t_ex = qt_person_get_by_personalnummer( $t_pnr, 0 );
	$t_pid = $t_ex !== false ? (int)$t_ex['id'] : qt_person_create( $t_data );
	$t_person_ids[] = $t_pid;
	if( !qt_zuordnung_open_exists( $t_pid, $t_profile_id, 0 ) ) {
		qt_zuordnung_create( array( 'person_id' => $t_pid, 'profil_id' => $t_profile_id,
			'gueltig_ab' => '2020-01-01', 'gueltig_bis' => '' ) );
	}
}

# --- Proof-index rows (bulk) ------------------------------------------------
# Start from a clean slate for this block so re-runs stay at the target volume.
foreach( array_chunk( $t_person_ids, 200 ) as $t_chunk ) {
	$t_in = implode( ',', array_map( 'intval', $t_chunk ) );
	db_query( 'DELETE FROM ' . plugin_table( 'nachweis' ) . ' WHERE person_id IN (' . $t_in . ')' );
}

$t_pairs = count( $t_person_ids ) * count( $t_measure_ids );
$t_per_pair = $t_pairs > 0 ? (int)max( 1, ceil( $g_nachweise / $t_pairs ) ) : 1;
$t_now = time();

$t_cols = '( person_id, massnahme_id, bug_id, soll_termin, gueltig_bis, status, zyklus, eskalation_stufe, ruht, date_created, date_modified )';
$t_batch = array();
$t_bug = 100000;
$t_written = 0;

$t_flush = function() use ( &$t_batch, $t_cols, &$t_written ) {
	if( empty( $t_batch ) ) {
		return;
	}
	$t_rows_sql = array();
	$t_params = array();
	foreach( $t_batch as $t_vals ) {
		$t_rows_sql[] = '( ' . implode( ', ', array_fill( 0, 11, db_param() ) ) . ' )';
		foreach( $t_vals as $t_v ) {
			$t_params[] = $t_v;
		}
	}
	db_query( 'INSERT INTO ' . plugin_table( 'nachweis' ) . ' ' . $t_cols . ' VALUES ' . implode( ', ', $t_rows_sql ), $t_params );
	$t_written += count( $t_batch );
	$t_batch = array();
};

foreach( $t_person_ids as $t_pid ) {
	foreach( $t_measure_ids as $t_mid ) {
		for( $k = 0; $k < $t_per_pair; $k++ ) {
			$t_bug++;
			$t_year = 2021 + $k;
			$t_is_current = ( $k === $t_per_pair - 1 );
			# Current cycle valid into the future; historical cycles expired.
			$t_status = $t_is_current ? 'gueltig' : 'abgelaufen';
			$t_soll   = $t_year . '-03-01';
			$t_gueltig = $t_is_current ? '2027-03-01' : ( $t_year . '-12-31' );
			$t_batch[] = array( $t_pid, $t_mid, $t_bug, $t_soll, $t_gueltig, $t_status, (string)$t_year, 0, 0, $t_now, $t_now );
			if( count( $t_batch ) >= 500 ) {
				$t_flush();
			}
		}
	}
}
$t_flush();

$t_setup_s = microtime( true ) - $t_setup_start;
qt_lt_log( sprintf( 'setup: %d persons, %d measures, %d proof rows in %.1f s',
	count( $t_person_ids ), count( $t_measure_ids ), $t_written, $t_setup_s ) );

# --- Timed read paths -------------------------------------------------------
$t_today = date( 'Y-m-d' );
$t_budget_ms = 2000;
$g_fail = 0;

$t_time = function( $p_label, callable $p_fn ) use ( $t_budget_ms, &$g_fail ) {
	$t0 = microtime( true );
	$t_result = $p_fn();
	$t_ms = ( microtime( true ) - $t0 ) * 1000;
	$t_ok = $t_ms < $t_budget_ms;
	if( !$t_ok ) {
		$g_fail++;
	}
	qt_lt_log( sprintf( '%-28s %8.1f ms   %s', $p_label, $t_ms, $t_ok ? 'OK (< 2 s)' : 'SLOW (>= 2 s)' ) );
	return $t_result;
};

$t_full = $t_time( 'matrix_build (full, 500)', function() use ( $t_today ) {
	return qt_matrix_build( $t_today, array() );
} );
qt_lt_log( sprintf( '  -> %d persons x %d measures in the built matrix', $t_full['total'], count( $t_full['massnahmen'] ) ) );

$t_time( 'matrix_build (page of 50)', function() use ( $t_today ) {
	return qt_matrix_build( $t_today, array( 'per_page' => 50, 'page' => 1 ) );
} );

$t_time( 'matrix_compliance (audit)', function() use ( $t_today ) {
	return qt_matrix_compliance( $t_today, array() );
} );

$t_actual = (int)db_result( db_query( 'SELECT COUNT(*) FROM ' . plugin_table( 'nachweis' ) ) );
qt_lt_log( 'proof-index rows in table: ' . $t_actual );
qt_lt_log( 'peak memory: ' . round( memory_get_peak_usage( true ) / 1048576, 1 ) . ' MB' );

qt_lt_log( $g_fail === 0
	? 'RESULT: PASS – all timed paths under the 2 s budget'
	: 'RESULT: FAIL – ' . $g_fail . ' path(s) over budget' );
qt_lt_log( 'run with --cleanup to remove the load-test data.' );
exit( $g_fail === 0 ? 0 : 1 );
