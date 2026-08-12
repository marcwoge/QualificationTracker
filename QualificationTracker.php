<?php
/**
 * QualificationTracker – MantisBT-Plugin für Unterweisungs- und
 * Qualifikationsmanagement nach ArbSchG § 12 und DGUV Vorschrift 1 § 4.
 *
 * Ein Ticket = eine Nachweis-Instanz (Person × Maßnahme × Gültigkeitszyklus).
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 * @link      https://github.com/marcwoge/QualificationTracker
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction. The Software is provided "AS IS", without
 * warranty of any kind. See the LICENSE file for the full MIT license text.
 */

if( !defined( 'MANTIS_DIR' ) ) {
	# Load the plugin only through the MantisBT plugin subsystem.
	die( 'Access denied.' );
}

/**
 * QualificationTracker plugin main class.
 *
 * Registers the plugin, its configuration defaults, the management-menu hook
 * and – from milestone M1 onwards – the database schema. The schema itself is
 * added in F1.1 once the open data-model decisions (E1/E2) are settled, so that
 * the person table is created with its final column set instead of being
 * migrated immediately afterwards.
 */
class QualificationTrackerPlugin extends MantisPlugin {

	/**
	 * Plugin manifest.
	 *
	 * Literal strings are used here (not plugin_lang_get()) because the plugin
	 * language files are not yet loaded during registration – the same approach
	 * the sibling plugin Reveille uses.
	 */
	function register() {
		$this->name        = 'QualificationTracker';
		$this->description  = 'Manage legally required safety instructions, qualifications and appointments as MantisBT tickets (ArbSchG, DGUV).';
		$this->page         = 'config';

		$this->version  = '0.1.0';
		$this->requires = array(
			'MantisCore' => '2.25.0',
		);

		$this->author  = 'Marc-Philipp Woge';
		$this->contact = 'marc.woge@googlemail.com';
		$this->url     = 'https://github.com/marcwoge/QualificationTracker';
	}

	/**
	 * Configuration defaults.
	 *
	 * Only settings that are independent of the open data-model decisions are
	 * seeded here. Escalation recipients, status mapping and the target project
	 * are added on the configuration page in F1.6.
	 *
	 * @return array
	 */
	function config() {
		return array(
			# --- Fälligkeitsberechnung (F1.8 / F1.9) -----------------------
			# Global default due-date mode; overridable per measure.
			# One of: rollierend | kalenderjahr | stichmonat | extern
			'faelligkeitsmodus_default' => 'kalenderjahr',
			# Default reference month for the "stichmonat" mode (1-12);
			# overridable per department for load balancing.
			'stichmonat_default'        => 11,
			# Grace window in days before the target date within which a
			# rolling cycle keeps its anchor (F1.9, prevents forward drift).
			'karenz_tage_default'       => 42,
			# First-instruction deadline for new entrants, in days after the
			# entry date (used to seed the initial soll_termin).
			'ersteinweisung_frist_tage' => 14,

			# --- Eskalation (F5.3), Vorgabestufen in Tagen -----------------
			# Positive = days before expiry, negative = days after.
			'eskalation_stufen_tage'    => array( 90, 30, 0, -30 ),
		);
	}

	/**
	 * Event hooks.
	 *
	 * @return array
	 */
	function hooks() {
		return array(
			'EVENT_MENU_MANAGE' => 'menu_manage',
		);
	}

	/**
	 * Entries for the "Manage" menu.
	 *
	 * @return array Array of anchor HTML strings.
	 */
	function menu_manage() {
		return array(
			'<a href="' . plugin_page( 'config' ) . '">'
				. plugin_lang_get( 'menu_config' ) . '</a>',
		);
	}

	/**
	 * Database schema.
	 *
	 * Intentionally empty at this stage. The tables qt_massnahme, qt_person,
	 * qt_profil, qt_profil_massnahme, qt_zuordnung and qt_veranstaltung are
	 * added in F1.1 (milestone M1) via the ADOdb datadict format and
	 * plugin_table(), once decisions E1 (leading person key) and E2 (external
	 * personnel) are confirmed.
	 *
	 * @return array
	 */
	function schema() {
		return array();
	}
}
