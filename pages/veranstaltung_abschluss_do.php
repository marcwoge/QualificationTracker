<?php
/**
 * QualificationTracker – perform event mass completion (F3.4).
 *
 * POST-only. Completes the proofs of the present participants and marks the
 * absent ones, then redirects back to the participant page with a summary.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'bug_api.php' );
require_api( 'config_api.php' );
require_api( 'database_api.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'print_api.php' );

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

form_security_validate( 'plugin_QualificationTracker_veranstaltung_abschluss' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_CustomFields.php' );
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_Completion.php' );
plugin_require_api( 'core/QT_Event.php' );
plugin_require_api( 'core/QT_Participant.php' );

$f_id               = gpc_get_int( 'id' );
$f_durchgefuehrt_am = gpc_get_string( 'durchgefuehrt_am', '' );
$f_durchfuehrender  = gpc_get_string( 'durchfuehrender', '' );
$f_present          = gpc_get_int_array( 'present', array() );

$t_event = qt_event_get( $f_id );
if( $t_event === false ) {
	error_parameters( $f_id );
	trigger_error( ERROR_GENERIC, ERROR );
}

# Fall back to the event date when none was entered.
if( $f_durchgefuehrt_am === '' ) {
	$f_durchgefuehrt_am = qt_event_termin_date( $t_event['termin'] );
	if( $f_durchgefuehrt_am === '' ) {
		$f_durchgefuehrt_am = date( 'Y-m-d' );
	}
}

$t_result = qt_teilnehmer_complete_event( $t_event, $f_present, $f_durchgefuehrt_am, $f_durchfuehrender );

form_security_purge( 'plugin_QualificationTracker_veranstaltung_abschluss' );

$t_redirect = plugin_page( 'veranstaltung_teilnehmer', true ) . '&id=' . $f_id
	. '&msg=completed'
	. '&completed=' . (int)$t_result['completed']
	. '&followup=' . (int)$t_result['followup_created']
	. '&absent=' . (int)$t_result['absent'];
print_successful_redirect( $t_redirect );
