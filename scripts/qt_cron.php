<?php
/**
 * QualificationTracker – CLI cron runner (F5.5).
 *
 * Bundles the four nightly automation passes into one command for cron or a
 * systemd timer, with exit codes suitable for monitoring:
 *
 *   0  success – all requested passes ran without error
 *   1  at least one pass raised an error (the others still ran)
 *   2  precondition failure – could not establish the cron user context
 *   3  bad usage – unknown --only pass
 *
 * The passes, in order:
 *   expiry        (F5.1) valid proofs past their end date -> abgelaufen
 *   ruhen         (F5.4) suspend/lift dependent appointments
 *   reactivation  (F5.2) hold/wake time-limited renewal tickets
 *   escalation    (F5.3) staged notifications around the due date
 *
 * Usage:
 *   php plugins/QualificationTracker/scripts/qt_cron.php [options]
 *     --dry-run            report what each pass would do; change nothing
 *     --only=<pass>        run only one pass (expiry|ruhen|reactivation|escalation)
 *     --user=<username>    act as this MantisBT user (overrides config cron_user)
 *     --help, -h           show this help
 *
 * Recommended crontab entry (daily at 02:00):
 *   0 2 * * * php /path/to/mantis/plugins/QualificationTracker/scripts/qt_cron.php
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

# --- CLI guard – refuse to run from a web request. --------------------------
if( php_sapi_name() !== 'cli' ) {
	http_response_code( 403 );
	die( "qt_cron.php must be run from the command line.\n" );
}

# --- Argument parsing (before bootstrapping MantisBT). ----------------------
$g_dry_run = false;
$g_only    = '';
$g_user    = '';
foreach( $argv as $t_i => $t_arg ) {
	if( $t_i === 0 ) {
		continue;
	}
	if( $t_arg === '--help' || $t_arg === '-h' ) {
		echo "QualificationTracker cron runner\n";
		echo "Usage: php qt_cron.php [--dry-run] [--only=<pass>] [--user=<username>]\n";
		echo "  passes: expiry, ruhen, reactivation, escalation\n";
		echo "  exit codes: 0 ok, 1 pass error, 2 no user context, 3 bad usage\n";
		exit( 0 );
	} else if( $t_arg === '--dry-run' ) {
		$g_dry_run = true;
	} else if( strpos( $t_arg, '--only=' ) === 0 ) {
		$g_only = substr( $t_arg, 7 );
	} else if( strpos( $t_arg, '--user=' ) === 0 ) {
		$g_user = substr( $t_arg, 7 );
	}
}

# --- Bootstrap MantisBT core. The script lives in ---------------------------
#   <mantis>/plugins/QualificationTracker/scripts/qt_cron.php
# so the MantisBT root is three directories up.
require_once( dirname( __DIR__, 3 ) . DIRECTORY_SEPARATOR . 'core.php' );

require_api( 'authentication_api.php' );
require_api( 'bug_api.php' );
require_api( 'bugnote_api.php' );
require_api( 'config_api.php' );
require_api( 'database_api.php' );

/**
 * Log a line to stdout with an ISO timestamp.
 *
 * @param string $p_message
 * @return void
 */
function qt_cron_log( $p_message ) {
	echo '[' . date( 'Y-m-d H:i:s' ) . '] ' . $p_message . "\n";
}

# --- Establish the plugin context and the acting user. ----------------------
plugin_push_current( 'QualificationTracker' );

$t_user = $g_user !== '' ? $g_user : (string)plugin_config_get( 'cron_user' );
if( !auth_attempt_script_login( $t_user ) ) {
	fwrite( STDERR, "qt_cron: cannot establish the cron user context for '" . $t_user . "'.\n" );
	exit( 2 );
}

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_CustomFields.php' );
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_SollIst.php' );
plugin_require_api( 'core/QT_Matrix.php' );
plugin_require_api( 'core/QT_Integration.php' );
plugin_require_api( 'core/QT_Expiry.php' );
plugin_require_api( 'core/QT_Reactivation.php' );
plugin_require_api( 'core/QT_Escalation.php' );
plugin_require_api( 'core/QT_Ruhen.php' );

$t_today = date( 'Y-m-d' );

# --- Dry-run preview helpers (read-only). -----------------------------------
$t_dry = array(
	'expiry' => function() use ( $t_today ) {
		return array( 'would_expire' => count( qt_expiry_find( $t_today ) ) );
	},
	'ruhen' => function() use ( $t_today ) {
		$t_suspend = 0; $t_lift = 0; $t_cache = array();
		foreach( qt_ruhen_candidates() as $t_c ) {
			$t_pid = (int)$t_c['person_id'];
			if( !isset( $t_cache[$t_pid] ) ) {
				$t_by = array();
				foreach( qt_nachweis_load_for_person( $t_pid ) as $t_nw ) {
					$t_by[(int)$t_nw['massnahme_id']][] = $t_nw;
				}
				$t_cache[$t_pid] = $t_by;
			}
			if( !bug_exists( (int)$t_c['bug_id'] ) ) { continue; }
			$t_rest = qt_ruhen_should_rest( qt_ruhen_prereq_states( (int)$t_c['massnahme_id'], $t_cache[$t_pid], $t_today ) );
			if( $t_rest && empty( $t_c['ruht'] ) ) { $t_suspend++; }
			else if( !$t_rest && !empty( $t_c['ruht'] ) ) { $t_lift++; }
		}
		return array( 'would_suspend' => $t_suspend, 'would_lift' => $t_lift );
	},
	'reactivation' => function() use ( $t_today ) {
		$t_reveille = qt_integration_reveille();
		$t_held = qt_reactivation_held_status( $t_reveille );
		$t_stufen = plugin_config_get( 'eskalation_stufen_tage' );
		$t_defer = 0; $t_react = 0;
		foreach( qt_reactivation_candidates() as $t_c ) {
			$t_bug = (int)$t_c['bug_id'];
			if( !bug_exists( $t_bug ) ) { continue; }
			$t_wake = qt_reactivation_wake_date( $t_c['soll_termin'], qt_reactivation_vorlauf( $t_c, $t_stufen ) );
			$t_status = (int)bug_get_field( $t_bug, 'status' );
			if( qt_reactivation_is_dormant( $t_wake, $t_today ) ) {
				if( $t_status !== $t_held ) { $t_defer++; }
			} else if( !$t_reveille && $t_status === $t_held ) {
				$t_react++;
			}
		}
		return array( 'would_defer' => $t_defer, 'would_reactivate' => $t_react );
	},
	'escalation' => function() use ( $t_today ) {
		$t_stufen = (array)plugin_config_get( 'eskalation_stufen_tage' );
		$t_n = 0;
		foreach( qt_eskalation_candidates() as $t_c ) {
			if( !bug_exists( (int)$t_c['bug_id'] ) ) { continue; }
			if( qt_eskalation_reached_count( $t_c['soll_termin'], $t_today, $t_stufen ) > (int)$t_c['eskalation_stufe'] ) {
				$t_n++;
			}
		}
		return array( 'would_notify' => $t_n );
	},
);

# --- The passes. ------------------------------------------------------------
$t_passes = array(
	'expiry'       => function() use ( $t_today ) { return qt_expiry_run( $t_today ); },
	'ruhen'        => function() use ( $t_today ) { return qt_ruhen_run( $t_today ); },
	'reactivation' => function() use ( $t_today ) { return qt_reactivation_run( $t_today ); },
	'escalation'   => function() use ( $t_today ) { return qt_eskalation_run( $t_today ); },
);

if( $g_only !== '' && !isset( $t_passes[$g_only] ) ) {
	fwrite( STDERR, "qt_cron: unknown pass '" . $g_only . "'.\n" );
	exit( 3 );
}

qt_cron_log( 'qt_cron start' . ( $g_dry_run ? ' (dry-run)' : '' ) . ' as ' . $t_user );

$t_exit = 0;
foreach( $t_passes as $t_name => $t_fn ) {
	if( $g_only !== '' && $g_only !== $t_name ) {
		continue;
	}
	try {
		$t_result = $g_dry_run ? $t_dry[$t_name]() : $t_fn();
		qt_cron_log( $t_name . ': ' . json_encode( $t_result ) );
	} catch( Exception $e ) {
		qt_cron_log( $t_name . ' ERROR: ' . $e->getMessage() );
		$t_exit = 1;
	}
}

qt_cron_log( 'qt_cron done (exit ' . $t_exit . ')' );
exit( $t_exit );
