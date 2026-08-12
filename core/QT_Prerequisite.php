<?php
/**
 * QualificationTracker – measure prerequisites (F1.3).
 *
 * A measure may require other measures (Qualifikation → Beauftragung →
 * Unterweisung). The "requires" graph must stay acyclic. Cycle detection is a
 * pure function (qt_prereq_detect_cycle) and is unit-tested; the persistence
 * helpers use the Mantis database API.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Build an adjacency map (node => list of required nodes) from a flat edge list.
 * Pure function.
 *
 * @param array $p_edges List of edges, each with 'massnahme_id' and 'voraussetzung_id'.
 * @return array
 */
function qt_prereq_build_adjacency( array $p_edges ) {
	$t_adjacency = array();
	foreach( $p_edges as $t_edge ) {
		$t_from = (int)$t_edge['massnahme_id'];
		$t_to   = (int)$t_edge['voraussetzung_id'];
		if( !isset( $t_adjacency[$t_from] ) ) {
			$t_adjacency[$t_from] = array();
		}
		$t_adjacency[$t_from][] = $t_to;
	}
	return $t_adjacency;
}

/**
 * Does the "requires" graph contain a cycle? Pure function (DFS with a
 * three-colour node state). A self-edge counts as a cycle.
 *
 * @param array $p_adjacency Node => list of required nodes.
 * @return bool
 */
function qt_prereq_detect_cycle( array $p_adjacency ) {
	# Collect every node that appears as a source or a target.
	$t_nodes = array_keys( $p_adjacency );
	foreach( $p_adjacency as $t_targets ) {
		foreach( $t_targets as $t_target ) {
			$t_nodes[] = (int)$t_target;
		}
	}
	$t_nodes = array_unique( $t_nodes );

	# 0 = unvisited, 1 = on the current path, 2 = fully explored.
	$t_state = array();
	foreach( $t_nodes as $t_node ) {
		if( qt_prereq_visit( (int)$t_node, $p_adjacency, $t_state ) ) {
			return true;
		}
	}
	return false;
}

/**
 * DFS helper for qt_prereq_detect_cycle(). Pure.
 *
 * @param int   $p_node
 * @param array $p_adjacency
 * @param array $p_state By reference.
 * @return bool True when a back edge (cycle) is found.
 */
function qt_prereq_visit( $p_node, array $p_adjacency, array &$p_state ) {
	if( isset( $p_state[$p_node] ) ) {
		if( $p_state[$p_node] === 1 ) {
			return true;   # back edge -> cycle
		}
		if( $p_state[$p_node] === 2 ) {
			return false;  # already cleared
		}
	}

	$p_state[$p_node] = 1;
	$t_neighbors = isset( $p_adjacency[$p_node] ) ? $p_adjacency[$p_node] : array();
	foreach( $t_neighbors as $t_neighbor ) {
		if( qt_prereq_visit( (int)$t_neighbor, $p_adjacency, $p_state ) ) {
			return true;
		}
	}
	$p_state[$p_node] = 2;
	return false;
}

/**
 * Would assigning $p_new_prereqs as the prerequisites of $p_node create a cycle
 * (given all existing edges)? Pure function.
 *
 * @param array $p_existing_edges All current edges (any edges of $p_node are
 *                                replaced by the new set).
 * @param int   $p_node
 * @param array $p_new_prereqs List of prerequisite measure ids.
 * @return bool
 */
function qt_prereq_creates_cycle( array $p_existing_edges, $p_node, array $p_new_prereqs ) {
	$t_node = (int)$p_node;

	$t_edges = array();
	foreach( $p_existing_edges as $t_edge ) {
		if( (int)$t_edge['massnahme_id'] !== $t_node ) {
			$t_edges[] = $t_edge;
		}
	}
	foreach( $p_new_prereqs as $t_prereq ) {
		$t_edges[] = array( 'massnahme_id' => $t_node, 'voraussetzung_id' => (int)$t_prereq );
	}

	return qt_prereq_detect_cycle( qt_prereq_build_adjacency( $t_edges ) );
}

/* -------------------------------------------------------------------------- *
 *  Persistence
 * -------------------------------------------------------------------------- */

/**
 * All prerequisite edges in the catalogue.
 *
 * @return array List of rows with 'massnahme_id' and 'voraussetzung_id'.
 */
function qt_vorbedingung_load_all() {
	$t_result = db_query( 'SELECT massnahme_id, voraussetzung_id FROM '
		. plugin_table( 'massnahme_vorbedingung' ) );
	$t_edges = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_edges[] = $t_row;
	}
	return $t_edges;
}

/**
 * Prerequisite ids of one measure.
 *
 * @param int $p_massnahme_id
 * @return array List of ints.
 */
function qt_vorbedingung_get_for( $p_massnahme_id ) {
	$t_result = db_query( 'SELECT voraussetzung_id FROM ' . plugin_table( 'massnahme_vorbedingung' )
		. ' WHERE massnahme_id = ' . db_param(), array( (int)$p_massnahme_id ) );
	$t_ids = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_ids[] = (int)$t_row['voraussetzung_id'];
	}
	return $t_ids;
}

/**
 * Replace the prerequisite set of a measure. Self-references and non-positive
 * ids are dropped.
 *
 * @param int   $p_massnahme_id
 * @param array $p_prereqs
 * @return void
 */
function qt_vorbedingung_set( $p_massnahme_id, array $p_prereqs ) {
	$t_id = (int)$p_massnahme_id;
	$t_table = plugin_table( 'massnahme_vorbedingung' );

	db_query( 'DELETE FROM ' . $t_table . ' WHERE massnahme_id = ' . db_param(), array( $t_id ) );

	$t_seen = array();
	foreach( $p_prereqs as $t_prereq ) {
		$t_prereq = (int)$t_prereq;
		if( $t_prereq <= 0 || $t_prereq === $t_id || isset( $t_seen[$t_prereq] ) ) {
			continue;
		}
		$t_seen[$t_prereq] = true;
		db_query( 'INSERT INTO ' . $t_table . ' ( massnahme_id, voraussetzung_id ) VALUES ( '
			. db_param() . ', ' . db_param() . ' )', array( $t_id, $t_prereq ) );
	}
}

/**
 * Remove every edge that touches a measure, in either direction. Called before
 * deleting a measure so no dangling prerequisites remain.
 *
 * @param int $p_massnahme_id
 * @return void
 */
function qt_vorbedingung_purge( $p_massnahme_id ) {
	$t_id = (int)$p_massnahme_id;
	$t_table = plugin_table( 'massnahme_vorbedingung' );
	db_query( 'DELETE FROM ' . $t_table . ' WHERE massnahme_id = ' . db_param()
		. ' OR voraussetzung_id = ' . db_param(), array( $t_id, $t_id ) );
}
