<?php
/**
 * QualificationTracker – custom-field bootstrap (F1.5).
 *
 * Creates the proof-ticket custom fields described in the concept (Teil 1, §5)
 * and links them to the target project. Existing fields of the same name are
 * reused (linked), never duplicated or redefined – this is also the bridge for
 * installations migrating from the pure-MantisBT ("Bordmittel") setup, which
 * used exactly these field names.
 *
 * qt_custom_fields_definitions() is pure data; the ensure/link/status helpers
 * use the MantisBT custom-field API. Deliberately there is NO free-text findings
 * field: occupational-health (VO) proofs may only carry type, date and
 * follow-up deadline (data-protection requirement, see F7.2).
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The custom-field definitions, in display order.
 *
 * @return array List of [ name, type, possible_values, default_value ].
 */
function qt_custom_fields_definitions() {
	return array(
		array( 'name' => 'mitarbeiter',          'type' => CUSTOM_FIELD_TYPE_STRING,  'possible_values' => '', 'default_value' => '' ),
		array( 'name' => 'personalnummer',       'type' => CUSTOM_FIELD_TYPE_STRING,  'possible_values' => '', 'default_value' => '' ),
		array( 'name' => 'massnahmenschluessel', 'type' => CUSTOM_FIELD_TYPE_STRING,  'possible_values' => '', 'default_value' => '' ),
		array( 'name' => 'rechtsgrundlage',      'type' => CUSTOM_FIELD_TYPE_STRING,  'possible_values' => '', 'default_value' => '' ),
		array( 'name' => 'durchgefuehrt_am',     'type' => CUSTOM_FIELD_TYPE_DATE,    'possible_values' => '', 'default_value' => '' ),
		array( 'name' => 'gueltig_bis',          'type' => CUSTOM_FIELD_TYPE_DATE,    'possible_values' => '', 'default_value' => '' ),
		array( 'name' => 'intervall_monate',     'type' => CUSTOM_FIELD_TYPE_NUMERIC, 'possible_values' => '', 'default_value' => '' ),
		array( 'name' => 'faelligkeitsmodus',    'type' => CUSTOM_FIELD_TYPE_ENUM,    'possible_values' => 'rollierend|kalenderjahr|stichmonat|extern', 'default_value' => 'kalenderjahr' ),
		array( 'name' => 'soll_termin',          'type' => CUSTOM_FIELD_TYPE_DATE,    'possible_values' => '', 'default_value' => '' ),
		array( 'name' => 'durchfuehrender',      'type' => CUSTOM_FIELD_TYPE_STRING,  'possible_values' => '', 'default_value' => '' ),
		array( 'name' => 'nachweisart',          'type' => CUSTOM_FIELD_TYPE_ENUM,    'possible_values' => 'Unterschriftenliste|Zertifikat|Beauftragungsschreiben|Teilnahmebestaetigung', 'default_value' => '' ),
		array( 'name' => 'abteilung',            'type' => CUSTOM_FIELD_TYPE_STRING,  'possible_values' => '', 'default_value' => '' ),
		array( 'name' => 'veranstaltung_id',     'type' => CUSTOM_FIELD_TYPE_STRING,  'possible_values' => '', 'default_value' => '' ),
	);
}

/**
 * Ensure every custom field exists. Missing fields are created and defined;
 * existing fields (matched by name) are reused untouched.
 *
 * @return array Map name => [ 'id' => int, 'created' => bool ].
 */
function qt_custom_fields_ensure() {
	$t_result = array();

	foreach( qt_custom_fields_definitions() as $t_def ) {
		$t_name = $t_def['name'];
		$t_id = custom_field_get_id_from_name( $t_name );
		$t_created = false;

		if( $t_id === false ) {
			$t_id = custom_field_create( $t_name );

			# Start from the freshly created field's full definition and override
			# only what we need, so custom_field_update() gets every key it reads.
			$t_full = custom_field_get_definition( $t_id );
			$t_full['type']             = $t_def['type'];
			$t_full['possible_values']  = $t_def['possible_values'];
			$t_full['default_value']    = $t_def['default_value'];
			$t_full['display_report']   = true;
			$t_full['display_update']   = true;
			$t_full['display_resolved'] = true;
			$t_full['display_closed']   = true;
			custom_field_update( $t_id, $t_full );

			$t_created = true;
		}

		$t_result[$t_name] = array( 'id' => (int)$t_id, 'created' => $t_created );
	}

	return $t_result;
}

/**
 * Ensure the fields exist and link them to a project (idempotent).
 *
 * @param int $p_project_id
 * @return int Number of fields newly linked.
 */
function qt_custom_fields_link( $p_project_id ) {
	$p_project_id = (int)$p_project_id;
	if( $p_project_id <= 0 ) {
		return 0;
	}

	$t_fields = qt_custom_fields_ensure();
	$t_linked = 0;
	foreach( $t_fields as $t_field ) {
		if( !custom_field_is_linked( $t_field['id'], $p_project_id ) ) {
			custom_field_link( $t_field['id'], $p_project_id );
			$t_linked++;
		}
	}
	return $t_linked;
}

/**
 * Status of each custom field for display on the configuration page.
 *
 * @param int $p_project_id Target project (0 = none), for the link column.
 * @return array List of [ name, type, exists (bool), linked (bool) ].
 */
function qt_custom_fields_status( $p_project_id ) {
	$p_project_id = (int)$p_project_id;
	$t_status = array();

	foreach( qt_custom_fields_definitions() as $t_def ) {
		$t_id = custom_field_get_id_from_name( $t_def['name'] );
		$t_status[] = array(
			'name'   => $t_def['name'],
			'type'   => $t_def['type'],
			'exists' => $t_id !== false,
			'linked' => ( $t_id !== false && $p_project_id > 0 )
				? custom_field_is_linked( $t_id, $p_project_id )
				: false,
		);
	}

	return $t_status;
}
