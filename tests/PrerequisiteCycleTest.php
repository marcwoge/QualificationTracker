<?php
/**
 * Unit tests for the pure prerequisite cycle detection (F1.3).
 *
 * @package QualificationTracker
 * @license MIT
 */

use PHPUnit\Framework\TestCase;

final class PrerequisiteCycleTest extends TestCase {

	/** Build one edge (massnahme requires voraussetzung). */
	private function edge( $p_from, $p_to ) {
		return array( 'massnahme_id' => $p_from, 'voraussetzung_id' => $p_to );
	}

	public function testEmptyGraphHasNoCycle() {
		self::assertFalse( qt_prereq_detect_cycle( array() ) );
	}

	public function testChainHasNoCycle() {
		# 1 -> 2 -> 3
		$t_adj = qt_prereq_build_adjacency( array( $this->edge( 1, 2 ), $this->edge( 2, 3 ) ) );
		self::assertFalse( qt_prereq_detect_cycle( $t_adj ) );
	}

	public function testDirectCycleIsDetected() {
		# 1 -> 2 -> 1
		$t_adj = qt_prereq_build_adjacency( array( $this->edge( 1, 2 ), $this->edge( 2, 1 ) ) );
		self::assertTrue( qt_prereq_detect_cycle( $t_adj ) );
	}

	public function testLongerCycleIsDetected() {
		# 1 -> 2 -> 3 -> 1
		$t_adj = qt_prereq_build_adjacency( array(
			$this->edge( 1, 2 ), $this->edge( 2, 3 ), $this->edge( 3, 1 ) ) );
		self::assertTrue( qt_prereq_detect_cycle( $t_adj ) );
	}

	public function testSelfLoopIsCycle() {
		$t_adj = qt_prereq_build_adjacency( array( $this->edge( 5, 5 ) ) );
		self::assertTrue( qt_prereq_detect_cycle( $t_adj ) );
	}

	public function testDiamondHasNoCycle() {
		# 1 -> 2, 1 -> 3, 2 -> 4, 3 -> 4  (a DAG)
		$t_adj = qt_prereq_build_adjacency( array(
			$this->edge( 1, 2 ), $this->edge( 1, 3 ),
			$this->edge( 2, 4 ), $this->edge( 3, 4 ) ) );
		self::assertFalse( qt_prereq_detect_cycle( $t_adj ) );
	}

	public function testBuildAdjacencyGroupsTargets() {
		$t_adj = qt_prereq_build_adjacency( array( $this->edge( 1, 2 ), $this->edge( 1, 3 ) ) );
		self::assertSame( array( 2, 3 ), $t_adj[1] );
	}

	public function testCreatesCycleTrueWhenClosingLoop() {
		# Existing: 2 requires 1. Now make 1 require 2 -> 1 -> 2 -> 1.
		$t_existing = array( $this->edge( 2, 1 ) );
		self::assertTrue( qt_prereq_creates_cycle( $t_existing, 1, array( 2 ) ) );
	}

	public function testCreatesCycleFalseForDag() {
		# Existing: 2 requires 1. Now make 1 require 3 -> still acyclic.
		$t_existing = array( $this->edge( 2, 1 ) );
		self::assertFalse( qt_prereq_creates_cycle( $t_existing, 1, array( 3 ) ) );
	}

	public function testCreatesCycleReplacesNodesOwnEdges() {
		# Existing: 1 -> 2 -> 3. Reassign node 2 to require nothing: the old
		# 2 -> 3 edge must be dropped, so 1 -> 2 alone stays acyclic.
		$t_existing = array( $this->edge( 1, 2 ), $this->edge( 2, 3 ) );
		self::assertFalse( qt_prereq_creates_cycle( $t_existing, 2, array() ) );
	}

	public function testCreatesCycleDetectsSelfReference() {
		self::assertTrue( qt_prereq_creates_cycle( array(), 7, array( 7 ) ) );
	}
}
