<?php
/**
 * QualificationTracker – migration from the native "Bordmittel" setup (F8.6).
 *
 * Before the plugin, the same domain can be run with plain MantisBT: one ticket
 * per proof instance in a project, the measure type as the ticket category, and
 * the proof data in custom fields (mitarbeiter, personalnummer,
 * massnahmenschluessel, soll_termin, durchgefuehrt_am, gueltig_bis, …). The
 * plugin deliberately adopted those very field names, so an existing setup can
 * be lifted into the plugin's data structure without touching the tickets:
 * this module scans a source project and reconstructs the person register, a
 * stub measure catalogue and the proof index (qt_nachweis), each proof pointing
 * back at its original ticket.
 *
 * The mapping helpers are pure and unit-tested; scan and run read and write the
 * database. The run is idempotent (persons by personnel number, measures by key,
 * proofs by ticket id) and offers a dry run.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Map a Bordmittel category name to a measure type. Pure.
 * Unterweisung→UW, Qualifikation→QU, Beauftragung→BE, Vorsorge→VO; anything else
 * (e.g. the "Stammdaten" housekeeping category) → '' (skip).
 *
 * @param string $p_category
 * @return string
 */
function qt_migrate_typ_from_category( $p_category ) {
	switch( trim( (string)$p_category ) ) {
		case 'Unterweisung':  return 'UW';
		case 'Qualifikation': return 'QU';
		case 'Beauftragung':  return 'BE';
		case 'Vorsorge':      return 'VO';
		default:              return '';
	}
}

/**
 * Split a person label into surname and given name. Pure.
 * Accepts "Nachname, Vorname" (preferred) and "Vorname Nachname"; a single token
 * becomes the surname. The surname is never empty for a non-empty input.
 *
 * @param string $p_name
 * @return array array( 'nachname' => string, 'vorname' => string )
 */
function qt_migrate_split_name( $p_name ) {
	$t_name = trim( (string)$p_name );
	if( $t_name === '' ) {
		return array( 'nachname' => '', 'vorname' => '' );
	}
	if( strpos( $t_name, ',' ) !== false ) {
		list( $t_last, $t_first ) = array_pad( explode( ',', $t_name, 2 ), 2, '' );
		return array( 'nachname' => trim( $t_last ), 'vorname' => trim( $t_first ) );
	}
	$t_parts = preg_split( '/\s+/', $t_name );
	if( count( $t_parts ) === 1 ) {
		return array( 'nachname' => $t_parts[0], 'vorname' => '' );
	}
	$t_last = array_pop( $t_parts );
	return array( 'nachname' => $t_last, 'vorname' => implode( ' ', $t_parts ) );
}

/**
 * The default MantisBT status number → plugin proof state map, matching the
 * status model proposed in KONZEPT-Bordmittel.md § 6.
 *
 * @return array
 */
function qt_migrate_default_status_map() {
	return array(
		10 => 'offen', 20 => 'geplant', 40 => 'durchgefuehrt', 50 => 'durchgefuehrt',
		80 => 'gueltig', 85 => 'gueltig', 90 => 'abgelaufen', 95 => 'entfallen',
	);
}

/**
 * Map a MantisBT status number to a plugin proof state, falling back to 'offen'
 * for unknown numbers. Pure.
 *
 * @param int   $p_status
 * @param array $p_map
 * @return string
 */
function qt_migrate_status( $p_status, array $p_map ) {
	$t_status = (int)$p_status;
	return isset( $p_map[$t_status] ) ? $p_map[$t_status] : 'offen';
}

/**
 * The cycle label for a proof: the year of the anchor (soll_termin), else of the
 * validity end. Pure. Empty when neither is a usable date.
 *
 * @param string|null $p_soll
 * @param string|null $p_gueltig
 * @return string
 */
function qt_migrate_zyklus( $p_soll, $p_gueltig ) {
	foreach( array( $p_soll, $p_gueltig ) as $t_date ) {
		$t_date = (string)$t_date;
		if( preg_match( '/^(\d{4})-\d{2}-\d{2}/', $t_date, $t_m ) ) {
			return $t_m[1];
		}
	}
	return '';
}

/**
 * The custom-field names the migration reads from the source tickets.
 *
 * @return array
 */
function qt_migrate_field_names() {
	return array( 'mitarbeiter', 'personalnummer', 'abteilung', 'massnahmenschluessel',
		'soll_termin', 'durchgefuehrt_am', 'gueltig_bis', 'intervall_monate',
		'faelligkeitsmodus', 'nachweisart', 'rechtsgrundlage', 'durchfuehrender' );
}

/**
 * Scan a source project: return one row per ticket with its category name,
 * status, handler and the proof custom-field values.
 *
 * @param int $p_project_id
 * @return array
 */
function qt_migrate_scan( $p_project_id ) {
	$t_project = (int)$p_project_id;

	# Field name -> id (only those that exist).
	$t_field_ids = array();
	foreach( qt_migrate_field_names() as $t_name ) {
		$t_id = custom_field_get_id_from_name( $t_name );
		if( $t_id !== false ) {
			$t_field_ids[(int)$t_id] = $t_name;
		}
	}

	# Bugs in the project.
	$t_bugs = array();
	$t_res = db_query( 'SELECT id, category_id, status, handler_id FROM {bug} WHERE project_id = ' . db_param(),
		array( $t_project ) );
	while( $t_row = db_fetch_array( $t_res ) ) {
		$t_bugs[(int)$t_row['id']] = array(
			'bug_id'      => (int)$t_row['id'],
			'category_id' => (int)$t_row['category_id'],
			'status'      => (int)$t_row['status'],
			'handler_id'  => (int)$t_row['handler_id'],
			'fields'      => array(),
		);
	}
	if( empty( $t_bugs ) ) {
		return array();
	}

	# Category id -> name.
	$t_cat = array();
	$t_res = db_query( 'SELECT id, name FROM {category}' );
	while( $t_row = db_fetch_array( $t_res ) ) {
		$t_cat[(int)$t_row['id']] = (string)$t_row['name'];
	}

	# Custom-field values for those bugs, in chunks to keep the IN() list sane.
	if( !empty( $t_field_ids ) ) {
		$t_fid_in = implode( ',', array_map( 'intval', array_keys( $t_field_ids ) ) );
		foreach( array_chunk( array_keys( $t_bugs ), 500 ) as $t_chunk ) {
			$t_bug_in = implode( ',', array_map( 'intval', $t_chunk ) );
			$t_res = db_query( 'SELECT bug_id, field_id, value FROM ' . db_get_table( 'custom_field_string' )
				. ' WHERE field_id IN (' . $t_fid_in . ') AND bug_id IN (' . $t_bug_in . ')' );
			while( $t_row = db_fetch_array( $t_res ) ) {
				$t_bid = (int)$t_row['bug_id'];
				$t_fname = $t_field_ids[(int)$t_row['field_id']];
				$t_bugs[$t_bid]['fields'][$t_fname] = (string)$t_row['value'];
			}
		}
	}

	# Assemble.
	$t_rows = array();
	foreach( $t_bugs as $t_bug ) {
		$t_bug['category'] = isset( $t_cat[$t_bug['category_id']] ) ? $t_cat[$t_bug['category_id']] : '';
		$t_rows[] = $t_bug;
	}
	return $t_rows;
}

/**
 * Migrate a source project into the plugin's data structure.
 *
 * @param int    $p_project_id
 * @param bool   $p_dry_run When true, validate and count without writing.
 * @return array Summary with per-entity created/updated/skipped and errors.
 */
function qt_migrate_run( $p_project_id, $p_dry_run = true ) {
	$t_rows = qt_migrate_scan( $p_project_id );
	$t_map = qt_migrate_default_status_map();

	$t_sum = array(
		'tickets'          => count( $t_rows ),
		'persons_created'  => 0,
		'measures_created' => 0,
		'proofs_created'   => 0,
		'proofs_existing'  => 0,
		'skipped'          => 0,
		'errors'           => array(),
	);

	# Local caches so repeated keys within one run resolve without re-querying.
	$t_person_cache = array();
	$t_measure_cache = array();

	foreach( $t_rows as $t_r ) {
		$t_f = $t_r['fields'];
		$t_typ = qt_migrate_typ_from_category( $t_r['category'] );
		if( $t_typ === '' ) {
			# Housekeeping category (Stammdaten) or unmapped – not a proof.
			$t_sum['skipped']++;
			continue;
		}

		$t_pnr  = isset( $t_f['personalnummer'] ) ? trim( $t_f['personalnummer'] ) : '';
		$t_name = isset( $t_f['mitarbeiter'] ) ? trim( $t_f['mitarbeiter'] ) : '';
		$t_schluessel = isset( $t_f['massnahmenschluessel'] ) ? trim( $t_f['massnahmenschluessel'] ) : '';

		if( $t_name === '' && $t_pnr === '' ) {
			$t_sum['errors'][] = array( 'bug_id' => $t_r['bug_id'], 'error' => 'error_migrate_no_person' );
			continue;
		}
		if( $t_schluessel === '' ) {
			$t_sum['errors'][] = array( 'bug_id' => $t_r['bug_id'], 'error' => 'error_migrate_no_measure' );
			continue;
		}

		# --- Person (cache by pnr, else by name) ---------------------------
		$t_pkey = $t_pnr !== '' ? 'p:' . $t_pnr : 'n:' . $t_name;
		if( isset( $t_person_cache[$t_pkey] ) ) {
			$t_person_id = $t_person_cache[$t_pkey];
		} else {
			$t_existing = $t_pnr !== '' ? qt_person_get_by_personalnummer( $t_pnr, 0 ) : false;
			if( $t_existing !== false ) {
				$t_person_id = (int)$t_existing['id'];
			} else {
				$t_split = qt_migrate_split_name( $t_name !== '' ? $t_name : $t_pnr );
				if( !$p_dry_run ) {
					$t_person_id = qt_person_create( array(
						'personalnummer' => $t_pnr, 'typ' => 'intern', 'fremdfirma' => '',
						'nachname' => $t_split['nachname'] !== '' ? $t_split['nachname'] : $t_pnr,
						'vorname' => $t_split['vorname'],
						'abteilung' => isset( $t_f['abteilung'] ) ? trim( $t_f['abteilung'] ) : '',
						'eintritt' => '', 'austritt' => '',
						'vorgesetzter_user_id' => (int)$t_r['handler_id'],
						'verkuerztes_intervall_bis' => '', 'aktiv' => 1,
					) );
				} else {
					$t_person_id = -1;
				}
				$t_sum['persons_created']++;
			}
			$t_person_cache[$t_pkey] = $t_person_id;
		}

		# --- Measure (cache by key) ----------------------------------------
		if( isset( $t_measure_cache[$t_schluessel] ) ) {
			$t_measure_id = $t_measure_cache[$t_schluessel];
		} else {
			$t_existing = qt_massnahme_get_by_schluessel( $t_schluessel, 0 );
			if( $t_existing !== false ) {
				$t_measure_id = (int)$t_existing['id'];
			} else {
				$t_modus = isset( $t_f['faelligkeitsmodus'] ) && trim( $t_f['faelligkeitsmodus'] ) !== ''
					? trim( $t_f['faelligkeitsmodus'] ) : 'kalenderjahr';
				$t_data = array(
					'schluessel' => $t_schluessel, 'bezeichnung' => $t_schluessel, 'typ' => $t_typ,
					'faelligkeitsmodus' => $t_modus,
					'intervall_monate' => isset( $t_f['intervall_monate'] ) ? trim( $t_f['intervall_monate'] ) : '',
					'karenz_tage' => plugin_config_get( 'karenz_tage_default' ), 'vorlaufzeit_tage' => 0,
					'wiederkehrend' => ( $t_typ === 'UW' || $t_typ === 'QB' ) ? 1 : 0,
					'sicherheitsrelevant' => 0,
					'rechtsgrundlage' => isset( $t_f['rechtsgrundlage'] ) ? trim( $t_f['rechtsgrundlage'] ) : '',
					'nachweisart' => isset( $t_f['nachweisart'] ) ? trim( $t_f['nachweisart'] ) : '',
					'aktiv' => 1,
				);
				$t_err = qt_massnahme_validate( $t_data );
				if( !empty( $t_err ) ) {
					$t_sum['errors'][] = array( 'bug_id' => $t_r['bug_id'], 'error' => 'error_migrate_measure_invalid' );
					continue;
				}
				$t_measure_id = $p_dry_run ? -1 : qt_massnahme_create( $t_data );
				$t_sum['measures_created']++;
			}
			$t_measure_cache[$t_schluessel] = $t_measure_id;
		}

		# --- Proof index (by ticket id) ------------------------------------
		if( qt_nachweis_get_by_bug( (int)$t_r['bug_id'] ) !== false ) {
			$t_sum['proofs_existing']++;
			continue;
		}

		$t_soll    = isset( $t_f['soll_termin'] ) ? trim( $t_f['soll_termin'] ) : '';
		$t_gueltig = isset( $t_f['gueltig_bis'] ) ? trim( $t_f['gueltig_bis'] ) : '';
		$t_state   = qt_migrate_status( $t_r['status'], $t_map );
		$t_zyklus  = qt_migrate_zyklus( $t_soll, $t_gueltig );

		if( !$p_dry_run ) {
			$t_now = time();
			db_query( 'INSERT INTO ' . plugin_table( 'nachweis' )
				. ' ( person_id, massnahme_id, bug_id, soll_termin, gueltig_bis, status, zyklus, date_created, date_modified )'
				. ' VALUES ( ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param()
				. ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ' )',
				array( (int)$t_person_id, (int)$t_measure_id, (int)$t_r['bug_id'],
					$t_soll !== '' ? $t_soll : null, $t_gueltig !== '' ? $t_gueltig : null,
					$t_state, $t_zyklus, $t_now, $t_now ) );
		}
		$t_sum['proofs_created']++;
	}

	return $t_sum;
}
