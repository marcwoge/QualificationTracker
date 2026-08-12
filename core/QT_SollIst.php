<?php
/**
 * QualificationTracker – target/actual check (F2.5).
 *
 * Read-only report: which person needs a measure (per their active profile
 * assignments) for which no valid proof exists — the gap that plain expiry
 * lists miss. Includes the "appointment without qualification" case: a measure
 * that IS valid but whose prerequisite is not.
 *
 * The proof state comes from the derived qt_nachweis index (F2.3). The
 * evaluation helpers qt_sollist_is_valid() and qt_sollist_evaluate() are pure
 * and unit-tested; the report itself reads the database.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Is a single proof (qt_nachweis row) currently valid? Pure.
 *
 * @param array  $p_nachweis
 * @param string $p_today ISO date.
 * @return bool
 */
function qt_sollist_is_valid( array $p_nachweis, $p_today ) {
	if( $p_nachweis['status'] !== 'gueltig' ) {
		return false;
	}
	$t_bis = $p_nachweis['gueltig_bis'];
	return $t_bis === null || $t_bis === '' || $t_bis >= $p_today;
}

/**
 * Evaluate all proofs a person has for one measure and return the gap kind.
 * Pure function.
 *
 * '' (empty)   = a valid proof exists, no gap
 * 'fehlt'      = no (non-cancelled) proof at all
 * 'offen'      = a proof is in progress but not yet valid
 * 'abgelaufen' = the proof has expired
 *
 * @param array  $p_rows  qt_nachweis rows for this person and measure.
 * @param string $p_today
 * @return string
 */
function qt_sollist_evaluate( array $p_rows, $p_today ) {
	$t_valid = false;
	$t_expired = false;
	$t_open = false;

	foreach( $p_rows as $t_row ) {
		if( $t_row['status'] === 'entfallen' ) {
			continue;
		}
		if( qt_sollist_is_valid( $t_row, $p_today ) ) {
			$t_valid = true;
		} else if( $t_row['status'] === 'abgelaufen'
			|| ( $t_row['gueltig_bis'] !== null && $t_row['gueltig_bis'] !== '' && $t_row['gueltig_bis'] < $p_today ) ) {
			$t_expired = true;
		} else {
			$t_open = true;
		}
	}

	if( $t_valid ) {
		return '';
	}
	if( $t_expired ) {
		return 'abgelaufen';
	}
	if( $t_open ) {
		return 'offen';
	}
	return 'fehlt';
}

/**
 * Build the target/actual report.
 *
 * @param string $p_today          ISO date "today".
 * @param string $p_filter_abteilung Optional department filter.
 * @return array List of gap rows: person_id, person, personalnummer, abteilung,
 *               massnahme_id, schluessel, bezeichnung, typ, art, detail.
 */
function qt_sollist_gaps( $p_today, $p_filter_abteilung = '' ) {
	$t_gaps = array();

	foreach( qt_person_load_all( $p_filter_abteilung ) as $t_person ) {
		if( !$t_person['aktiv'] ) {
			continue;
		}
		$t_pid = (int)$t_person['id'];
		$t_name = trim( $t_person['nachname'] . ', ' . $t_person['vorname'], ', ' );

		$t_required = qt_generator_required_massnahmen( $t_pid, $p_today );
		if( empty( $t_required ) ) {
			continue;
		}

		# Group this person's proofs by measure.
		$t_by_massnahme = array();
		foreach( qt_nachweis_load_for_person( $t_pid ) as $t_nw ) {
			$t_by_massnahme[(int)$t_nw['massnahme_id']][] = $t_nw;
		}

		$t_valid_map = array();
		foreach( $t_required as $t_m ) {
			$t_mid = (int)$t_m['id'];
			$t_rows = isset( $t_by_massnahme[$t_mid] ) ? $t_by_massnahme[$t_mid] : array();
			$t_art = qt_sollist_evaluate( $t_rows, $p_today );
			$t_valid_map[$t_mid] = ( $t_art === '' );

			if( $t_art !== '' ) {
				$t_gaps[] = qt_sollist_row( $t_person, $t_name, $t_m, $t_art, '' );
			}
		}

		# "Appointment without qualification": a valid measure whose prerequisite
		# is not valid for this person.
		foreach( $t_required as $t_m ) {
			$t_mid = (int)$t_m['id'];
			if( empty( $t_valid_map[$t_mid] ) ) {
				continue;
			}
			foreach( qt_vorbedingung_get_for( $t_mid ) as $t_prereq_id ) {
				$t_rows = isset( $t_by_massnahme[$t_prereq_id] ) ? $t_by_massnahme[$t_prereq_id] : array();
				if( qt_sollist_evaluate( $t_rows, $p_today ) !== '' ) {
					$t_prm = qt_massnahme_get( $t_prereq_id );
					$t_detail = $t_prm !== false ? $t_prm['schluessel'] : ( '#' . $t_prereq_id );
					$t_gaps[] = qt_sollist_row( $t_person, $t_name, $t_m, 'vorbedingung_fehlt', $t_detail );
				}
			}
		}
	}

	return $t_gaps;
}

/**
 * Assemble one gap row.
 *
 * @param array  $p_person
 * @param string $p_name
 * @param array  $p_massnahme
 * @param string $p_art
 * @param string $p_detail
 * @return array
 */
function qt_sollist_row( array $p_person, $p_name, array $p_massnahme, $p_art, $p_detail ) {
	return array(
		'person_id'      => (int)$p_person['id'],
		'person'         => $p_name,
		'personalnummer' => $p_person['personalnummer'],
		'abteilung'      => $p_person['abteilung'],
		'massnahme_id'   => (int)$p_massnahme['id'],
		'schluessel'     => $p_massnahme['schluessel'],
		'bezeichnung'    => $p_massnahme['bezeichnung'],
		'typ'            => $p_massnahme['typ'],
		'art'            => $p_art,
		'detail'         => $p_detail,
	);
}
