<?php
/**
 * QualificationTracker – optional plugin integration (F2.4).
 *
 * The plugin has NO hard dependency on any other plugin. It detects optional
 * companions at runtime and degrades cleanly when they are absent. Detection
 * never triggers an error even if the plugin API were unavailable.
 *
 * Important: due-date scheduling is always native (QT_DueDateCalculator is the
 * single source of every due date). IssueRecurrence is recognised for
 * information only and is never used to compute dates – the four modes and the
 * anchor/grace mechanism cannot be expressed as a generic recurrence rule.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Is an optional plugin installed? Degrades to false if the plugin API is not
 * available. Pure with respect to side effects (read-only).
 *
 * @param string $p_basename
 * @return bool
 */
function qt_integration_available( $p_basename ) {
	if( !function_exists( 'plugin_is_installed' ) ) {
		return false;
	}
	return (bool)plugin_is_installed( $p_basename );
}

/**
 * Is IssueRecurrence installed? (Recognised only; scheduling stays native.)
 *
 * @return bool
 */
function qt_integration_issuerecurrence() {
	return qt_integration_available( 'IssueRecurrence' );
}

/**
 * Is Reveille installed? (Used from F5.2 for expiry reactivation when present.)
 *
 * @return bool
 */
function qt_integration_reveille() {
	return qt_integration_available( 'Reveille' );
}
