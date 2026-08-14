<?php
/**
 * QualificationTracker – historical proof CSV import (F6.2).
 *
 * Imports existing (historical) proofs from a semicolon-separated CSV with a
 * header row: person (by personnel number), measure (by key), performed date and
 * validity end date. Each row creates a proof ticket in the target status –
 * 'gueltig' while still valid, 'abgelaufen' once the validity has ended – with
 * the audit fields set, plus a qt_nachweis index row. Idempotent per cycle.
 *
 * qt_import_nachweise_parse (reused from the person import),
 * qt_import_nachweise_map_row(), qt_import_nachweise_target_status() and
 * qt_import_nachweise_zyklus() are pure and unit-tested; the import writes the
 * database and creates tickets.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The accepted CSV column headers (lower-case).
 *
 * @return array
 */
function qt_import_nachweise_columns() {
	return array( 'personalnummer', 'massnahme', 'durchgefuehrt_am', 'gueltig_bis', 'durchfuehrender', 'zyklus' );
}

/**
 * Map a parsed CSV row to a historical-proof data array. Pure.
 *
 * @param array $p_row
 * @return array
 */
function qt_import_nachweise_map_row( array $p_row ) {
	$t_get = function( $p_key ) use ( $p_row ) {
		return isset( $p_row[$p_key] ) ? trim( (string)$p_row[$p_key] ) : '';
	};
	return array(
		'personalnummer'   => $t_get( 'personalnummer' ),
		'massnahme'        => $t_get( 'massnahme' ),
		'durchgefuehrt_am' => $t_get( 'durchgefuehrt_am' ),
		'gueltig_bis'      => $t_get( 'gueltig_bis' ),
		'durchfuehrender'  => $t_get( 'durchfuehrender' ),
		'zyklus'           => $t_get( 'zyklus' ),
	);
}

/**
 * The target proof status for a historical proof: valid while the end date is
 * empty or not in the past, else expired. Pure.
 *
 * @param string $p_gueltig_bis
 * @param string $p_today
 * @return string 'gueltig' or 'abgelaufen'
 */
function qt_import_nachweise_target_status( $p_gueltig_bis, $p_today ) {
	if( $p_gueltig_bis === null || $p_gueltig_bis === '' ) {
		return 'gueltig';
	}
	return ( substr( $p_gueltig_bis, 0, 10 ) < substr( $p_today, 0, 10 ) ) ? 'abgelaufen' : 'gueltig';
}

/**
 * The cycle label for a historical proof: the explicit column, else the year of
 * the validity end date, else the year the proof was performed. Pure.
 *
 * @param array $p_data
 * @return string
 */
function qt_import_nachweise_zyklus( array $p_data ) {
	if( isset( $p_data['zyklus'] ) && trim( (string)$p_data['zyklus'] ) !== '' ) {
		return trim( (string)$p_data['zyklus'] );
	}
	if( isset( $p_data['gueltig_bis'] ) && $p_data['gueltig_bis'] !== '' ) {
		return substr( $p_data['gueltig_bis'], 0, 4 );
	}
	if( isset( $p_data['durchgefuehrt_am'] ) && $p_data['durchgefuehrt_am'] !== '' ) {
		return substr( $p_data['durchgefuehrt_am'], 0, 4 );
	}
	return '';
}

/**
 * Create the historical proof ticket and its index row.
 *
 * @param array  $p_person
 * @param array  $p_massnahme
 * @param array  $p_data          Mapped row.
 * @param string $p_status        Target domain status.
 * @param string $p_zyklus
 * @param int    $p_project_id
 * @param array  $p_category_ids
 * @param array  $p_field_ids
 * @return int Bug id.
 */
function qt_import_nachweise_place( array $p_person, array $p_massnahme, array $p_data, $p_status, $p_zyklus,
		$p_project_id, array $p_category_ids, array $p_field_ids ) {
	$t_bug = qt_generator_create_ticket( $p_person, $p_massnahme, null, $p_project_id, $p_category_ids, $p_field_ids );

	if( isset( $p_field_ids['durchgefuehrt_am'] ) && $p_data['durchgefuehrt_am'] !== '' ) {
		custom_field_set_value( $p_field_ids['durchgefuehrt_am'], $t_bug, strtotime( $p_data['durchgefuehrt_am'] . ' 00:00:00' ) );
	}
	if( isset( $p_field_ids['gueltig_bis'] ) && $p_data['gueltig_bis'] !== '' ) {
		custom_field_set_value( $p_field_ids['gueltig_bis'], $t_bug, strtotime( $p_data['gueltig_bis'] . ' 00:00:00' ) );
	}
	if( isset( $p_field_ids['durchfuehrender'] ) && $p_data['durchfuehrender'] !== '' ) {
		custom_field_set_value( $p_field_ids['durchfuehrender'], $t_bug, $p_data['durchfuehrender'] );
	}
	if( $p_data['gueltig_bis'] !== '' ) {
		bug_set_field( $t_bug, 'due_date', strtotime( $p_data['gueltig_bis'] . ' 00:00:00' ) );
	}
	bug_set_field( $t_bug, 'status', qt_status_to_mantis( $p_status ) );

	$t_nid = qt_nachweis_record( (int)$p_person['id'], (int)$p_massnahme['id'], $t_bug, null, $p_status, $p_zyklus );
	if( $p_data['gueltig_bis'] !== '' ) {
		db_query( 'UPDATE ' . plugin_table( 'nachweis' ) . ' SET gueltig_bis = ' . db_param()
			. ' WHERE id = ' . db_param(), array( $p_data['gueltig_bis'], (int)$t_nid ) );
	}

	return $t_bug;
}

/**
 * Import mapped historical proof rows. Resolves person and measure, validates,
 * and creates a proof ticket + index row per row (idempotent per cycle).
 *
 * @param array  $p_rows    Mapped rows.
 * @param bool   $p_dry_run Validate only when true.
 * @param string $p_today   ISO date.
 * @return array Summary: created, skipped, errors (list of zeile, ref, fehler[]).
 */
function qt_import_nachweise_run( array $p_rows, $p_dry_run, $p_today ) {
	$t_summary = array( 'created' => 0, 'skipped' => 0, 'errors' => array() );

	$t_project_id = (int)plugin_config_get( 'zielprojekt_id' );
	if( $t_project_id <= 0 ) {
		$t_summary['errors'][] = array( 'zeile' => 0, 'ref' => '', 'fehler' => array( 'error_no_zielprojekt' ) );
		return $t_summary;
	}

	if( !$p_dry_run ) {
		qt_custom_fields_link( $t_project_id );
	}
	$t_cats = qt_generator_ensure_categories( $t_project_id );
	$t_fields = qt_generator_field_ids();

	foreach( $p_rows as $t_i => $t_data ) {
		$t_zeile = $t_i + 2;
		$t_ref = $t_data['personalnummer'] . ' / ' . $t_data['massnahme'];
		$t_errors = array();

		$t_person = ( $t_data['personalnummer'] !== '' )
			? qt_person_get_by_personalnummer( $t_data['personalnummer'] ) : false;
		if( $t_person === false ) {
			$t_errors[] = 'error_import_person_unknown';
		}
		$t_massnahme = ( $t_data['massnahme'] !== '' )
			? qt_massnahme_get_by_schluessel( $t_data['massnahme'] ) : false;
		if( $t_massnahme === false ) {
			$t_errors[] = 'error_import_massnahme_unknown';
		}
		if( $t_data['durchgefuehrt_am'] === '' || !qt_person_valid_date( $t_data['durchgefuehrt_am'] ) ) {
			$t_errors[] = 'error_import_durchgefuehrt_invalid';
		}
		if( $t_data['gueltig_bis'] !== '' && !qt_person_valid_date( $t_data['gueltig_bis'] ) ) {
			$t_errors[] = 'error_import_gueltig_bis_invalid';
		}

		if( !empty( $t_errors ) ) {
			$t_summary['errors'][] = array( 'zeile' => $t_zeile, 'ref' => $t_ref, 'fehler' => $t_errors );
			continue;
		}

		$t_zyklus = qt_import_nachweise_zyklus( $t_data );

		# Idempotent: skip when a proof for this person/measure/cycle exists.
		if( qt_nachweis_find_cycle( (int)$t_person['id'], (int)$t_massnahme['id'], $t_zyklus ) !== false ) {
			$t_summary['skipped']++;
			continue;
		}

		$t_status = qt_import_nachweise_target_status( $t_data['gueltig_bis'], $p_today );

		if( !$p_dry_run ) {
			qt_import_nachweise_place( $t_person, $t_massnahme, $t_data, $t_status, $t_zyklus,
				$t_project_id, $t_cats, $t_fields );
		}
		$t_summary['created']++;
	}

	return $t_summary;
}
