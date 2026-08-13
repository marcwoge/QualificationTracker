<?php
/**
 * QualificationTracker – add/remove event participants (F3.2).
 *
 * POST-only handler. action=add takes person_ids[] and plans them; action=remove
 * takes a single teilnehmer_id. Redirects back to the participant page.
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
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'print_api.php' );

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

form_security_validate( 'plugin_QualificationTracker_veranstaltung_teilnehmer_update' );

plugin_require_api( 'core/QT_Event.php' );
plugin_require_api( 'core/QT_Participant.php' );

$f_id     = gpc_get_int( 'id' );
$f_action = gpc_get_string( 'action', '' );

$t_event = qt_event_get( $f_id );
if( $t_event === false ) {
	error_parameters( $f_id );
	trigger_error( ERROR_GENERIC, ERROR );
}

$t_msg = '';

if( $f_action === 'add' ) {
	$f_person_ids = gpc_get_int_array( 'person_ids', array() );
	$t_added = 0;
	foreach( $f_person_ids as $t_person_id ) {
		if( qt_teilnehmer_add( $f_id, $t_person_id ) ) {
			$t_added++;
		}
	}
	$t_msg = $t_added > 0 ? 'added' : '';
} else if( $f_action === 'remove' ) {
	$f_teilnehmer_id = gpc_get_int( 'teilnehmer_id' );
	$t_teilnehmer = qt_teilnehmer_get( $f_teilnehmer_id );
	# Guard: the row must belong to this event.
	if( $t_teilnehmer !== false && (int)$t_teilnehmer['veranstaltung_id'] === $f_id ) {
		qt_teilnehmer_remove( $f_teilnehmer_id );
		$t_msg = 'removed';
	}
}

form_security_purge( 'plugin_QualificationTracker_veranstaltung_teilnehmer_update' );

$t_redirect = plugin_page( 'veranstaltung_teilnehmer', true ) . '&id=' . $f_id;
if( $t_msg !== '' ) {
	$t_redirect .= '&msg=' . $t_msg;
}
print_successful_redirect( $t_redirect );
