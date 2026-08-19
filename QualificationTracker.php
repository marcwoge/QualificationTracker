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
		# Landing page of the plugin (the "gear" in the plugin manager).
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
			# --- Zugriff / Berechtigungsstufen (F7.1) ----------------------
			# Four graded roles mapped to global access levels:
			#   view   – Betrachter (read-only reports; optionally scoped to
			#            their own department via abteilung_betrachter)
			#   edit   – Sachbearbeiter (persons, events, completions, import)
			#   manage – SiFa/safety officer (catalogue, profiles, generator,
			#            automation) – the historical manage_threshold
			#   admin  – Administrator (configuration)
			'view_threshold'            => VIEWER,
			'edit_threshold'            => UPDATER,
			'manage_threshold'          => MANAGER,
			'admin_threshold'           => ADMINISTRATOR,

			# Optional map MantisBT user id => department name. A user that only
			# reaches the view level is restricted to this department in the
			# read reports; users with edit level or higher see everything.
			'abteilung_betrachter'      => array(),

			# --- Löschkonzept / Aufbewahrung (F7.3) ------------------------
			# Retention period (months) after a finished proof's anchor date,
			# after which it becomes a deletion candidate. The anchor is the
			# end of validity for expired proofs and the modification date for
			# cancelled ones. A global default plus an optional per-measure-type
			# override; 0 disables deletion for that type. These are starting
			# points – set them to your own legal retention requirements.
			'aufbewahrung_monate_default' => 36,
			'aufbewahrung_monate_typ'     => array(),

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

			# Per-department reference-month override for the "stichmonat" mode
			# (staffelung). Map department name => month (1-12); empty means the
			# global default applies. Maintained on the configuration page (F1.6),
			# consumed by the calculator via its abteilung_stichmonat parameter.
			'stichmonat_abteilung'      => array(),

			# --- Eskalation (F5.3), Vorgabestufen in Tagen -----------------
			# Positive = days before expiry, negative = days after.
			'eskalation_stufen_tage'    => array( 90, 30, 0, -30 ),

			# Extra notification recipients per escalation stage (F5.3): one
			# entry per stage above, each a list of MantisBT user ids added as
			# ticket monitors when that stage fires, so later stages widen the
			# circle. The ticket handler (the supervisor) is always notified by
			# the stage bugnote; this only adds people on top. Default: none.
			'eskalation_empfaenger'     => array( array(), array(), array(), array() ),

			# --- Ablaufreaktivierung (F5.2) --------------------------------
			# Status a deferred ("sleeping") renewal ticket is parked at in the
			# native fallback (no Reveille). Default 15 mirrors Reveille's own
			# deferred status; register it in status_enum_string to hide such
			# tickets from the active work list. When Reveille is installed the
			# hand-off uses Reveille's configured deferred status instead.
			'reactivation_held_status'  => 15,

			# --- Ruhensvermerk (F5.4) --------------------------------------
			# Status a dependent appointment (Beauftragung) ticket is set to
			# while it rests because a safety-relevant prerequisite has lapsed.
			# Default 20 (feedback) = needs attention; overridable.
			'ruhens_status'             => 20,

			# --- CLI-Runner (F5.5) -----------------------------------------
			# The MantisBT user the nightly cron runner acts as (author of the
			# escalation/suspension notes, ticket status changes). Override with
			# a dedicated service account. The --user CLI flag takes precedence.
			'cron_user'                 => 'administrator',

			# --- REST-Endpunkte (F6.3) -------------------------------------
			# Whether POST .../import may write (create/update persons and
			# proofs) via REST. Off by default; the read endpoints stay
			# available. Turn on for a NiFi service account after review.
			'rest_import_enabled'       => 0,

			# --- Ticketerzeugung (ab M2) -----------------------------------
			# Project the proof tickets are created in. 0 = not configured yet.
			'zielprojekt_id'            => 0,

			# Dedicated occupational-health project for VO measures (F7.2).
			# 0 = none configured -> VO tickets fall back to zielprojekt_id.
			# Set a separate project so only occupational-health staff can see
			# them (project-level access control).
			'vorsorge_projekt_id'       => 0,

			# Mapping of the plugin's domain proof states onto MantisBT status
			# values (decision G1). Defaults target the standard status enum so
			# the plugin works without editing config_inc.php; overridable so an
			# installation with custom statuses can point here instead.
			# NEW_=10, feedback=20, acknowledged=30, confirmed=40, assigned=50,
			# resolved=80, closed=90.
			'status_mapping'            => array(
				'offen'         => 10,
				'geplant'       => 30,
				'durchgefuehrt' => 40,
				'gueltig'       => 80,
				'abgelaufen'    => 20,
				'entfallen'     => 90,
			),
		);
	}

	/**
	 * Event hooks.
	 *
	 * @return array
	 */
	function hooks() {
		return array(
			'EVENT_MENU_MANAGE'           => 'menu_manage',
			'EVENT_MENU_MAIN'             => 'menu_main',
			'EVENT_LAYOUT_CONTENT_BEGIN'  => 'dashboard_widget',
			'EVENT_REST_API_ROUTES'       => 'rest_routes',
		);
	}

	/**
	 * Main-menu (sidebar) entry point for the read reports (F7.1).
	 *
	 * The management menu is manager-gated, so viewers and clerks would have no
	 * way to reach the matrix. This adds a "Qualifikationen" sidebar link for
	 * everyone who reaches the view threshold; the pages themselves enforce the
	 * per-role access.
	 *
	 * @return array
	 */
	function menu_main() {
		plugin_require_api( 'core/QT_Access.php' );
		if( !qt_access_has( 'view' ) ) {
			return array();
		}
		return array( array(
			'title' => plugin_lang_get( 'menu_matrix' ),
			'url'   => plugin_page( 'matrix' ),
			'icon'  => 'fa-shield',
		) );
	}

	/**
	 * Register the plugin's REST routes (F6.3) under
	 * /api/rest/plugins/QualificationTracker/. The core AuthMiddleware has
	 * already authenticated the caller; each handler additionally enforces the
	 * manage threshold, and the import handler the rest_import_enabled flag.
	 *
	 * @param string $p_event
	 * @param array  $p_args  Carries the Slim app under 'app'.
	 * @return void
	 */
	function rest_routes( $p_event, $p_args ) {
		$t_app = $p_args['app'];
		$t_plugin = $this->basename;
		plugin_require_api( 'core/QT_Rest.php' );

		$t_app->group( '/plugins/' . $t_plugin, function() use ( $t_app ) {
			$t_app->get( '/personen', 'qt_rest_personen' );
			$t_app->get( '/personen/', 'qt_rest_personen' );
			$t_app->get( '/nachweise', 'qt_rest_nachweise' );
			$t_app->get( '/nachweise/', 'qt_rest_nachweise' );
			$t_app->post( '/import', 'qt_rest_import' );
			$t_app->post( '/import/', 'qt_rest_import' );
		} );
	}

	/**
	 * Dashboard KPI widget (F4.6).
	 *
	 * Rendered at the top of the front / My-View page for users allowed to manage
	 * the plugin: the overall compliance rate and the outstanding-measure counts
	 * as of today, with links into the matrix and the audit report. Gated to the
	 * dashboard pages so it does not appear elsewhere, and it does no database
	 * work until after that check.
	 *
	 * @param string $p_event Event name.
	 * @return string HTML (EVENT_LAYOUT_CONTENT_BEGIN is an output event).
	 */
	function dashboard_widget( $p_event ) {
		$t_script = basename( isset( $_SERVER['SCRIPT_NAME'] ) ? $_SERVER['SCRIPT_NAME'] : '' );
		if( $t_script !== 'my_view_page.php' && $t_script !== 'main_page.php' ) {
			return '';
		}
		if( !access_has_global_level( plugin_config_get( 'manage_threshold' ) ) ) {
			return '';
		}

		plugin_require_api( 'core/QT_Catalog.php' );
		plugin_require_api( 'core/QT_Person.php' );
		plugin_require_api( 'core/QT_Prerequisite.php' );
		plugin_require_api( 'core/QT_CustomFields.php' );
		plugin_require_api( 'core/QT_DueDateCalculator.php' );
		plugin_require_api( 'core/QT_Generator.php' );
		plugin_require_api( 'core/QT_SollIst.php' );
		plugin_require_api( 'core/QT_Matrix.php' );

		$t_report = qt_matrix_compliance( date( 'Y-m-d' ), array() );
		$t = $t_report['total'];
		if( (int)$t['soll'] <= 0 ) {
			return '';
		}

		$t_rate = (float)$t['rate'];
		$t_rate_class = $t_rate >= 90 ? 'success' : ( $t_rate >= 70 ? 'warning' : 'danger' );

		$t_lang = function( $p_key ) {
			return plugin_lang_get( $p_key );
		};
		$t_kpi = function( $p_value, $p_label, $p_class ) {
			return '<div class="col-sm-3 col-xs-6 center">'
				. '<h2 class="bigger-200 ' . ( $p_class === 'grey' ? 'grey' : 'text-' . $p_class ) . '" style="margin:4px 0">' . (int)$p_value . '</h2>'
				. '<span class="grey">' . string_display_line( $p_label ) . '</span></div>';
		};

		$t_html = '<div class="col-md-12 col-xs-12"><div class="space-10"></div>'
			. '<div class="widget-box widget-color-blue2">'
			. '<div class="widget-header widget-header-small"><h4 class="widget-title lighter">'
			. '<i class="ace-icon fa fa-shield"></i> ' . string_display_line( $t_lang( 'widget_title' ) )
			. '</h4></div><div class="widget-body"><div class="widget-main">'
			. '<div class="row">'
			. '<div class="col-sm-3 col-xs-6 center">'
			. '<h2 class="bigger-200 text-' . $t_rate_class . '" style="margin:4px 0">' . number_format( $t_rate, 1 ) . '&nbsp;%</h2>'
			. '<span class="grey">' . string_display_line( $t_lang( 'audit_rate' ) ) . '</span></div>'
			. $t_kpi( (int)$t['abgelaufen'], $t_lang( 'matrix_state_abgelaufen' ), 'danger' )
			. $t_kpi( (int)$t['fehlt'], $t_lang( 'matrix_state_fehlt' ), 'grey' )
			. $t_kpi( (int)$t['offen'], $t_lang( 'matrix_state_offen' ), 'info' )
			. '</div>'
			. '<div class="clearfix" style="margin-top:8px">'
			. '<a class="btn btn-xs btn-primary btn-white btn-round" href="' . plugin_page( 'matrix' ) . '">'
			. '<i class="ace-icon fa fa-th"></i> ' . string_display_line( $t_lang( 'menu_matrix' ) ) . '</a> '
			. '<a class="btn btn-xs btn-white btn-round" href="' . plugin_page( 'audit' ) . '">'
			. '<i class="ace-icon fa fa-file-text-o"></i> ' . string_display_line( $t_lang( 'menu_audit' ) ) . '</a>'
			. '</div>'
			. '</div></div></div></div>';

		return $t_html;
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
			'<a href="' . plugin_page( 'moduswechsel' ) . '">'
				. plugin_lang_get( 'menu_moduswechsel' ) . '</a>',
			'<a href="' . plugin_page( 'person' ) . '">'
				. plugin_lang_get( 'menu_person' ) . '</a>',
			'<a href="' . plugin_page( 'import_personen' ) . '">'
				. plugin_lang_get( 'menu_import_personen' ) . '</a>',
			'<a href="' . plugin_page( 'import_nachweise' ) . '">'
				. plugin_lang_get( 'menu_import_nachweise' ) . '</a>',
			'<a href="' . plugin_page( 'profil' ) . '">'
				. plugin_lang_get( 'menu_profil' ) . '</a>',
			'<a href="' . plugin_page( 'zuordnung' ) . '">'
				. plugin_lang_get( 'menu_zuordnung' ) . '</a>',
			'<a href="' . plugin_page( 'matrix' ) . '">'
				. plugin_lang_get( 'menu_matrix' ) . '</a>',
			'<a href="' . plugin_page( 'audit' ) . '">'
				. plugin_lang_get( 'menu_audit' ) . '</a>',
			'<a href="' . plugin_page( 'sollist' ) . '">'
				. plugin_lang_get( 'menu_sollist' ) . '</a>',
			'<a href="' . plugin_page( 'dryrun' ) . '">'
				. plugin_lang_get( 'menu_dryrun' ) . '</a>',
			'<a href="' . plugin_page( 'nachweise' ) . '">'
				. plugin_lang_get( 'menu_nachweise' ) . '</a>',
			'<a href="' . plugin_page( 'automatik' ) . '">'
				. plugin_lang_get( 'menu_automatik' ) . '</a>',
			'<a href="' . plugin_page( 'loeschung' ) . '">'
				. plugin_lang_get( 'menu_loeschung' ) . '</a>',
			'<a href="' . plugin_page( 'auskunft' ) . '">'
				. plugin_lang_get( 'menu_auskunft' ) . '</a>',
			'<a href="' . plugin_page( 'historie' ) . '">'
				. plugin_lang_get( 'menu_historie' ) . '</a>',
			'<a href="' . plugin_page( 'verzeichnis' ) . '">'
				. plugin_lang_get( 'menu_verzeichnis' ) . '</a>',
			'<a href="' . plugin_page( 'veranstaltung' ) . '">'
				. plugin_lang_get( 'menu_event' ) . '</a>',
			'<a href="' . plugin_page( 'config' ) . '">'
				. plugin_lang_get( 'menu_config' ) . '</a>',
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

			# --- 13/14/15: qt_massnahme_vorbedingung (prerequisites, F1.3) --
			# Directed edge: measure `massnahme_id` requires `voraussetzung_id`.
			# The graph must stay acyclic; cycles are rejected on save.
			array( 'CreateTableSQL', array( plugin_table( 'massnahme_vorbedingung' ), "
				id              I UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				massnahme_id    I UNSIGNED NOTNULL,
				voraussetzung_id I UNSIGNED NOTNULL
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_vorbed_unique',
				plugin_table( 'massnahme_vorbedingung' ), 'massnahme_id,voraussetzung_id',
				array( 'UNIQUE' ) ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_vorbed_voraussetzung',
				plugin_table( 'massnahme_vorbedingung' ), 'voraussetzung_id' ) ),

			# --- 16/17/18/19: qt_nachweis (derived index, F2.3 / decision G5) --
			# One row per proof instance, pointing at the MantisBT ticket that is
			# the source of truth. Denormalises the audit-relevant fields so the
			# generator can be idempotent and the matrix (M4) can aggregate fast
			# without the slow custom-field join.
			array( 'CreateTableSQL', array( plugin_table( 'nachweis' ), "
				id            I     UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				person_id     I     UNSIGNED NOTNULL,
				massnahme_id  I     UNSIGNED NOTNULL,
				bug_id        I     UNSIGNED NOTNULL DEFAULT '0',
				soll_termin   D,
				gueltig_bis   D,
				status        C(16) NOTNULL DEFAULT \" 'offen' \",
				zyklus        C(16),
				date_created  I     UNSIGNED NOTNULL DEFAULT '0',
				date_modified I     UNSIGNED NOTNULL DEFAULT '0'
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_nachweis_pm',
				plugin_table( 'nachweis' ), 'person_id,massnahme_id' ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_nachweis_bug',
				plugin_table( 'nachweis' ), 'bug_id' ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_nachweis_status',
				plugin_table( 'nachweis' ), 'status' ) ),

			# --- 20/21/22: qt_teilnehmer (event participants, F3.2) --------
			# One row per person planned for a group event. bug_id links the
			# child proof ticket created in F3.3; status carries the F3.4 mass
			# completion outcome (eingeplant/teilgenommen/abwesend).
			array( 'CreateTableSQL', array( plugin_table( 'teilnehmer' ), "
				id               I     UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				veranstaltung_id I     UNSIGNED NOTNULL,
				person_id        I     UNSIGNED NOTNULL,
				bug_id           I     UNSIGNED NOTNULL DEFAULT '0',
				status           C(16) NOTNULL DEFAULT \" 'eingeplant' \",
				date_created     I     UNSIGNED NOTNULL DEFAULT '0'
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_teilnehmer_unique',
				plugin_table( 'teilnehmer' ), 'veranstaltung_id,person_id',
				array( 'UNIQUE' ) ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_teilnehmer_person',
				plugin_table( 'teilnehmer' ), 'person_id' ) ),

			# --- 23: qt_nachweis.eskalation_stufe (escalation state, F5.3) --
			# Highest escalation stage already notified for this proof (0 = none).
			# Lets the nightly escalation run fire each stage exactly once.
			array( 'AddColumnSQL', array( plugin_table( 'nachweis' ),
				"eskalation_stufe I2 NOTNULL DEFAULT '0'" ) ),

			# --- 24: qt_nachweis.ruht (suspension flag, F5.4) --------------
			# 1 while a dependent appointment rests because a safety-relevant
			# prerequisite has lapsed; makes the suspension idempotent and
			# reversible when the prerequisite becomes valid again.
			array( 'AddColumnSQL', array( plugin_table( 'nachweis' ),
				"ruht L NOTNULL DEFAULT '0'" ) ),

			# --- 25/26: qt_lauf (automation run log, F5.6) -----------------
			# One row per executed automation pass (CLI or UI), with its JSON
			# result summary, so every run is auditable in the interface.
			array( 'CreateTableSQL', array( plugin_table( 'lauf' ), "
				id           I     UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				lauf         C(16) NOTNULL,
				quelle       C(8)  NOTNULL DEFAULT \" 'ui' \",
				ergebnis     X,
				user_id      I     UNSIGNED NOTNULL DEFAULT '0',
				date_created I     UNSIGNED NOTNULL DEFAULT '0'
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_lauf_created',
				plugin_table( 'lauf' ), 'date_created' ) ),

			# --- 27/28: qt_loeschung (deletion log, F7.3) ------------------
			# Append-only protocol of executed proof deletions (Löschprotokoll).
			# Keeps the business identifiers (personnel number, measure key,
			# ticket id, validity end) so the erasure stays provable after the
			# ticket and the master data are gone – DSGVO Art. 17 accountability.
			array( 'CreateTableSQL', array( plugin_table( 'loeschung' ), "
				id                   I     UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				bug_id               I     UNSIGNED NOTNULL DEFAULT '0',
				person_id            I     UNSIGNED NOTNULL DEFAULT '0',
				massnahme_id         I     UNSIGNED NOTNULL DEFAULT '0',
				personalnummer       C(64),
				massnahme_schluessel C(64),
				gueltig_bis          D,
				grund                C(191),
				user_id              I     UNSIGNED NOTNULL DEFAULT '0',
				date_created         I     UNSIGNED NOTNULL DEFAULT '0'
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_loeschung_created',
				plugin_table( 'loeschung' ), 'date_created' ) ),

			# --- 29/30/31: qt_historie (master-data change log, F7.5) ------
			# Append-only history of create/update/delete changes to the
			# plugin's own master data (catalogue, profiles, assignments),
			# analogous to the MantisBT bug history which cannot see these
			# plugin tables. One row per changed field.
			array( 'CreateTableSQL', array( plugin_table( 'historie' ), "
				id           I     UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				entity_typ   C(16) NOTNULL,
				entity_id    I     UNSIGNED NOTNULL DEFAULT '0',
				aktion       C(8)  NOTNULL DEFAULT \" 'update' \",
				feld         C(64),
				alt_wert     X,
				neu_wert     X,
				user_id      I     UNSIGNED NOTNULL DEFAULT '0',
				date_created I     UNSIGNED NOTNULL DEFAULT '0'
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_historie_entity',
				plugin_table( 'historie' ), 'entity_typ,entity_id' ) ),
			array( 'CreateIndexSQL', array( 'idx_qt_historie_created',
				plugin_table( 'historie' ), 'date_created' ) ),
		);
	}

	/**
	 * On install, create the proof-ticket custom fields (F1.5).
	 *
	 * Fields are only created when missing; existing fields of the same name are
	 * reused. Linking them to a project happens on the configuration page once a
	 * target project is chosen. Runs after the schema so it never blocks the
	 * table creation.
	 *
	 * @return bool
	 */
	function install() {
		require_once( dirname( __FILE__ ) . '/core/QT_CustomFields.php' );
		qt_custom_fields_ensure();
		return true;
	}

	/**
	 * Remove the plugin's tables on uninstall (Issue #1: teardown).
	 *
	 * MantisBT does not drop plugin schema tables automatically. Dropping them
	 * here also serves data protection: uninstalling removes the personal data
	 * this plugin stored. The MantisBT ticket data (the actual proofs) is not
	 * touched – it lives in the core tables. The shared custom fields are left in
	 * place (they may hold data and be used elsewhere).
	 */
	function uninstall() {
		$t_tables = array(
			'historie', 'loeschung', 'lauf', 'teilnehmer', 'nachweis', 'massnahme_vorbedingung', 'veranstaltung',
			'zuordnung', 'profil_massnahme', 'profil', 'person', 'massnahme',
		);
		foreach( $t_tables as $t_name ) {
			# Table names come from plugin_table(), never from user input.
			db_query( 'DROP TABLE IF EXISTS ' . plugin_table( $t_name ) );
		}
	}
}
