<?php
/**
 * QualificationTracker – create an event from a suggestion (F3.7). POST-only.
 *
 * Takes one suggested session (measure, date, a list of persons), creates the
 * group event and books the persons as participants, then hands over to the
 * event's participant page. The safety officer refines details (location,
 * instructor) and generates the proof tickets there as usual.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'config_api.php' );
require_api( 'database_api.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'print_api.php' );

auth_reauthenticate();
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'manage' );

form_security_validate( 'plugin_QualificationTracker_terminvorschlag_create' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Event.php' );
plugin_require_api( 'core/QT_Participant.php' );

$f_massnahme = gpc_get_int( 'massnahme_id', 0 );
$f_termin    = gpc_get_string( 'termin', '' );
$f_persons   = gpc_get_int_array( 'person_ids', array() );

$t_measure = qt_massnahme_get( $f_massnahme );
if( $t_measure === false || !qt_event_valid_termin( $f_termin ) ) {
	error_parameters( $f_massnahme );
	trigger_error( ERROR_GENERIC, ERROR );
}

$t_titel = sprintf( plugin_lang_get( 'terminvorschlag_event_title' ),
	(string)$t_measure['schluessel'], substr( (string)$f_termin, 0, 10 ) );

$t_event_id = qt_event_create( array(
	'massnahme_id'   => $f_massnahme,
	'titel'          => mb_substr( $t_titel, 0, 191 ),
	'termin'         => $f_termin,
	'ort'            => '',
	'unterweisender' => '',
	'kapazitaet'     => (string)count( $f_persons ),
	'status'         => 'geplant',
) );

# Book the suggested persons (idempotent; unknown ids are skipped).
$t_added = 0;
foreach( $f_persons as $t_pid ) {
	if( (int)$t_pid > 0 && qt_person_get( (int)$t_pid ) !== false ) {
		if( qt_teilnehmer_add( $t_event_id, (int)$t_pid ) ) {
			$t_added++;
		}
	}
}

form_security_purge( 'plugin_QualificationTracker_terminvorschlag_create' );
print_successful_redirect( plugin_page( 'veranstaltung_teilnehmer', true )
	. '&id=' . (int)$t_event_id . '&msg=created&added=' . (int)$t_added );
