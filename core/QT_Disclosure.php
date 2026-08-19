<?php
/**
 * QualificationTracker – data-subject disclosure (Auskunft, F7.4).
 *
 * Compiles everything this plugin stores about one person into a single report
 * for a DSGVO Art. 15 subject-access request: the master record, the profile
 * assignments, every proof instance, event participations and any deletion-log
 * entries about that person. The page renders it as a print-optimised, fully
 * self-contained HTML document, so the browser's "Save as PDF" produces the PDF
 * without adding any runtime dependency (the plugin stays git-clone deployable).
 *
 * The field-mapping and filename helpers are pure and unit-tested; the gather
 * step reads the database.
 *
 * Note on occupational-health (VO) data: the proof index (qt_nachweis) holds
 * only status and dates, never a finding – the data model has no such field
 * (see F7.2). Listing that a VO proof exists is data the subject is entitled to
 * under Art. 15; no health content is disclosed because none is stored.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The master-record fields of a person as ordered label/value pairs for the
 * disclosure report. Pure: booleans and empty values are normalised to display
 * strings, and only intrinsic attributes are emitted (the surrogate id and the
 * bookkeeping timestamps are handled separately by the report header). The
 * caller may inject a resolved supervisor name as 'vorgesetzter_name'.
 *
 * A field's 'value' is a display string unless 'translate' is true, in which
 * case 'value' is itself a lang key the caller must resolve (used for booleans
 * so the report stays localised).
 *
 * @param array $p_person Person row.
 * @return array List of array( 'key' => lang key, 'value' => string[, 'translate' => bool] ).
 */
function qt_auskunft_person_fields( array $p_person ) {
	$t_dash = '–';
	$t_val = function( $p_key ) use ( $p_person, $t_dash ) {
		if( !isset( $p_person[$p_key] ) ) {
			return $t_dash;
		}
		$t_v = (string)$p_person[$p_key];
		return $t_v === '' ? $t_dash : $t_v;
	};

	$t_supervisor = isset( $p_person['vorgesetzter_name'] ) && (string)$p_person['vorgesetzter_name'] !== ''
		? (string)$p_person['vorgesetzter_name']
		: $t_dash;

	$t_aktiv = isset( $p_person['aktiv'] ) && (int)$p_person['aktiv'] === 1;

	return array(
		array( 'key' => 'col_personalnummer', 'value' => $t_val( 'personalnummer' ) ),
		array( 'key' => 'label_person_typ',   'value' => $t_val( 'typ' ) ),
		array( 'key' => 'label_fremdfirma',   'value' => $t_val( 'fremdfirma' ) ),
		array( 'key' => 'label_nachname',     'value' => $t_val( 'nachname' ) ),
		array( 'key' => 'label_vorname',      'value' => $t_val( 'vorname' ) ),
		array( 'key' => 'col_abteilung',      'value' => $t_val( 'abteilung' ) ),
		array( 'key' => 'label_eintritt',     'value' => $t_val( 'eintritt' ) ),
		array( 'key' => 'label_austritt',     'value' => $t_val( 'austritt' ) ),
		array( 'key' => 'label_vorgesetzter', 'value' => $t_supervisor ),
		array( 'key' => 'label_jugendschutz', 'value' => $t_val( 'verkuerztes_intervall_bis' ) ),
		array( 'key' => 'label_aktiv',        'value' => $t_aktiv ? 'bool_yes' : 'bool_no', 'translate' => true ),
	);
}

/**
 * A safe, descriptive base filename for the disclosure document. Pure: keeps
 * only word characters, dashes and dots; collapses everything else to
 * underscores. Example: "Auskunft_Mustermann_Erika_900123_2026-08-19".
 *
 * @param array  $p_person Person row.
 * @param string $p_today  ISO date.
 * @return string
 */
function qt_auskunft_filename( array $p_person, $p_today ) {
	$t_parts = array(
		'Auskunft',
		isset( $p_person['nachname'] ) ? (string)$p_person['nachname'] : '',
		isset( $p_person['vorname'] ) ? (string)$p_person['vorname'] : '',
		isset( $p_person['personalnummer'] ) ? (string)$p_person['personalnummer'] : '',
		(string)$p_today,
	);
	$t_name = implode( '_', array_filter( $t_parts, function( $p ) { return $p !== ''; } ) );
	$t_name = preg_replace( '/[^\w.\-]+/u', '_', $t_name );
	$t_name = preg_replace( '/_+/', '_', $t_name );
	return trim( $t_name, '_' );
}

/**
 * Gather everything the plugin stores about one person.
 *
 * @param int $p_person_id
 * @return array|false Sections person, zuordnungen, nachweise, teilnahmen,
 *                     loeschungen; or false when the person does not exist.
 */
function qt_auskunft_gather( $p_person_id ) {
	$t_person = qt_person_get( (int)$p_person_id );
	if( $t_person === false ) {
		return false;
	}

	# Resolve the supervisor's display name for the master-record section.
	$t_sup = (int)$t_person['vorgesetzter_user_id'];
	$t_person['vorgesetzter_name'] = ( $t_sup > 0 && user_exists( $t_sup ) )
		? user_get_name( $t_sup ) : '';

	# Profile assignments.
	$t_zuordnungen = array();
	$t_res = db_query( 'SELECT z.*, pr.name AS profil_name'
		. ' FROM ' . plugin_table( 'zuordnung' ) . ' z'
		. ' LEFT JOIN ' . plugin_table( 'profil' ) . ' pr ON pr.id = z.profil_id'
		. ' WHERE z.person_id = ' . db_param() . ' ORDER BY z.gueltig_ab, z.id',
		array( (int)$p_person_id ) );
	while( $t_row = db_fetch_array( $t_res ) ) {
		$t_zuordnungen[] = $t_row;
	}

	# Proof instances (the person's core qualification data).
	$t_nachweise = array();
	$t_res = db_query( 'SELECT n.*, m.schluessel, m.bezeichnung, m.typ'
		. ' FROM ' . plugin_table( 'nachweis' ) . ' n'
		. ' LEFT JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = n.massnahme_id'
		. ' WHERE n.person_id = ' . db_param() . ' ORDER BY n.soll_termin, n.id',
		array( (int)$p_person_id ) );
	while( $t_row = db_fetch_array( $t_res ) ) {
		$t_nachweise[] = $t_row;
	}

	# Event participations.
	$t_teilnahmen = array();
	$t_res = db_query( 'SELECT t.*, v.titel, v.termin, m.schluessel, m.bezeichnung'
		. ' FROM ' . plugin_table( 'teilnehmer' ) . ' t'
		. ' LEFT JOIN ' . plugin_table( 'veranstaltung' ) . ' v ON v.id = t.veranstaltung_id'
		. ' LEFT JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = v.massnahme_id'
		. ' WHERE t.person_id = ' . db_param() . ' ORDER BY v.termin, t.id',
		array( (int)$p_person_id ) );
	while( $t_row = db_fetch_array( $t_res ) ) {
		$t_teilnahmen[] = $t_row;
	}

	# Deletion-log entries about this person (proofs already erased).
	$t_loeschungen = array();
	$t_res = db_query( 'SELECT * FROM ' . plugin_table( 'loeschung' )
		. ' WHERE person_id = ' . db_param() . ' ORDER BY date_created DESC, id DESC',
		array( (int)$p_person_id ) );
	while( $t_row = db_fetch_array( $t_res ) ) {
		$t_loeschungen[] = $t_row;
	}

	return array(
		'person'      => $t_person,
		'zuordnungen' => $t_zuordnungen,
		'nachweise'   => $t_nachweise,
		'teilnahmen'  => $t_teilnahmen,
		'loeschungen' => $t_loeschungen,
	);
}
