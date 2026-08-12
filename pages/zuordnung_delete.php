<?php
/**
 * QualificationTracker – delete a profile assignment (F2.2).
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

form_security_validate( 'plugin_QualificationTracker_zuordnung_delete' );

plugin_require_api( 'core/QT_Assignment.php' );

$f_id = gpc_get_int( 'id' );

if( qt_zuordnung_get( $f_id ) === false ) {
	error_parameters( $f_id );
	trigger_error( ERROR_GENERIC, ERROR );
}

qt_zuordnung_delete( $f_id );

form_security_purge( 'plugin_QualificationTracker_zuordnung_delete' );
print_successful_redirect( plugin_page( 'zuordnung', true ) . '&msg=deleted' );
