<?php
/**
 * QualificationTracker – demo seed for the test environment (F8.1).
 *
 * Populates the plugin with reproducible, entirely synthetic demo data so the
 * Docker sandbox shows something meaningful right after install:
 *   - the bundled example catalogue (>= 8 measures),
 *   - 50 synthetic persons (personnel numbers 900000+, no real people),
 *   - three activity profiles and a person-to-profile assignment each.
 *
 * The data is synthetic by construction (personnel numbers in the 900000 block,
 * placeholder names) so the repository never carries personal data. The script
 * is idempotent: persons are matched by personnel number and profiles by name,
 * so re-running it updates rather than duplicates. Pass --reset to wipe the
 * plugin's master data first for a clean demo.
 *
 * Proof tickets are NOT generated here: that needs a configured target project
 * and is the generator's job (Manage -> QualificationTracker, or the dry-run /
 * generate pages). Once a target project is set, run the generator to fan the
 * assignments out into tickets.
 *
 * Usage:
 *   php plugins/QualificationTracker/scripts/qt_seed.php [--reset] [--user=<name>]
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

if( php_sapi_name() !== 'cli' ) {
	http_response_code( 403 );
	die( "qt_seed.php must be run from the command line.\n" );
}

$g_reset = false;
$g_user  = '';
foreach( $argv as $t_i => $t_arg ) {
	if( $t_i === 0 ) {
		continue;
	}
	if( $t_arg === '--help' || $t_arg === '-h' ) {
		echo "QualificationTracker demo seeder\n";
		echo "Usage: php qt_seed.php [--reset] [--user=<username>]\n";
		echo "  --reset   delete the plugin's master data before seeding\n";
		echo "  --user    MantisBT user to act as (default: administrator)\n";
		exit( 0 );
	} else if( $t_arg === '--reset' ) {
		$g_reset = true;
	} else if( strpos( $t_arg, '--user=' ) === 0 ) {
		$g_user = substr( $t_arg, 7 );
	}
}

require_once( dirname( __DIR__, 3 ) . DIRECTORY_SEPARATOR . 'core.php' );
require_api( 'authentication_api.php' );
require_api( 'config_api.php' );
require_api( 'database_api.php' );

function qt_seed_log( $p_message ) {
	echo '[seed] ' . $p_message . "\n";
}

plugin_push_current( 'QualificationTracker' );

$t_user = $g_user !== '' ? $g_user : 'administrator';
if( !auth_attempt_script_login( $t_user ) ) {
	fwrite( STDERR, "qt_seed: cannot log in as '" . $t_user . "'.\n" );
	exit( 2 );
}

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_CatalogImport.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Profile.php' );
plugin_require_api( 'core/QT_Assignment.php' );

/* -------------------------------------------------------------------------- *
 *  Optional reset
 * -------------------------------------------------------------------------- */
if( $g_reset ) {
	foreach( array( 'historie', 'loeschung', 'lauf', 'teilnehmer', 'nachweis',
		'massnahme_vorbedingung', 'veranstaltung', 'zuordnung', 'profil_massnahme',
		'profil', 'person', 'massnahme' ) as $t_tbl ) {
		db_query( 'DELETE FROM ' . plugin_table( $t_tbl ) );
	}
	qt_seed_log( 'reset: plugin master data cleared' );
}

/* -------------------------------------------------------------------------- *
 *  Measures – import the bundled example catalogue
 * -------------------------------------------------------------------------- */
$t_yaml = file_get_contents( qt_catalog_seed_path() );
$t_rows = qt_yaml_parse_simple( (string)$t_yaml );
$t_cat  = qt_catalog_import( $t_rows, true );
qt_seed_log( sprintf( 'measures: %d created, %d updated, %d skipped, %d errors',
	$t_cat['created'], $t_cat['updated'], $t_cat['skipped'], count( $t_cat['errors'] ) ) );

/* -------------------------------------------------------------------------- *
 *  Persons – 50 synthetic records (personnel numbers 900000+)
 * -------------------------------------------------------------------------- */
$t_vornamen = array( 'Alex', 'Bianca', 'Cem', 'Dana', 'Erik', 'Farah', 'Georg', 'Hanna',
	'Ingo', 'Jana', 'Kai', 'Lena', 'Mats', 'Nora', 'Ole', 'Pia', 'Quirin', 'Rosa',
	'Sven', 'Tara', 'Udo', 'Vera', 'Wim', 'Xena', 'Yannis', 'Zoe' );
$t_nachnamen = array( 'Adler', 'Berg', 'Cramer', 'Dorn', 'Ebert', 'Frank', 'Groß', 'Huber',
	'Ilic', 'Jung', 'Klein', 'Lang', 'Meyer', 'Nowak', 'Otto', 'Peters', 'Roth',
	'Schmitz', 'Thiel', 'Ulrich', 'Voss', 'Weber', 'Ziegler' );
$t_abteilungen = array( 'Produktion', 'Logistik', 'Instandhaltung', 'Labor', 'Verwaltung', 'Versand' );

$t_created = 0;
$t_updated = 0;
for( $i = 0; $i < 50; $i++ ) {
	$t_pnr = (string)( 900000 + $i );
	$t_typ = ( $i % 10 === 9 ) ? 'fremdfirma' : 'intern';
	$t_data = array(
		'personalnummer'            => $t_pnr,
		'typ'                       => $t_typ,
		'fremdfirma'                => $t_typ === 'intern' ? '' : 'Fremd GmbH',
		'nachname'                  => $t_nachnamen[$i % count( $t_nachnamen )],
		'vorname'                   => $t_vornamen[$i % count( $t_vornamen )],
		'abteilung'                 => $t_abteilungen[$i % count( $t_abteilungen )],
		'eintritt'                  => '2021-01-01',
		'austritt'                  => '',
		'vorgesetzter_user_id'      => 0,
		'verkuerztes_intervall_bis' => '',
		'aktiv'                     => 1,
	);
	$t_existing = qt_person_get_by_personalnummer( $t_pnr, 0 );
	if( $t_existing !== false ) {
		qt_person_update( (int)$t_existing['id'], $t_data );
		$t_updated++;
	} else {
		qt_person_create( $t_data );
		$t_created++;
	}
}
qt_seed_log( sprintf( 'persons: %d created, %d updated (total 50)', $t_created, $t_updated ) );

/* -------------------------------------------------------------------------- *
 *  Profiles – three, built from the imported measures (generic, key-agnostic)
 * -------------------------------------------------------------------------- */
$t_measures = qt_massnahme_load_all( false );
$t_ids = array();
foreach( $t_measures as $t_m ) {
	$t_ids[] = (int)$t_m['id'];
}

# Nested measure sets so soll counts differ across profiles.
$t_profiles = array(
	'Alle Beschäftigten' => array_slice( $t_ids, 0, min( 2, count( $t_ids ) ) ),
	'Produktion'         => array_slice( $t_ids, 0, min( 5, count( $t_ids ) ) ),
	'Instandhaltung'     => $t_ids,
);

$t_profile_ids = array();
foreach( $t_profiles as $t_name => $t_massnahme_ids ) {
	$t_existing = qt_profil_get_by_name( $t_name, 0 );
	if( $t_existing !== false ) {
		$t_pid = (int)$t_existing['id'];
		qt_profil_update( $t_pid, array( 'name' => $t_name, 'beschreibung' => 'Demo-Profil', 'aktiv' => 1 ) );
	} else {
		$t_pid = qt_profil_create( array( 'name' => $t_name, 'beschreibung' => 'Demo-Profil', 'aktiv' => 1 ) );
	}
	qt_profil_set_massnahmen( $t_pid, $t_massnahme_ids );
	$t_profile_ids[] = $t_pid;
}
qt_seed_log( sprintf( 'profiles: %d ensured', count( $t_profile_ids ) ) );

/* -------------------------------------------------------------------------- *
 *  Assignments – each person to one profile, round-robin
 * -------------------------------------------------------------------------- */
$t_persons = qt_person_load_all();
$t_assigned = 0;
$t_idx = 0;
foreach( $t_persons as $t_p ) {
	if( empty( $t_profile_ids ) ) {
		break;
	}
	$t_pid = $t_profile_ids[$t_idx % count( $t_profile_ids )];
	$t_idx++;
	if( qt_zuordnung_open_exists( (int)$t_p['id'], $t_pid, 0 ) ) {
		continue;
	}
	qt_zuordnung_create( array(
		'person_id'   => (int)$t_p['id'],
		'profil_id'   => $t_pid,
		'gueltig_ab'  => '2021-01-01',
		'gueltig_bis' => '',
	) );
	$t_assigned++;
}
qt_seed_log( sprintf( 'assignments: %d created', $t_assigned ) );

qt_seed_log( 'done. Set a target project and run the generator to create proof tickets.' );
exit( 0 );
