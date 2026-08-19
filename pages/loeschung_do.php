<?php
/**
 * QualificationTracker – execute proof deletions (F7.3). POST-only.
 *
 * Deletes the selected finished proofs whose retention has elapsed, writing one
 * deletion-log entry per proof. Eligibility is re-checked server-side, so a
 * stale or forged id can never delete an in-retention record.
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
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'admin' );

form_security_validate( 'plugin_QualificationTracker_loeschung_do' );

plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Deletion.php' );

$f_ids   = gpc_get_int_array( 'ids', array() );
$f_grund = gpc_get_string( 'grund', '' );

$t_result = qt_loesch_execute( $f_ids, $f_grund, date( 'Y-m-d' ) );

form_security_purge( 'plugin_QualificationTracker_loeschung_do' );
print_successful_redirect( plugin_page( 'loeschung', true )
	. '&msg=deleted&deleted=' . (int)$t_result['deleted'] . '&skipped=' . (int)$t_result['skipped'] );
