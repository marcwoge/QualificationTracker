<?php
/**
 * QualificationTracker – delete a group event (F3.1).
 *
 * POST-only handler.
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

form_security_validate( 'plugin_QualificationTracker_veranstaltung_delete' );

plugin_require_api( 'core/QT_Event.php' );

$f_id = gpc_get_int( 'id' );

if( qt_event_get( $f_id ) === false ) {
	error_parameters( $f_id );
	trigger_error( ERROR_GENERIC, ERROR );
}

qt_event_delete( $f_id );

form_security_purge( 'plugin_QualificationTracker_veranstaltung_delete' );
print_successful_redirect( plugin_page( 'veranstaltung', true ) . '&msg=deleted' );
