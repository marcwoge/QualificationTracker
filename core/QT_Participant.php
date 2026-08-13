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
