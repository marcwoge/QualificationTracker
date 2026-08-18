<?php
/**
 * QualificationTracker – matrix / raw-data CSV export (F4.4).
 *
 * Streams the qualification matrix or the underlying proof records as CSV,
 * honouring the current filters. Dependency-free (fputcsv, no library). The
 * semicolon delimiter and UTF-8 BOM make the file open cleanly in German Excel.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'config_api.php' );
require_api( 'database_api.php' );
require_api( 'gpc_api.php' );
require_api( 'lang_api.php' );

auth_reauthenticate();
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'view' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_CustomFields.php' );
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_SollIst.php' );
plugin_require_api( 'core/QT_Matrix.php' );

$f_format    = gpc_get_string( 'format', 'matrix' );
$f_abteilung = gpc_get_string( 'abteilung', '' );
$f_profil    = gpc_get_int( 'profil_id', 0 );
$f_typ       = gpc_get_string( 'typ', '' );
$f_status    = gpc_get_string( 'status', '' );
$t_today     = date( 'Y-m-d' );

$t_filters = array(
	'abteilung' => $f_abteilung,
	'profil_id' => $f_profil,
	'typ'       => $f_typ,
	'status'    => $f_status,
);

$t_state_lang = array(
	'gueltig'    => 'matrix_state_gueltig',
	'bald'       => 'matrix_state_bald',
	'offen'      => 'matrix_state_offen',
	'abgelaufen' => 'matrix_state_abgelaufen',
	'fehlt'      => 'matrix_state_fehlt',
);

$t_filename = 'qualifikationsmatrix_' . ( $f_format === 'raw' ? 'rohdaten_' : '' ) . $t_today . '.csv';

header( 'Content-Type: text/csv; charset=UTF-8' );
header( 'Content-Disposition: attachment; filename="' . $t_filename . '"' );

$t_out = fopen( 'php://output', 'w' );
# UTF-8 BOM so Excel detects the encoding.
fwrite( $t_out, "\xEF\xBB\xBF" );

if( $f_format === 'raw' ) {
	fputcsv( $t_out, array(
		plugin_lang_get( 'col_personalnummer' ),
		plugin_lang_get( 'col_name' ),
		plugin_lang_get( 'col_abteilung' ),
		plugin_lang_get( 'col_schluessel' ),
		plugin_lang_get( 'col_bezeichnung' ),
		plugin_lang_get( 'col_typ' ),
		plugin_lang_get( 'event_col_status' ),
		plugin_lang_get( 'export_soll_termin' ),
		plugin_lang_get( 'export_gueltig_bis' ),
		plugin_lang_get( 'export_zyklus' ),
		plugin_lang_get( 'export_ticket' ),
	), ';' );

	foreach( qt_matrix_raw_rows( $t_today, $t_filters ) as $t_r ) {
		fputcsv( $t_out, array(
			(string)$t_r['personalnummer'],
			trim( $t_r['nachname'] . ', ' . $t_r['vorname'], ', ' ),
			(string)$t_r['abteilung'],
			(string)$t_r['schluessel'],
			(string)$t_r['bezeichnung'],
			(string)$t_r['typ'],
			(string)$t_r['status'],
			(string)$t_r['soll_termin'],
			(string)$t_r['gueltig_bis'],
			(string)$t_r['zyklus'],
			(int)$t_r['bug_id'] > 0 ? (int)$t_r['bug_id'] : '',
		), ';' );
	}
} else {
	# Matrix grid: one row per person, one column per measure, cell = state label.
	$t_matrix = qt_matrix_build( $t_today, $t_filters );

	$t_header = array(
		plugin_lang_get( 'col_personalnummer' ),
		plugin_lang_get( 'col_name' ),
		plugin_lang_get( 'col_abteilung' ),
	);
	foreach( $t_matrix['massnahmen'] as $t_m ) {
		$t_header[] = $t_m['schluessel'];
	}
	fputcsv( $t_out, $t_header, ';' );

	foreach( $t_matrix['persons'] as $t_person ) {
		$t_pid = (int)$t_person['id'];
		$t_line = array(
			(string)$t_person['personalnummer'],
			trim( $t_person['nachname'] . ', ' . $t_person['vorname'], ', ' ),
			(string)$t_person['abteilung'],
		);
		foreach( $t_matrix['massnahmen'] as $t_m ) {
			$t_mid = (int)$t_m['id'];
			$t_cell = isset( $t_matrix['cells'][$t_pid][$t_mid] ) ? $t_matrix['cells'][$t_pid][$t_mid] : null;
			if( $t_cell === null ) {
				$t_line[] = '';
			} else {
				$t_val = plugin_lang_get( $t_state_lang[$t_cell['state']] );
				if( ( $t_cell['state'] === 'gueltig' || $t_cell['state'] === 'bald' ) && $t_cell['rest'] !== null ) {
					$t_val .= ' (' . (int)$t_cell['rest'] . ')';
				}
				$t_line[] = $t_val;
			}
		}
		fputcsv( $t_out, $t_line, ';' );
	}
}

fclose( $t_out );
exit;
