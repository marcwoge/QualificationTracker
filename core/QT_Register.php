<?php
/**
 * QualificationTracker – records-of-processing helper (Verarbeitungsverzeichnis, F7.6).
 *
 * Art. 30 DSGVO requires a record of processing activities. The plugin ships a
 * fill-in template (docs/Verarbeitungsverzeichnis.md) and renders a
 * configuration-aware version in the interface, so the retention periods,
 * projects and roles shown reflect the actual installation instead of a static
 * example. This file provides the data those views build on.
 *
 * qt_verzeichnis_aufbewahrung_zeilen() is pure and unit-tested (it reuses the
 * retention resolution of the deletion concept); qt_verzeichnis_context() reads
 * the live configuration.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The retention table rows for the record: one default row plus one per measure
 * type, each with its resolved retention period in months. Pure; reuses
 * qt_loesch_retention_months() so the record matches the deletion concept.
 *
 * @param int   $p_default Global default months.
 * @param array $p_map     Per-type override map.
 * @param array $p_types   Measure types to list.
 * @return array List of array( 'typ' => string, 'monate' => int, 'is_default' => bool ).
 */
function qt_verzeichnis_aufbewahrung_zeilen( $p_default, array $p_map, array $p_types ) {
	$t_lines = array( array( 'typ' => '', 'monate' => (int)$p_default, 'is_default' => true ) );
	foreach( $p_types as $t_typ ) {
		$t_lines[] = array(
			'typ'        => $t_typ,
			'monate'     => qt_loesch_retention_months( $t_typ, $p_map, $p_default ),
			'is_default' => false,
		);
	}
	return $t_lines;
}

/**
 * The live configuration relevant to the record of processing: retention
 * settings, the target and occupational-health projects, and the role
 * thresholds. Used by the page to fill the Art. 30 template.
 *
 * @return array
 */
function qt_verzeichnis_context() {
	$t_ziel     = (int)plugin_config_get( 'zielprojekt_id' );
	$t_vorsorge = (int)plugin_config_get( 'vorsorge_projekt_id' );

	return array(
		'aufbewahrung_default' => (int)plugin_config_get( 'aufbewahrung_monate_default' ),
		'aufbewahrung_map'     => (array)plugin_config_get( 'aufbewahrung_monate_typ' ),
		'zielprojekt_id'       => $t_ziel,
		'zielprojekt_name'     => $t_ziel > 0 && project_exists( $t_ziel ) ? project_get_name( $t_ziel ) : '',
		'vorsorge_id'          => $t_vorsorge,
		'vorsorge_name'        => $t_vorsorge > 0 && project_exists( $t_vorsorge ) ? project_get_name( $t_vorsorge ) : '',
		'view_threshold'       => (int)plugin_config_get( 'view_threshold' ),
		'edit_threshold'       => (int)plugin_config_get( 'edit_threshold' ),
		'manage_threshold'     => (int)plugin_config_get( 'manage_threshold' ),
		'admin_threshold'      => (int)plugin_config_get( 'admin_threshold' ),
	);
}
