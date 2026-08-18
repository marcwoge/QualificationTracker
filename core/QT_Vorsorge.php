<?php
/**
 * QualificationTracker – occupational-health (VO) separation (F7.2).
 *
 * Occupational-health data is data-protection critical. Two controls:
 *
 *  1. Isolation – proof tickets for measures of type VO are created in a
 *     dedicated MantisBT project (vorsorge_projekt_id) instead of the general
 *     target project, so only occupational-health staff (who have access to
 *     that project) can see them.
 *  2. Data minimisation – a VO ticket may only carry identification and
 *     scheduling fields; program metadata is dropped and, by design, the data
 *     model has no free-text findings/diagnosis field anywhere.
 *
 * qt_vorsorge_project(), qt_vorsorge_allowed_fields() and
 * qt_vorsorge_field_allowed() are pure and unit-tested; qt_vorsorge_categories()
 * reads the database.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The project a proof ticket belongs in: the dedicated occupational-health
 * project for VO measures (when configured), otherwise the general target
 * project. Pure.
 *
 * @param string $p_typ
 * @param int    $p_default_project
 * @param int    $p_vorsorge_project
 * @return int
 */
function qt_vorsorge_project( $p_typ, $p_default_project, $p_vorsorge_project ) {
	if( $p_typ === 'VO' && (int)$p_vorsorge_project > 0 ) {
		return (int)$p_vorsorge_project;
	}
	return (int)$p_default_project;
}

/**
 * The custom-field keys permitted on a VO ticket (identification + scheduling).
 * Program metadata (legal basis, interval, mode) is intentionally omitted. Pure.
 *
 * @return array
 */
function qt_vorsorge_allowed_fields() {
	return array( 'mitarbeiter', 'personalnummer', 'abteilung', 'massnahmenschluessel',
		'nachweisart', 'soll_termin', 'durchgefuehrt_am', 'gueltig_bis', 'durchfuehrender' );
}

/**
 * Whether a custom field may be set on a ticket of the given measure type. Pure.
 * Non-VO measures allow every field; VO measures only the minimised set.
 *
 * @param string $p_field
 * @param string $p_typ
 * @return bool
 */
function qt_vorsorge_field_allowed( $p_field, $p_typ ) {
	if( $p_typ !== 'VO' ) {
		return true;
	}
	return in_array( $p_field, qt_vorsorge_allowed_fields(), true );
}

/**
 * The measure categories for a project, ensuring they exist. Cached per project
 * within the request.
 *
 * @param int $p_project_id
 * @return array Map category name => id.
 */
function qt_vorsorge_categories( $p_project_id ) {
	static $s_cache = array();
	$t_project = (int)$p_project_id;
	if( !isset( $s_cache[$t_project] ) ) {
		qt_custom_fields_link( $t_project );
		$s_cache[$t_project] = qt_generator_ensure_categories( $t_project );
	}
	return $s_cache[$t_project];
}
