<?php
/**
 * QualificationTracker – attach the scanned attendance list (F3.6).
 *
 * POST-only. Attaches the uploaded scan once to the parent event ticket and
 * adds a reference note to every child proof ticket, then redirects back.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'bug_api.php' );
require_api( 'bugnote_api.php' );
require_api( 'config_api.php' );
require_api( 'database_api.php' );
require_api( 'file_api.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

form_security_validate( 'plugin_QualificationTracker_veranstaltung_anhang' );

plugin_require_api( 'core/QT_Event.php' );
plugin_require_api( 'core/QT_Participant.php' );

$f_id   = gpc_get_int( 'id' );
$f_file = gpc_get_file( 'nachweis', null );

$t_event = qt_event_get( $f_id );
if( $t_event === false ) {
	error_parameters( $f_id );
	trigger_error( ERROR_GENERIC, ERROR );
}

$t_redirect = plugin_page( 'veranstaltung_teilnehmer', true ) . '&id=' . $f_id;

# No file / upload error.
if( !is_array( $f_file ) || !isset( $f_file['error'] ) || $f_file['error'] !== UPLOAD_ERR_OK
	|| !isset( $f_file['name'] ) || $f_file['name'] === '' ) {
	form_security_purge( 'plugin_QualificationTracker_veranstaltung_anhang' );
	print_successful_redirect( $t_redirect . '&msg=anhang_no_file' );
}

$t_parent = (int)$t_event['eltern_bug_id'];
$t_note = sprintf( plugin_lang_get( 'anhang_note' ),
	bug_format_id( $t_parent ), string_display_line( $f_file['name'] ) );

$t_result = qt_teilnehmer_attach_nachweis( $t_event, $f_file, $t_note );

form_security_purge( 'plugin_QualificationTracker_veranstaltung_anhang' );

if( in_array( 'error_no_parent', $t_result['errors'], true ) ) {
	print_successful_redirect( $t_redirect . '&msg=anhang_no_parent' );
}

print_successful_redirect( $t_redirect . '&msg=anhang_attached&referenced=' . (int)$t_result['referenced'] );
