<?php
/**
 * QualificationTracker – automation run log (F5.6).
 *
 * Records one row per executed automation pass (the CLI runner F5.5 and the
 * manual Automatik-page triggers), so every run is auditable in the interface.
 *
 * qt_lauf_format_result() is pure and unit-tested; recording and loading read
 * and write the database.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Format a pass result array as a short "key: value" line. Pure. Arrays are
 * rendered as their element count.
 *
 * @param array $p_ergebnis
 * @return string
 */
function qt_lauf_format_result( array $p_ergebnis ) {
	$t_parts = array();
	foreach( $p_ergebnis as $t_key => $t_value ) {
		if( is_array( $t_value ) ) {
			$t_value = count( $t_value );
		} else if( is_bool( $t_value ) ) {
			$t_value = $t_value ? '1' : '0';
		}
		$t_parts[] = $t_key . ': ' . $t_value;
	}
	return implode( ', ', $t_parts );
}

/**
 * Record one automation pass in the run log.
 *
 * @param string $p_lauf     Pass name (expiry|ruhen|reactivation|escalation).
 * @param array  $p_ergebnis Result summary (stored as JSON).
 * @param string $p_quelle   Source: 'cli' or 'ui'.
 * @return void
 */
function qt_lauf_record( $p_lauf, array $p_ergebnis, $p_quelle ) {
	db_query( 'INSERT INTO ' . plugin_table( 'lauf' )
		. ' ( lauf, quelle, ergebnis, user_id, date_created )'
		. ' VALUES ( ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ' )',
		array(
			substr( (string)$p_lauf, 0, 16 ),
			substr( (string)$p_quelle, 0, 8 ),
			json_encode( $p_ergebnis ),
			(int)auth_get_current_user_id(),
			time(),
		) );
}

/**
 * Load the most recent run-log rows, newest first.
 *
 * @param int $p_limit
 * @return array
 */
function qt_lauf_load( $p_limit = 50 ) {
	$t_result = db_query( 'SELECT * FROM ' . plugin_table( 'lauf' )
		. ' ORDER BY date_created DESC, id DESC', array(), (int)$p_limit );
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}
