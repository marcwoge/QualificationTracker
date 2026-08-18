<?php
/**
 * QualificationTracker – REST endpoints (F6.3).
 *
 * Read endpoints for the person register and the proof index, plus a guarded
 * write endpoint for bulk import (for a NiFi service account). Registered on the
 * MantisBT REST app via EVENT_REST_API_ROUTES; the core AuthMiddleware has
 * already authenticated the caller. Every handler additionally enforces the
 * plugin's manage threshold, and the import handler the rest_import_enabled flag.
 *
 * Routes (base /api/rest):
 *   GET  /plugins/QualificationTracker/personen   [?abteilung=&limit=&offset=]
 *   GET  /plugins/QualificationTracker/nachweise   [?person_id=&massnahme_id=&status=&limit=&offset=]
 *   POST /plugins/QualificationTracker/import       { type, dry_run, rows[] }
 *
 * qt_rest_person_json() and qt_rest_nachweis_json() are pure and unit-tested;
 * the handlers read/write the database.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * The public JSON shape of a person row. Pure.
 *
 * @param array $p_row
 * @return array
 */
function qt_rest_person_json( array $p_row ) {
	return array(
		'id'                   => (int)$p_row['id'],
		'personalnummer'       => (string)$p_row['personalnummer'],
		'typ'                  => (string)$p_row['typ'],
		'fremdfirma'           => (string)$p_row['fremdfirma'],
		'nachname'             => (string)$p_row['nachname'],
		'vorname'              => (string)$p_row['vorname'],
		'abteilung'            => (string)$p_row['abteilung'],
		'eintritt'             => $p_row['eintritt'],
		'austritt'             => $p_row['austritt'],
		'vorgesetzter_user_id' => (int)$p_row['vorgesetzter_user_id'],
		'aktiv'                => (bool)$p_row['aktiv'],
	);
}

/**
 * The public JSON shape of a qt_nachweis row. Pure.
 *
 * @param array $p_row
 * @return array
 */
function qt_rest_nachweis_json( array $p_row ) {
	return array(
		'id'           => (int)$p_row['id'],
		'person_id'    => (int)$p_row['person_id'],
		'massnahme_id' => (int)$p_row['massnahme_id'],
		'bug_id'       => (int)$p_row['bug_id'],
		'soll_termin'  => $p_row['soll_termin'],
		'gueltig_bis'  => $p_row['gueltig_bis'],
		'status'       => (string)$p_row['status'],
		'zyklus'       => (string)$p_row['zyklus'],
	);
}

/**
 * Establish the plugin context and load the core APIs the handlers need.
 *
 * @return void
 */
function qt_rest_bootstrap() {
	plugin_push_current( 'QualificationTracker' );
	plugin_require_api( 'core/QT_Catalog.php' );
	plugin_require_api( 'core/QT_Person.php' );
	plugin_require_api( 'core/QT_Prerequisite.php' );
	plugin_require_api( 'core/QT_CustomFields.php' );
	plugin_require_api( 'core/QT_DueDateCalculator.php' );
	plugin_require_api( 'core/QT_Generator.php' );
	plugin_require_api( 'core/QT_ImportPersonen.php' );
	plugin_require_api( 'core/QT_ImportNachweise.php' );
}

/**
 * True when the current user meets the plugin's manage threshold.
 *
 * @return bool
 */
function qt_rest_may_manage() {
	return access_has_global_level( (int)plugin_config_get( 'manage_threshold' ) );
}

/**
 * GET .../personen – the person register as JSON.
 *
 * @param mixed $p_request
 * @param mixed $p_response
 * @param array $p_args
 * @return mixed
 */
function qt_rest_personen( $p_request, $p_response, array $p_args ) {
	qt_rest_bootstrap();
	if( !qt_rest_may_manage() ) {
		return $p_response->withStatus( HTTP_STATUS_FORBIDDEN );
	}

	$t_abteilung = (string)$p_request->getParam( 'abteilung', '' );
	$t_limit  = (int)$p_request->getParam( 'limit', 1000 );
	$t_offset = (int)$p_request->getParam( 'offset', 0 );

	$t_all = qt_person_load_all( $t_abteilung );
	$t_slice = array_slice( $t_all, max( 0, $t_offset ), $t_limit > 0 ? $t_limit : null );

	$t_out = array();
	foreach( $t_slice as $t_p ) {
		$t_out[] = qt_rest_person_json( $t_p );
	}
	return $p_response->withJson( array( 'total' => count( $t_all ), 'personen' => $t_out ) );
}

/**
 * GET .../nachweise – the derived proof index as JSON, with optional filters.
 *
 * @param mixed $p_request
 * @param mixed $p_response
 * @param array $p_args
 * @return mixed
 */
function qt_rest_nachweise( $p_request, $p_response, array $p_args ) {
	qt_rest_bootstrap();
	if( !qt_rest_may_manage() ) {
		return $p_response->withStatus( HTTP_STATUS_FORBIDDEN );
	}

	$t_where = array( '1 = 1' );
	$t_params = array();
	$t_person = (int)$p_request->getParam( 'person_id', 0 );
	if( $t_person > 0 ) {
		$t_where[] = 'person_id = ' . db_param();
		$t_params[] = $t_person;
	}
	$t_massnahme = (int)$p_request->getParam( 'massnahme_id', 0 );
	if( $t_massnahme > 0 ) {
		$t_where[] = 'massnahme_id = ' . db_param();
		$t_params[] = $t_massnahme;
	}
	$t_status = (string)$p_request->getParam( 'status', '' );
	if( $t_status !== '' ) {
		$t_where[] = 'status = ' . db_param();
		$t_params[] = $t_status;
	}
	$t_limit  = (int)$p_request->getParam( 'limit', 1000 );
	$t_offset = (int)$p_request->getParam( 'offset', 0 );

	$t_query = 'SELECT * FROM ' . plugin_table( 'nachweis' )
		. ' WHERE ' . implode( ' AND ', $t_where ) . ' ORDER BY id';
	$t_result = db_query( $t_query, $t_params, $t_limit > 0 ? $t_limit : -1, max( 0, $t_offset ) );

	$t_out = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_out[] = qt_rest_nachweis_json( $t_row );
	}
	return $p_response->withJson( array( 'nachweise' => $t_out ) );
}

/**
 * POST .../import – bulk import of persons or historical proofs (guarded).
 *
 * Body: { "type": "personen"|"nachweise", "dry_run": bool, "rows": [ {...} ] }.
 *
 * @param mixed $p_request
 * @param mixed $p_response
 * @param array $p_args
 * @return mixed
 */
function qt_rest_import( $p_request, $p_response, array $p_args ) {
	qt_rest_bootstrap();
	if( !qt_rest_may_manage() ) {
		return $p_response->withStatus( HTTP_STATUS_FORBIDDEN );
	}
	if( !(bool)plugin_config_get( 'rest_import_enabled' ) ) {
		return $p_response->withJson( array( 'error' => 'rest import disabled' ), HTTP_STATUS_FORBIDDEN );
	}

	$t_body = $p_request->getParsedBody();
	if( !is_array( $t_body ) ) {
		$t_body = json_decode( (string)$p_request->getBody(), true );
	}
	if( !is_array( $t_body ) ) {
		return $p_response->withJson( array( 'error' => 'invalid body' ), HTTP_STATUS_BAD_REQUEST );
	}

	$t_type    = isset( $t_body['type'] ) ? (string)$t_body['type'] : '';
	$t_dry_run = !empty( $t_body['dry_run'] );
	$t_rows    = ( isset( $t_body['rows'] ) && is_array( $t_body['rows'] ) ) ? $t_body['rows'] : array();

	if( $t_type === 'personen' ) {
		$t_mapped = array();
		foreach( $t_rows as $t_row ) {
			$t_mapped[] = qt_import_personen_map_row( is_array( $t_row ) ? $t_row : array() );
		}
		$t_summary = qt_import_personen_run( $t_mapped, $t_dry_run );
	} else if( $t_type === 'nachweise' ) {
		$t_mapped = array();
		foreach( $t_rows as $t_row ) {
			$t_mapped[] = qt_import_nachweise_map_row( is_array( $t_row ) ? $t_row : array() );
		}
		$t_summary = qt_import_nachweise_run( $t_mapped, $t_dry_run, date( 'Y-m-d' ) );
	} else {
		return $p_response->withJson( array( 'error' => 'unknown type' ), HTTP_STATUS_BAD_REQUEST );
	}

	return $p_response->withJson( array( 'type' => $t_type, 'dry_run' => $t_dry_run, 'result' => $t_summary ) );
}
