<?php
/**
 * QualificationTracker – delete an activity profile (F2.1).
 *
 * POST-only handler. A profile still assigned to a person is not deleted; the
 * user is redirected back with a note and can deactivate instead.
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

form_security_validate( 'plugin_QualificationTracker_profil_delete' );

plugin_require_api( 'core/QT_Profile.php' );

$f_id = gpc_get_int( 'id' );

if( qt_profil_get( $f_id ) === false ) {
	error_parameters( $f_id );
	trigger_error( ERROR_GENERIC, ERROR );
}

if( qt_profil_is_referenced( $f_id ) ) {
	form_security_purge( 'plugin_QualificationTracker_profil_delete' );
	print_header_redirect( plugin_page( 'profil', true ) . '&msg=referenced' );
	exit;
}

qt_profil_delete( $f_id );

form_security_purge( 'plugin_QualificationTracker_profil_delete' );
print_successful_redirect( plugin_page( 'profil', true ) . '&msg=deleted' );
