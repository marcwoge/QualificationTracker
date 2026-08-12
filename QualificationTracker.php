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

if( !defined( 'MANTIS_VERSION' ) ) {
	# Load the plugin only through the MantisBT plugin subsystem, never directly.
	die( 'QualificationTracker is a MantisBT plugin and cannot be called directly.' );
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
		# Landing page of the plugin. Points at the measure catalogue until the
		# dedicated configuration page is built (F1.6).
		$this->page         = 'catalog';

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
			# --- Zugriff ---------------------------------------------------
			# Minimum global access level allowed to manage master data
			# (catalogue, profiles, persons). Refined into dedicated levels in
			# F7.1; MANAGER by default so the safety officer can maintain it.
			'manage_threshold'          => MANAGER,

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
			'<a href="' . plugin_page( 'catalog' ) . '">'
				. plugin_lang_get( 'menu_catalog' ) . '</a>',
		);
	}

	/**
	 * Database schema (F1.1).
	 *
	 * Six master-data tables in ADOdb datadict format, addressed through
	 * plugin_table(). MantisBT applies each numbered step in order and records
	 * the highest applied index per plugin, so this array is strictly
	 * APPEND-ONLY across releases – never reorder or delete an existing entry.
	 *
	 * Nachweise themselves are MantisBT tickets (one ticket = one proof
	 * instance), not a table here; their fields (durchgefuehrt_am, soll_termin,
	 * gueltig_bis, …) are custom fields created in F1.5.
	 *
	 * Data-model decisions baked in here:
	 *  - E1: qt_person.id (surrogate) is the leading key; personalnummer is a
	 *        nullable business attribute, unique only where present.
	 *  - E2: external staff live in qt_person via the `typ` discriminator plus
	 *        an optional `fremdfirma`, not in a separate table.
	 *  - E3: one-off (event-driven) instructions are modelled by
	 *        qt_massnahme.wiederkehrend = 0, not by a sixth measure type.
	 *  - E5: youth-protection short interval is driven by a single date
	 *        (verkuerztes_intervall_bis); the full date of birth is never stored.
	 *  - E6: a single global catalog – deliberately no standort_id column.
	 *
	 * Privacy: there is intentionally NO free-text findings column anywhere.
	 * Occupational-health (VO) proofs may only ever carry type, date and
	 * follow-up deadline; the data model cannot hold a diagnosis (see F7.2).
	 *
	 * @return array
	 */
	function schema() {
		return array(
			# --- 0/1: qt_massnahme (measure catalogue) ---------------------
			array( 'CreateTableSQL', array( plugin_table( 'massnahme' ), "
				id                   I       UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				schluessel           C(64)   NOTNULL,
				bezeichnung          C(191)  NOTNULL,
				typ                  C(2)    NOTNULL DEFAULT \" 'UW' \",
				intervall_monate     I2      UNSIGNED,
				faelligkeitsmodus    C(16)   NOTNULL DEFAULT \" 'kalenderjahr' \",
				stichmonat           I2      UNSIGNED,
				karenz_tage          I2      UNSIGNED NOTNULL DEFAULT '42',
				vorlaufzeit_tage     I2      UNSIGNED NOTNULL DEFAULT '0',
				wiederkehrend        L       NOTNULL DEFAULT '1',
				sicherheitsrelevant  L       NOTNULL DEFAULT '0',
				rechtsgrundlage      C(191),
				nachweisart          C(64),
				aktiv                L       NOTNULL DEFAULT '1',
				date_created         I       UNSIGNED NOTNULL DEFAULT '0',
				date_modified        I       UNSIGNED NOTNULL DEFAULT '0'
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_massnahme_schluessel',
				plugin_table( 'massnahme' ), 'schluessel', array( 'UNIQUE' ) ) ),

			# --- 2/3: qt_person (person register) --------------------------
			array( 'CreateTableSQL', array( plugin_table( 'person' ), "
				id                        I      UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				personalnummer            C(64),
				typ                       C(16)  NOTNULL DEFAULT \" 'intern' \",
				fremdfirma                C(128),
				nachname                  C(128) NOTNULL,
				vorname                   C(128) NOTNULL DEFAULT \" '' \",
				abteilung                 C(128) NOTNULL DEFAULT \" '' \",
				eintritt                  D,
				austritt                  D,
				vorgesetzter_user_id      I      UNSIGNED,
				verkuerztes_intervall_bis D,
				aktiv                     L      NOTNULL DEFAULT '1',
				date_created              I      UNSIGNED NOTNULL DEFAULT '0',
				date_modified             I      UNSIGNED NOTNULL DEFAULT '0'
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_person_pnr',
				plugin_table( 'person' ), 'personalnummer', array( 'UNIQUE' ) ) ),

			# --- 4/5: qt_profil (activity profile) -------------------------
			array( 'CreateTableSQL', array( plugin_table( 'profil' ), "
				id            I      UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				name          C(128) NOTNULL,
				beschreibung  X,
				aktiv         L      NOTNULL DEFAULT '1',
				date_created  I      UNSIGNED NOTNULL DEFAULT '0',
				date_modified I      UNSIGNED NOTNULL DEFAULT '0'
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_profil_name',
				plugin_table( 'profil' ), 'name', array( 'UNIQUE' ) ) ),

			# --- 6/7: qt_profil_massnahme (profile <-> measure, n:m) -------
			array( 'CreateTableSQL', array( plugin_table( 'profil_massnahme' ), "
				id           I UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				profil_id    I UNSIGNED NOTNULL,
				massnahme_id I UNSIGNED NOTNULL
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_profmass_unique',
				plugin_table( 'profil_massnahme' ), 'profil_id,massnahme_id',
				array( 'UNIQUE' ) ) ),

			# --- 8/9/10: qt_zuordnung (person <-> profile assignment) ------
			array( 'CreateTableSQL', array( plugin_table( 'zuordnung' ), "
				id            I UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				person_id     I UNSIGNED NOTNULL,
				profil_id     I UNSIGNED NOTNULL,
				gueltig_ab    D,
				gueltig_bis   D,
				date_created  I UNSIGNED NOTNULL DEFAULT '0',
				date_modified I UNSIGNED NOTNULL DEFAULT '0'
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_zuordnung_person',
				plugin_table( 'zuordnung' ), 'person_id' ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_zuordnung_profil',
				plugin_table( 'zuordnung' ), 'profil_id' ) ),

			# --- 11/12: qt_veranstaltung (group event) ---------------------
			array( 'CreateTableSQL', array( plugin_table( 'veranstaltung' ), "
				id             I      UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				massnahme_id   I      UNSIGNED NOTNULL,
				titel          C(191) NOTNULL DEFAULT \" '' \",
				termin         T,
				ort            C(191),
				unterweisender C(191),
				kapazitaet     I2     UNSIGNED,
				eltern_bug_id  I      UNSIGNED,
				status         C(16)  NOTNULL DEFAULT \" 'geplant' \",
				date_created   I      UNSIGNED NOTNULL DEFAULT '0',
				date_modified  I      UNSIGNED NOTNULL DEFAULT '0'
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_veranstaltung_massnahme',
				plugin_table( 'veranstaltung' ), 'massnahme_id' ) ),
		);
	}

	/**
	 * Remove the plugin's tables on uninstall (Issue #1: teardown).
	 *
	 * MantisBT does not drop plugin schema tables automatically. Dropping them
	 * here also serves data protection: uninstalling removes the personal data
	 * this plugin stored. The MantisBT ticket data (the actual proofs) is not
	 * touched – it lives in the core tables.
	 */
	function uninstall() {
		$t_tables = array(
			'veranstaltung', 'zuordnung', 'profil_massnahme',
			'profil', 'person', 'massnahme',
		);
		foreach( $t_tables as $t_name ) {
			# Table names come from plugin_table(), never from user input.
			db_query( 'DROP TABLE IF EXISTS ' . plugin_table( $t_name ) );
		}
	}
}
