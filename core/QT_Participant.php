<?php
/**
 * QualificationTracker – event participants (F3.2).
 *
 * Selecting who attends a group event (F3.1). The candidate pool is the set of
 * persons who require the event's measure (via their active profile assignments)
 * and currently have a gap for it (fehlt/offen/abgelaufen), reusing the same
 * gap evaluation as the target/actual report (F2.5). Capacity is a soft limit:
 * overbooking is warned, never blocked.
 *
 * Produces no output and never reads $_POST; qt_teilnehmer_capacity_state() and
 * qt_teilnehmer_status_valid() are pure and unit-tested. The child proof tickets
 * (F3.3) and mass completion (F3.4) build on this.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The participant states.
 *
 * @return array
 */
function qt_teilnehmer_statuses() {
	return array( 'eingeplant', 'teilgenommen', 'abwesend' );
}

/**
 * Is the given participant status one of the known states? Pure.
 *
 * @param string $p_status
 * @return bool
 */
function qt_teilnehmer_status_valid( $p_status ) {
	return in_array( $p_status, qt_teilnehmer_statuses(), true );
}

/**
 * Classify how full an event is. Pure.
 *
 * A capacity of 0 (or unset) means "unlimited". Returns:
 *   'unbegrenzt' – no capacity set
 *   'frei'       – capacity set, still room
 *   'voll'       – exactly at capacity
 *   'ueberbucht' – more participants than capacity
 *
 * @param int|null $p_kapazitaet
 * @param int      $p_count
 * @return string
 */
function qt_teilnehmer_capacity_state( $p_kapazitaet, $p_count ) {
	$t_cap = (int)$p_kapazitaet;
	$t_count = (int)$p_count;
	if( $t_cap <= 0 ) {
		return 'unbegrenzt';
	}
	if( $t_count > $t_cap ) {
		return 'ueberbucht';
	}
	if( $t_count === $t_cap ) {
		return 'voll';
	}
	return 'frei';
}

/**
 * Does a candidate's gap kind pass the "due" filter? Pure.
 *
 * An empty filter accepts every gap kind; otherwise the kinds must match.
 *
 * @param string $p_art    Gap kind of the candidate (fehlt/offen/abgelaufen).
 * @param string $p_filter Requested gap kind, or '' for all.
 * @return bool
 */
function qt_teilnehmer_art_matches( $p_art, $p_filter ) {
	return $p_filter === '' || $p_art === $p_filter;
}

/* -------------------------------------------------------------------------- *
 *  Persistence
 * -------------------------------------------------------------------------- */

/**
 * Load the participants of an event with the person's name and department,
 * ordered by name.
 *
 * @param int $p_veranstaltung_id
 * @return array
 */
function qt_teilnehmer_load( $p_veranstaltung_id ) {
	$t_query = 'SELECT t.*, p.nachname, p.vorname, p.personalnummer, p.abteilung'
		. ' FROM ' . plugin_table( 'teilnehmer' ) . ' t'
		. ' JOIN ' . plugin_table( 'person' ) . ' p ON p.id = t.person_id'
		. ' WHERE t.veranstaltung_id = ' . db_param()
		. ' ORDER BY p.nachname, p.vorname';
	$t_result = db_query( $t_query, array( (int)$p_veranstaltung_id ) );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * The set of person ids already planned for an event.
 *
 * @param int $p_veranstaltung_id
 * @return array Map person_id => true.
 */
function qt_teilnehmer_person_ids( $p_veranstaltung_id ) {
	$t_result = db_query( 'SELECT person_id FROM ' . plugin_table( 'teilnehmer' )
		. ' WHERE veranstaltung_id = ' . db_param(), array( (int)$p_veranstaltung_id ) );
	$t_ids = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_ids[(int)$t_row['person_id']] = true;
	}
	return $t_ids;
}

/**
 * Number of participants of an event.
 *
 * @param int $p_veranstaltung_id
 * @return int
 */
function qt_teilnehmer_count( $p_veranstaltung_id ) {
	$t_result = db_query( 'SELECT COUNT(*) AS c FROM ' . plugin_table( 'teilnehmer' )
		. ' WHERE veranstaltung_id = ' . db_param(), array( (int)$p_veranstaltung_id ) );
	$t_row = db_fetch_array( $t_result );
	return (int)$t_row['c'];
}

/**
 * Fetch a single participant row.
 *
 * @param int $p_id
 * @return array|false
 */
function qt_teilnehmer_get( $p_id ) {
	$t_result = db_query( 'SELECT * FROM ' . plugin_table( 'teilnehmer' )
		. ' WHERE id = ' . db_param(), array( (int)$p_id ) );
	$t_row = db_fetch_array( $t_result );
	return $t_row === false ? false : $t_row;
}

/**
 * Add a person to an event. Idempotent: a person already planned is not added
 * twice. Returns true when a row was inserted.
 *
 * @param int $p_veranstaltung_id
 * @param int $p_person_id
 * @return bool
 */
function qt_teilnehmer_add( $p_veranstaltung_id, $p_person_id ) {
	$t_existing = db_query( 'SELECT id FROM ' . plugin_table( 'teilnehmer' )
		. ' WHERE veranstaltung_id = ' . db_param() . ' AND person_id = ' . db_param(),
		array( (int)$p_veranstaltung_id, (int)$p_person_id ) );
	if( db_fetch_array( $t_existing ) !== false ) {
		return false;
	}
	db_query( 'INSERT INTO ' . plugin_table( 'teilnehmer' )
		. ' ( veranstaltung_id, person_id, status, date_created )'
		. ' VALUES ( ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ' )',
		array( (int)$p_veranstaltung_id, (int)$p_person_id, 'eingeplant', time() ) );
	return true;
}

/**
 * Remove a participant by its row id.
 *
 * @param int $p_id
 * @return void
 */
function qt_teilnehmer_remove( $p_id ) {
	db_query( 'DELETE FROM ' . plugin_table( 'teilnehmer' ) . ' WHERE id = ' . db_param(),
		array( (int)$p_id ) );
}

/**
 * Store the proof-ticket id on a participant row.
 *
 * @param int $p_id
 * @param int $p_bug_id
 * @return void
 */
function qt_teilnehmer_set_bug( $p_id, $p_bug_id ) {
	db_query( 'UPDATE ' . plugin_table( 'teilnehmer' ) . ' SET bug_id = ' . db_param()
		. ' WHERE id = ' . db_param(), array( (int)$p_bug_id, (int)$p_id ) );
}

/**
 * Set the participation status of a participant row.
 *
 * @param int    $p_id
 * @param string $p_status
 * @return void
 */
function qt_teilnehmer_set_status( $p_id, $p_status ) {
	$t_status = qt_teilnehmer_status_valid( $p_status ) ? $p_status : 'eingeplant';
	db_query( 'UPDATE ' . plugin_table( 'teilnehmer' ) . ' SET status = ' . db_param()
		. ' WHERE id = ' . db_param(), array( $t_status, (int)$p_id ) );
}

/* -------------------------------------------------------------------------- *
 *  Child proof tickets (F3.3)
 * -------------------------------------------------------------------------- */

/**
 * Create the per-participant proof tickets for an event as children of the
 * event's parent ticket (F3.3).
 *
 * Honours the "one ticket per person × measure × cycle" invariant: if a person
 * already has an open proof ticket for the event's measure (e.g. from the chain
 * generator F2.3), that ticket is reused and only linked as a child; otherwise a
 * new proof ticket is created. Idempotent per participant via the stored bug_id.
 *
 * Side-effectful (creates MantisBT tickets); integration-tested, not a unit.
 *
 * @param array  $p_event Event row.
 * @param string $p_today ISO date "today".
 * @return array Summary: parent, created, linked, skipped, errors[].
 */
function qt_teilnehmer_generate_tickets( array $p_event, $p_today ) {
	$t_summary = array( 'parent' => 0, 'created' => 0, 'linked' => 0, 'skipped' => 0, 'errors' => array() );

	$t_project_id = (int)plugin_config_get( 'zielprojekt_id' );
	if( $t_project_id <= 0 ) {
		$t_summary['errors'][] = 'error_no_zielprojekt';
		return $t_summary;
	}

	$t_massnahme = qt_massnahme_get( (int)$p_event['massnahme_id'] );
	if( $t_massnahme === false ) {
		$t_summary['errors'][] = 'error_event_massnahme_required';
		return $t_summary;
	}

	$t_participants = qt_teilnehmer_load( (int)$p_event['id'] );
	if( empty( $t_participants ) ) {
		return $t_summary;
	}

	# Ensure fields/categories exist and are linked to the target project.
	qt_custom_fields_link( $t_project_id );
	$t_category_ids = qt_generator_ensure_categories( $t_project_id );
	$t_field_ids = qt_generator_field_ids();

	# The parent "Sammeltermin" ticket the children hang under.
	$t_parent = qt_event_ensure_parent_ticket( $p_event, $t_massnahme, $t_project_id, $t_category_ids );
	if( $t_parent > 0 && (int)$p_event['eltern_bug_id'] <= 0 ) {
		$t_summary['parent'] = 1;
	}

	foreach( $t_participants as $t_p ) {
		if( (int)$t_p['bug_id'] > 0 ) {
			$t_summary['skipped']++;
			continue;
		}
		$t_person = qt_person_get( (int)$t_p['person_id'] );
		if( $t_person === false ) {
			continue;
		}

		# Reuse an existing open proof ticket (one-ticket invariant) or create one.
		$t_open = qt_nachweis_find_open( (int)$t_person['id'], (int)$t_massnahme['id'] );
		if( $t_open !== false && (int)$t_open['bug_id'] > 0 && bug_exists( (int)$t_open['bug_id'] ) ) {
			$t_bug_id = (int)$t_open['bug_id'];
			$t_summary['linked']++;
		} else {
			$t_soll = qt_generator_initial_soll( $t_massnahme, $t_person, $p_today );
			$t_bug_id = qt_generator_place_ticket(
				$t_person, $t_massnahme, $t_soll, $t_project_id, $t_category_ids, $t_field_ids );
			$t_summary['created']++;
		}

		# Wire the child under the parent event ticket.
		if( $t_parent > 0 && $t_bug_id !== $t_parent
			&& !relationship_same_type_exists( $t_bug_id, $t_parent, BUG_DEPENDANT ) ) {
			relationship_add( $t_bug_id, $t_parent, BUG_DEPENDANT );
		}

		qt_teilnehmer_set_bug( (int)$t_p['id'], $t_bug_id );
	}

	return $t_summary;
}

/* -------------------------------------------------------------------------- *
 *  Mass completion (F3.4)
 * -------------------------------------------------------------------------- */

/**
 * Complete an event for all its participants in one action (F3.4).
 *
 * Present participants get their proof completed via QT_Completion (F2.8):
 * durchgefuehrt_am / gueltig_bis / durchfuehrender are set, the proof becomes
 * valid and – for recurring measures – a follow-up ticket is created; the
 * participant is marked 'teilgenommen'. Absent participants are marked
 * 'abwesend'; their proof ticket stays open, so they remain due and are picked
 * up again for the next event. The event itself is set to 'durchgefuehrt'.
 *
 * Side-effectful; integration-tested.
 *
 * @param array  $p_event            Event row.
 * @param array  $p_present_ids      Person ids that attended.
 * @param string $p_durchgefuehrt_am ISO date the event was held.
 * @param string $p_durchfuehrender  Instructor recorded on the proofs.
 * @return array Summary: completed, followup_created, absent, skipped, errors[].
 */
function qt_teilnehmer_complete_event( array $p_event, array $p_present_ids, $p_durchgefuehrt_am, $p_durchfuehrender ) {
	$t_summary = array( 'completed' => 0, 'followup_created' => 0, 'absent' => 0, 'skipped' => 0, 'errors' => array() );

	$t_present = array();
	foreach( $p_present_ids as $t_pid ) {
		$t_present[(int)$t_pid] = true;
	}

	foreach( qt_teilnehmer_load( (int)$p_event['id'] ) as $t_p ) {
		$t_row_id = (int)$t_p['id'];
		$t_person_id = (int)$t_p['person_id'];
		$t_bug = (int)$t_p['bug_id'];

		if( !isset( $t_present[$t_person_id] ) ) {
			qt_teilnehmer_set_status( $t_row_id, 'abwesend' );
			$t_summary['absent']++;
			continue;
		}

		# Present: complete the proof behind the child ticket.
		if( $t_bug <= 0 ) {
			$t_summary['skipped']++;
			continue;
		}
		$t_nw = qt_nachweis_get_by_bug( $t_bug );
		if( $t_nw !== false && $t_nw['status'] !== 'gueltig' ) {
			$t_res = qt_completion_complete( (int)$t_nw['id'], $p_durchgefuehrt_am, '', $p_durchfuehrender );
			$t_summary['completed'] += (int)$t_res['completed'];
			$t_summary['followup_created'] += (int)$t_res['followup_created'];
		} else {
			$t_summary['skipped']++;
		}
		qt_teilnehmer_set_status( $t_row_id, 'teilgenommen' );
	}

	qt_event_update_status( (int)$p_event['id'], 'durchgefuehrt' );

	return $t_summary;
}

/* -------------------------------------------------------------------------- *
 *  Proof attachment (F3.6)
 * -------------------------------------------------------------------------- */

/**
 * Attach the scanned attendance list once to the parent event ticket and add a
 * reference note to every child proof ticket (F3.6).
 *
 * The file lives only on the parent ticket; each child gets a bugnote pointing
 * there, so the signed list is stored once, not per participant. Requires the
 * parent ticket to exist (created by F3.3).
 *
 * Side-effectful; integration-tested.
 *
 * @param array  $p_event     Event row.
 * @param array  $p_file      An $_FILES entry (from gpc_get_file()).
 * @param string $p_note_text Localised reference note added to each child.
 * @return array Summary: attached, referenced, errors[].
 */
function qt_teilnehmer_attach_nachweis( array $p_event, array $p_file, $p_note_text ) {
	$t_summary = array( 'attached' => 0, 'referenced' => 0, 'errors' => array() );

	$t_parent = (int)$p_event['eltern_bug_id'];
	if( $t_parent <= 0 || !bug_exists( $t_parent ) ) {
		$t_summary['errors'][] = 'error_no_parent';
		return $t_summary;
	}

	# Store the scan once, on the parent event ticket.
	file_add( $t_parent, $p_file, 'bug' );
	$t_summary['attached'] = 1;

	# Reference it from every child proof ticket (no duplicate upload).
	foreach( qt_teilnehmer_load( (int)$p_event['id'] ) as $t_p ) {
		$t_bug = (int)$t_p['bug_id'];
		if( $t_bug > 0 && bug_exists( $t_bug ) ) {
			bugnote_add( $t_bug, $p_note_text, '0:00', false, BUGNOTE, '', null, false );
			$t_summary['referenced']++;
		}
	}

	return $t_summary;
}

/* -------------------------------------------------------------------------- *
 *  Candidate pool
 * -------------------------------------------------------------------------- */

/**
 * The persons eligible to be added to an event for the given measure: active
 * persons who require the measure (via active profile assignments) and have a
 * gap for it. Persons already planned for the event are excluded.
 *
 * @param int    $p_massnahme_id
 * @param int    $p_veranstaltung_id  Event whose current participants to exclude.
 * @param string $p_today             ISO date "today".
 * @param string $p_filter_abteilung  Optional department filter.
 * @param string $p_filter_art        Optional gap-kind filter (fehlt/offen/abgelaufen).
 * @return array List of rows: person_id, person, personalnummer, abteilung, art.
 */
function qt_teilnehmer_candidates( $p_massnahme_id, $p_veranstaltung_id, $p_today,
		$p_filter_abteilung = '', $p_filter_art = '' ) {
	$t_massnahme_id = (int)$p_massnahme_id;
	$t_already = qt_teilnehmer_person_ids( $p_veranstaltung_id );
	$t_candidates = array();

	foreach( qt_person_load_all( $p_filter_abteilung ) as $t_person ) {
		if( !$t_person['aktiv'] ) {
			continue;
		}
		$t_pid = (int)$t_person['id'];
		if( isset( $t_already[$t_pid] ) ) {
			continue;
		}

		# Does this person require the event's measure at all?
		$t_required = false;
		foreach( qt_generator_required_massnahmen( $t_pid, $p_today ) as $t_m ) {
			if( (int)$t_m['id'] === $t_massnahme_id ) {
				$t_required = true;
				break;
			}
		}
		if( !$t_required ) {
			continue;
		}

		# Only persons with an actual gap for the measure are due.
		$t_rows = array();
		foreach( qt_nachweis_load_for_person( $t_pid ) as $t_nw ) {
			if( (int)$t_nw['massnahme_id'] === $t_massnahme_id ) {
				$t_rows[] = $t_nw;
			}
		}
		$t_art = qt_sollist_evaluate( $t_rows, $p_today );
		if( $t_art === '' || !qt_teilnehmer_art_matches( $t_art, $p_filter_art ) ) {
			continue;
		}

		$t_candidates[] = array(
			'person_id'      => $t_pid,
			'person'         => trim( $t_person['nachname'] . ', ' . $t_person['vorname'], ', ' ),
			'personalnummer' => $t_person['personalnummer'],
			'abteilung'      => $t_person['abteilung'],
			'art'            => $t_art,
		);
	}

	return $t_candidates;
}
