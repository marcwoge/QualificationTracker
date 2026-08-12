<?php
/**
 * QualificationTracker – chain generator (F2.3).
 *
 * Turns a person's active profile assignments into MantisBT proof tickets (one
 * ticket per required measure) with their custom fields, the supervisor as
 * handler, the due date from QT_DueDateCalculator, the "depends on" relationships
 * from the prerequisites, and a row in the derived qt_nachweis index.
 *
 * Decisions this implements: G1 (status mapping), G2 (depends on), G3 (initial
 * chain only – recurring cycles are F2.8), G4 (field population), G5 (qt_nachweis
 * index for idempotency and matrix performance).
 *
 * The MantisBT ticket is the source of truth; qt_nachweis is a derived pointer.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Default mapping of domain proof states to MantisBT status values (G1). Pure.
 *
 * @return array
 */
function qt_status_default_mapping() {
	return array(
		'offen'         => 10,   # NEW_
		'geplant'       => 30,   # acknowledged
		'durchgefuehrt' => 40,   # confirmed
		'gueltig'       => 80,   # resolved
		'abgelaufen'    => 20,   # feedback
		'entfallen'     => 90,   # closed
	);
}

/**
 * Effective status mapping (configured values over defaults).
 *
 * @return array
 */
function qt_status_mapping() {
	$t_config = (array)plugin_config_get( 'status_mapping' );
	return array_merge( qt_status_default_mapping(), $t_config );
}

/**
 * Resolve a domain state to a MantisBT status value.
 *
 * @param string $p_domain
 * @return int
 */
function qt_status_to_mantis( $p_domain ) {
	$t_map = qt_status_mapping();
	return isset( $t_map[$p_domain] ) ? (int)$t_map[$p_domain] : 10;
}

/**
 * Map a measure type to its MantisBT category name (concept §4). Pure.
 *
 * @return array
 */
function qt_generator_category_map() {
	return array(
		'UW' => 'Unterweisung',
		'QU' => 'Qualifikation',
		'QB' => 'Qualifikation',
		'BE' => 'Beauftragung',
		'VO' => 'Vorsorge',
	);
}

/**
 * Ensure the measure-type categories exist in the target project.
 *
 * @param int $p_project_id
 * @return array Map category name => id.
 */
function qt_generator_ensure_categories( $p_project_id ) {
	$t_ids = array();
	foreach( array_unique( array_values( qt_generator_category_map() ) ) as $t_name ) {
		$t_id = category_get_id_by_name( $t_name, $p_project_id, false );
		if( $t_id === false ) {
			$t_id = category_add( $p_project_id, $t_name );
		}
		$t_ids[$t_name] = (int)$t_id;
	}
	return $t_ids;
}

/* -------------------------------------------------------------------------- *
 *  qt_nachweis derived index
 * -------------------------------------------------------------------------- */

/**
 * The open (still active) proof for a person and measure, or false. "Open"
 * means any state other than 'entfallen'.
 *
 * @param int $p_person_id
 * @param int $p_massnahme_id
 * @return array|false
 */
function qt_nachweis_find_open( $p_person_id, $p_massnahme_id ) {
	$t_result = db_query(
		'SELECT * FROM ' . plugin_table( 'nachweis' )
		. ' WHERE person_id = ' . db_param() . ' AND massnahme_id = ' . db_param()
		. " AND status <> 'entfallen'",
		array( (int)$p_person_id, (int)$p_massnahme_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row === false ? false : $t_row;
}

/**
 * Record a proof in the index.
 *
 * @param int         $p_person_id
 * @param int         $p_massnahme_id
 * @param int         $p_bug_id
 * @param string|null $p_soll_termin
 * @param string      $p_status
 * @param string      $p_zyklus
 * @return int
 */
function qt_nachweis_record( $p_person_id, $p_massnahme_id, $p_bug_id, $p_soll_termin, $p_status, $p_zyklus ) {
	$t_table = plugin_table( 'nachweis' );
	$t_now = time();
	db_query(
		'INSERT INTO ' . $t_table
		. ' ( person_id, massnahme_id, bug_id, soll_termin, gueltig_bis, status, zyklus, date_created, date_modified )'
		. ' VALUES ( ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param()
		. ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ' )',
		array(
			(int)$p_person_id, (int)$p_massnahme_id, (int)$p_bug_id,
			( $p_soll_termin === null || $p_soll_termin === '' ) ? null : $p_soll_termin,
			null,
			(string)$p_status,
			(string)$p_zyklus,
			$t_now, $t_now,
		) );
	return db_insert_id( $t_table );
}

/**
 * All index rows for a person.
 *
 * @param int $p_person_id
 * @return array
 */
function qt_nachweis_load_for_person( $p_person_id ) {
	$t_result = db_query( 'SELECT * FROM ' . plugin_table( 'nachweis' )
		. ' WHERE person_id = ' . db_param() . ' ORDER BY massnahme_id',
		array( (int)$p_person_id ) );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/* -------------------------------------------------------------------------- *
 *  Planning
 * -------------------------------------------------------------------------- */

/**
 * The distinct active measures a person requires, from their active profile
 * assignments. An assignment is active when it has no end date or the end date
 * is not in the past.
 *
 * @param int    $p_person_id
 * @param string $p_today ISO date "today".
 * @return array List of measure rows.
 */
function qt_generator_required_massnahmen( $p_person_id, $p_today ) {
	$t_query =
		'SELECT DISTINCT m.* FROM ' . plugin_table( 'zuordnung' ) . ' z'
		. ' JOIN ' . plugin_table( 'profil' ) . ' pr ON pr.id = z.profil_id AND pr.aktiv = 1'
		. ' JOIN ' . plugin_table( 'profil_massnahme' ) . ' pm ON pm.profil_id = z.profil_id'
		. ' JOIN ' . plugin_table( 'massnahme' ) . ' m ON m.id = pm.massnahme_id AND m.aktiv = 1'
		. ' WHERE z.person_id = ' . db_param()
		. ' AND ( z.gueltig_bis IS NULL OR z.gueltig_bis >= ' . db_param() . ' )'
		. ' ORDER BY m.schluessel';
	$t_result = db_query( $t_query, array( (int)$p_person_id, $p_today ) );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Compute the initial target date for a measure and person (G3).
 * 'extern' measures get no computed date – it comes from the document.
 *
 * @param array  $p_massnahme
 * @param array  $p_person
 * @param string $p_today
 * @return string|null ISO date or null.
 */
function qt_generator_initial_soll( array $p_massnahme, array $p_person, $p_today ) {
	if( $p_massnahme['faelligkeitsmodus'] === 'extern' ) {
		return null;
	}
	$t_eintritt = ( isset( $p_person['eintritt'] ) && $p_person['eintritt'] !== null && $p_person['eintritt'] !== '' )
		? $p_person['eintritt']
		: $p_today;
	$t_frist = (int)plugin_config_get( 'ersteinweisung_frist_tage' );
	return QT_DueDateCalculator::initial_soll_termin( $t_eintritt, $t_frist, $p_massnahme['faelligkeitsmodus'] );
}

/**
 * Build the generation plan for a person: one item per required measure with
 * the action (create / skip) and the initial target date.
 *
 * @param array  $p_person
 * @param string $p_today
 * @return array List of items: [ massnahme, soll_termin, action, reason ].
 */
function qt_generator_plan( array $p_person, $p_today ) {
	$t_plan = array();
	foreach( qt_generator_required_massnahmen( (int)$p_person['id'], $p_today ) as $t_m ) {
		$t_existing = qt_nachweis_find_open( (int)$p_person['id'], (int)$t_m['id'] );
		if( $t_existing !== false ) {
			$t_plan[] = array( 'massnahme' => $t_m, 'soll_termin' => null, 'action' => 'skip', 'reason' => 'exists' );
			continue;
		}
		$t_plan[] = array(
			'massnahme'   => $t_m,
			'soll_termin' => qt_generator_initial_soll( $t_m, $p_person, $p_today ),
			'action'      => 'create',
			'reason'      => '',
		);
	}
	return $t_plan;
}

/* -------------------------------------------------------------------------- *
 *  Execution
 * -------------------------------------------------------------------------- */

/**
 * Map of custom-field name => id (only those that exist).
 *
 * @return array
 */
function qt_generator_field_ids() {
	$t_ids = array();
	foreach( qt_custom_fields_definitions() as $t_def ) {
		$t_id = custom_field_get_id_from_name( $t_def['name'] );
		if( $t_id !== false ) {
			$t_ids[$t_def['name']] = (int)$t_id;
		}
	}
	return $t_ids;
}

/**
 * Create one proof ticket for a person and measure (G4). Returns the bug id.
 *
 * @param array    $p_person
 * @param array    $p_massnahme
 * @param string|null $p_soll_termin
 * @param int      $p_project_id
 * @param array    $p_category_ids Map category name => id.
 * @param array    $p_field_ids    Map custom-field name => id.
 * @return int Bug id.
 */
function qt_generator_create_ticket( array $p_person, array $p_massnahme, $p_soll_termin, $p_project_id, array $p_category_ids, array $p_field_ids ) {
	$t_name = trim( $p_person['nachname'] . ', ' . $p_person['vorname'], ', ' );
	$t_zyklus = ( $p_soll_termin === null ) ? '' : substr( $p_soll_termin, 0, 4 );
	$t_category = qt_generator_category_map();
	$t_category_id = isset( $t_category[$p_massnahme['typ']] )
		? (int)$p_category_ids[$t_category[$p_massnahme['typ']]]
		: (int)reset( $p_category_ids );

	$t_summary = $p_massnahme['schluessel'] . ' ' . $p_massnahme['bezeichnung'] . ' – ' . $t_name
		. ( $t_zyklus !== '' ? ' (' . $t_zyklus . ')' : '' );
	$t_summary = mb_substr( $t_summary, 0, 128 );

	$t_description = $p_massnahme['bezeichnung']
		. ( $p_massnahme['rechtsgrundlage'] !== null && $p_massnahme['rechtsgrundlage'] !== '' ? "\n" . $p_massnahme['rechtsgrundlage'] : '' )
		. "\n" . $t_name
		. ( $p_person['personalnummer'] !== null && $p_person['personalnummer'] !== '' ? ' (' . $p_person['personalnummer'] . ')' : '' )
		. ( $p_person['abteilung'] !== '' ? ' – ' . $p_person['abteilung'] : '' );

	$t_handler = (int)$p_person['vorgesetzter_user_id'];
	if( $t_handler <= 0 || !user_exists( $t_handler ) ) {
		$t_handler = 0;
	}

	$t_bug = new BugData;
	$t_bug->project_id  = (int)$p_project_id;
	$t_bug->reporter_id = auth_get_current_user_id();
	$t_bug->handler_id  = $t_handler;
	$t_bug->category_id = $t_category_id;
	$t_bug->summary     = $t_summary;
	$t_bug->description = $t_description;
	# Note: when a handler is set, MantisBT auto-promotes the status to
	# "assigned" on create. That is fine – the authoritative domain state lives
	# in qt_nachweis; the Mantis status is only a coarse open/closed signal.
	$t_bug->status      = qt_status_to_mantis( 'offen' );
	if( $p_soll_termin !== null && $p_soll_termin !== '' ) {
		$t_bug->due_date = strtotime( $p_soll_termin . ' 00:00:00' );
	}
	$t_bug_id = $t_bug->create();

	# --- custom fields (G4) ----------------------------------------------
	$t_values = array(
		'mitarbeiter'          => $t_name,
		'personalnummer'       => (string)$p_person['personalnummer'],
		'abteilung'            => (string)$p_person['abteilung'],
		'massnahmenschluessel' => $p_massnahme['schluessel'],
		'rechtsgrundlage'      => (string)$p_massnahme['rechtsgrundlage'],
		'intervall_monate'     => $p_massnahme['intervall_monate'] === null ? '' : (string)$p_massnahme['intervall_monate'],
		'faelligkeitsmodus'    => $p_massnahme['faelligkeitsmodus'],
		'nachweisart'          => (string)$p_massnahme['nachweisart'],
	);
	foreach( $t_values as $t_name_cf => $t_val ) {
		if( isset( $p_field_ids[$t_name_cf] ) ) {
			custom_field_set_value( $p_field_ids[$t_name_cf], $t_bug_id, $t_val );
		}
	}
	# soll_termin is a date custom field -> store as timestamp.
	if( $p_soll_termin !== null && $p_soll_termin !== '' && isset( $p_field_ids['soll_termin'] ) ) {
		custom_field_set_value( $p_field_ids['soll_termin'], $t_bug_id, strtotime( $p_soll_termin . ' 00:00:00' ) );
	}

	return $t_bug_id;
}

/**
 * Execute a plan for a person: create the tickets, then wire the prerequisite
 * "depends on" relationships (G2).
 *
 * @param array  $p_person
 * @param array  $p_plan
 * @param string $p_today
 * @return array Summary: created, skipped, errors[].
 */
function qt_generator_execute( array $p_person, array $p_plan, $p_today ) {
	$t_summary = array( 'created' => 0, 'skipped' => 0, 'errors' => array() );

	$t_project_id = (int)plugin_config_get( 'zielprojekt_id' );
	if( $t_project_id <= 0 ) {
		$t_summary['errors'][] = 'error_no_zielprojekt';
		return $t_summary;
	}

	# Make sure the fields and categories exist in / are linked to the project.
	qt_custom_fields_link( $t_project_id );
	$t_category_ids = qt_generator_ensure_categories( $t_project_id );
	$t_field_ids = qt_generator_field_ids();

	# massnahme_id => bug_id for the tickets created (for relationship wiring).
	$t_bug_by_massnahme = array();

	foreach( $p_plan as $t_item ) {
		if( $t_item['action'] !== 'create' ) {
			$t_summary['skipped']++;
			continue;
		}
		$t_m = $t_item['massnahme'];
		$t_bug_id = qt_generator_create_ticket(
			$p_person, $t_m, $t_item['soll_termin'], $t_project_id, $t_category_ids, $t_field_ids );

		$t_zyklus = ( $t_item['soll_termin'] === null ) ? '' : substr( $t_item['soll_termin'], 0, 4 );
		qt_nachweis_record( (int)$p_person['id'], (int)$t_m['id'], $t_bug_id, $t_item['soll_termin'], 'offen', $t_zyklus );

		$t_bug_by_massnahme[(int)$t_m['id']] = $t_bug_id;
		$t_summary['created']++;
	}

	# --- depends-on relationships (G2) -----------------------------------
	# The dependent ticket "depends on" the prerequisite ticket. Resolve the
	# prerequisite's ticket from the just-created ones or an existing open proof.
	foreach( $t_bug_by_massnahme as $t_massnahme_id => $t_bug_id ) {
		foreach( qt_vorbedingung_get_for( $t_massnahme_id ) as $t_prereq_id ) {
			$t_prereq_bug = 0;
			if( isset( $t_bug_by_massnahme[$t_prereq_id] ) ) {
				$t_prereq_bug = $t_bug_by_massnahme[$t_prereq_id];
			} else {
				$t_open = qt_nachweis_find_open( (int)$p_person['id'], (int)$t_prereq_id );
				if( $t_open !== false && (int)$t_open['bug_id'] > 0 ) {
					$t_prereq_bug = (int)$t_open['bug_id'];
				}
			}
			if( $t_prereq_bug > 0 ) {
				relationship_add( $t_bug_id, $t_prereq_bug, BUG_DEPENDANT );
			}
		}
	}

	return $t_summary;
}

/**
 * Plan and execute generation for a person.
 *
 * @param int    $p_person_id
 * @param string $p_today ISO date "today".
 * @return array Summary.
 */
function qt_generator_run_for_person( $p_person_id, $p_today ) {
	$t_person = qt_person_get( $p_person_id );
	if( $t_person === false ) {
		return array( 'created' => 0, 'skipped' => 0, 'errors' => array( 'error_person_not_found' ) );
	}
	$t_plan = qt_generator_plan( $t_person, $p_today );
	return qt_generator_execute( $t_person, $t_plan, $p_today );
}

/**
 * Company-wide dry-run plan (F2.6): flat rows of what would be created / skipped
 * across all active persons, optionally filtered by department.
 *
 * @param string $p_today
 * @param string $p_abteilung
 * @return array List of rows: person, personalnummer, abteilung, schluessel,
 *               bezeichnung, typ, modus, soll_termin, action.
 */
function qt_generator_plan_all( $p_today, $p_abteilung = '' ) {
	$t_rows = array();
	foreach( qt_person_load_all( $p_abteilung ) as $t_person ) {
		if( !$t_person['aktiv'] ) {
			continue;
		}
		$t_name = trim( $t_person['nachname'] . ', ' . $t_person['vorname'], ', ' );
		foreach( qt_generator_plan( $t_person, $p_today ) as $t_item ) {
			$t_m = $t_item['massnahme'];
			$t_rows[] = array(
				'person'         => $t_name,
				'personalnummer' => $t_person['personalnummer'],
				'abteilung'      => $t_person['abteilung'],
				'schluessel'     => $t_m['schluessel'],
				'bezeichnung'    => $t_m['bezeichnung'],
				'typ'            => $t_m['typ'],
				'modus'          => $t_m['faelligkeitsmodus'],
				'soll_termin'    => $t_item['soll_termin'],
				'action'         => $t_item['action'],
			);
		}
	}
	return $t_rows;
}

/**
 * Company-wide generation (F2.6): run the generator for every active person,
 * optionally filtered by department.
 *
 * @param string $p_today
 * @param string $p_abteilung
 * @return array Summary: created, skipped, persons, errors[].
 */
function qt_generator_run_all( $p_today, $p_abteilung = '' ) {
	$t_sum = array( 'created' => 0, 'skipped' => 0, 'persons' => 0, 'errors' => array() );
	foreach( qt_person_load_all( $p_abteilung ) as $t_person ) {
		if( !$t_person['aktiv'] ) {
			continue;
		}
		$t_s = qt_generator_run_for_person( (int)$t_person['id'], $p_today );
		$t_sum['created'] += $t_s['created'];
		$t_sum['skipped'] += $t_s['skipped'];
		$t_sum['persons']++;
		foreach( $t_s['errors'] as $t_e ) {
			$t_sum['errors'][] = $t_e;
		}
	}
	return $t_sum;
}
