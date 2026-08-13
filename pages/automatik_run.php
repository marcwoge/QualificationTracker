<?php
/**
 * QualificationTracker – run the expiry watchdog (F5.1). POST-only.
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

form_security_validate( 'plugin_QualificationTracker_automatik_run' );

$f_action = gpc_get_string( 'action', 'expiry' );
$t_today  = date( 'Y-m-d' );

if( $f_action === 'reactivation' ) {
	plugin_require_api( 'core/QT_Catalog.php' );
	plugin_require_api( 'core/QT_DueDateCalculator.php' );
	plugin_require_api( 'core/QT_Generator.php' );
	plugin_require_api( 'core/QT_Matrix.php' );
	plugin_require_api( 'core/QT_Integration.php' );
	plugin_require_api( 'core/QT_Reactivation.php' );

	$t_result = qt_reactivation_run( $t_today );

	form_security_purge( 'plugin_QualificationTracker_automatik_run' );
	print_successful_redirect( plugin_page( 'automatik', true )
		. '&msg=reactivated&deferred=' . (int)$t_result['deferred']
		. '&reactivated=' . (int)$t_result['reactivated'] );
}

plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_Expiry.php' );

$t_result = qt_expiry_run( $t_today );

form_security_purge( 'plugin_QualificationTracker_automatik_run' );
print_successful_redirect( plugin_page( 'automatik', true ) . '&msg=expired&count=' . (int)$t_result['expired'] );
