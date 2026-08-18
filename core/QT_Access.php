<?php
/**
 * QualificationTracker – graded access roles (F7.1).
 *
 * Four roles map to MantisBT global access levels via configurable thresholds:
 * 'view' (Betrachter), 'edit' (Sachbearbeiter), 'manage' (SiFa) and 'admin'
 * (Administrator). Pages call qt_access_ensure() with the role they require. A
 * pure viewer can additionally be scoped to their own department in the read
 * reports via the abteilung_betrachter map.
 *
 * qt_access_effective_abteilung() is pure and unit-tested; the other helpers
 * read the plugin configuration and the current user's access level.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The configured global access threshold for a role. Unknown roles fall back to
 * the manage threshold (the safe, higher bar).
 *
 * @param string $p_role view|edit|manage|admin
 * @return int
 */
function qt_access_threshold( $p_role ) {
	switch( $p_role ) {
		case 'view':
			return (int)plugin_config_get( 'view_threshold' );
		case 'edit':
			return (int)plugin_config_get( 'edit_threshold' );
		case 'admin':
			return (int)plugin_config_get( 'admin_threshold' );
		case 'manage':
		default:
			return (int)plugin_config_get( 'manage_threshold' );
	}
}

/**
 * Whether the current user reaches the role's threshold.
 *
 * @param string $p_role
 * @return bool
 */
function qt_access_has( $p_role ) {
	return access_has_global_level( qt_access_threshold( $p_role ) );
}

/**
 * Ensure the current user reaches the role's threshold or stop with the standard
 * MantisBT "access denied" page.
 *
 * @param string $p_role
 * @return void
 */
function qt_access_ensure( $p_role ) {
	access_ensure_global_level( qt_access_threshold( $p_role ) );
}

/**
 * The department a pure viewer is restricted to, or '' when unrestricted. Users
 * that reach the edit level (or higher) are never restricted.
 *
 * @return string
 */
function qt_access_viewer_abteilung() {
	if( qt_access_has( 'edit' ) ) {
		return '';
	}
	$t_map = (array)plugin_config_get( 'abteilung_betrachter' );
	$t_uid = (int)auth_get_current_user_id();
	return isset( $t_map[$t_uid] ) ? (string)$t_map[$t_uid] : '';
}

/**
 * The effective department filter: a viewer restriction always wins over the
 * requested filter. Pure.
 *
 * @param string $p_requested
 * @param string $p_restriction
 * @return string
 */
function qt_access_effective_abteilung( $p_requested, $p_restriction ) {
	return ( $p_restriction !== '' ) ? $p_restriction : $p_requested;
}
