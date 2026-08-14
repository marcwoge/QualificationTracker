<?php
/**
 * QualificationTracker – apply a due-date mode change (F5.7). POST-only.
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
require_api( 'custom_field_api.php' );
require_api( 'database_api.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'print_api.php' );

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

form_security_validate( 'plugin_QualificationTracker_moduswechsel_apply' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_CustomFields.php' );
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_ModeChange.php' );

$f_massnahme  = gpc_get_int( 'massnahme_id' );
$f_modus      = gpc_get_string( 'modus', '' );
$f_stichmonat = gpc_get_int( 'stichmonat', 0 );

if( qt_massnahme_get( $f_massnahme ) === false || !in_array( $f_modus, qt_catalog_modi(), true ) ) {
	error_parameters( $f_massnahme );
	trigger_error( ERROR_GENERIC, ERROR );
}

$t_result = qt_moduswechsel_apply( $f_massnahme, $f_modus, $f_stichmonat );

form_security_purge( 'plugin_QualificationTracker_moduswechsel_apply' );
print_successful_redirect( plugin_page( 'moduswechsel', true )
	. '&massnahme_id=' . $f_massnahme . '&modus=' . urlencode( $f_modus ) . '&stichmonat=' . (int)$f_stichmonat
	. '&msg=applied&updated=' . (int)$t_result['updated'] . '&preserved=' . (int)$t_result['preserved'] );
