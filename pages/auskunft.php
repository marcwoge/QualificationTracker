<?php
/**
 * QualificationTracker – data-subject disclosure page (Auskunft, F7.4).
 *
 * Select a person to compile everything the plugin stores about them for a
 * DSGVO Art. 15 subject-access request. The default view previews the report in
 * the normal layout; format=print emits a standalone, print-optimised HTML
 * document (no MantisBT chrome) that the browser saves as a PDF – no PDF library
 * and thus no runtime dependency required.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'bug_api.php' );
require_api( 'config_api.php' );
require_api( 'database_api.php' );
require_api( 'gpc_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );
require_api( 'user_api.php' );

auth_reauthenticate();
# A subject-access disclosure compiles all data about a person; administrator task.
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'admin' );

plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Disclosure.php' );

$f_person_id = gpc_get_int( 'person_id', 0 );
$f_format    = gpc_get_string( 'format', '' );

$t_today   = date( 'Y-m-d' );
$t_persons = qt_person_load_all();
$t_data    = $f_person_id > 0 ? qt_auskunft_gather( $f_person_id ) : false;

/**
 * Render one label/value definition table for the master record.
 *
 * @param array $p_fields From qt_auskunft_person_fields().
 * @return void
 */
function qt_auskunft_print_person( array $p_fields ) {
	echo '<table class="qt-kv">';
	foreach( $p_fields as $t_f ) {
		$t_value = ( isset( $t_f['translate'] ) && $t_f['translate'] )
			? plugin_lang_get( $t_f['value'] )
			: (string)$t_f['value'];
		echo '<tr><th>' . string_display_line( plugin_lang_get( $t_f['key'] ) ) . '</th>'
			. '<td>' . string_display_line( $t_value ) . '</td></tr>';
	}
	echo '</table>';
}

/**
 * Render the report body (all sections). Shared by the preview and the
 * standalone print document so both stay identical.
 *
 * @param array  $p_data  From qt_auskunft_gather().
 * @param string $p_today ISO date.
 * @return void
 */
function qt_auskunft_render_body( array $p_data, $p_today ) {
	$t_person = $p_data['person'];
	$t_dash   = '&ndash;';

	echo '<h3>' . string_display_line( plugin_lang_get( 'auskunft_section_stammdaten' ) ) . '</h3>';
	qt_auskunft_print_person( qt_auskunft_person_fields( $t_person ) );

	# --- Assignments --------------------------------------------------------
	echo '<h3>' . string_display_line( plugin_lang_get( 'auskunft_section_zuordnungen' ) ) . '</h3>';
	if( empty( $p_data['zuordnungen'] ) ) {
		echo '<p class="qt-empty">' . string_display_line( plugin_lang_get( 'auskunft_none' ) ) . '</p>';
	} else {
		echo '<table class="qt-list"><thead><tr>'
			. '<th>' . string_display_line( plugin_lang_get( 'label_profil' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'label_gueltig_ab' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'label_gueltig_bis' ) ) . '</th>'
			. '</tr></thead><tbody>';
		foreach( $p_data['zuordnungen'] as $t_z ) {
			echo '<tr><td>' . string_display_line( (string)$t_z['profil_name'] ) . '</td>'
				. '<td>' . ( $t_z['gueltig_ab'] ? string_display_line( (string)$t_z['gueltig_ab'] ) : $t_dash ) . '</td>'
				. '<td>' . ( $t_z['gueltig_bis'] ? string_display_line( (string)$t_z['gueltig_bis'] ) : $t_dash ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	# --- Proofs -------------------------------------------------------------
	echo '<h3>' . string_display_line( plugin_lang_get( 'auskunft_section_nachweise' ) ) . '</h3>';
	if( empty( $p_data['nachweise'] ) ) {
		echo '<p class="qt-empty">' . string_display_line( plugin_lang_get( 'auskunft_none' ) ) . '</p>';
	} else {
		echo '<table class="qt-list"><thead><tr>'
			. '<th>' . string_display_line( plugin_lang_get( 'label_event_massnahme' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'col_typ' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'event_col_status' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'label_soll_termin' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'label_gueltig_bis' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'loeschung_col_ticket' ) ) . '</th>'
			. '</tr></thead><tbody>';
		foreach( $p_data['nachweise'] as $t_n ) {
			$t_state_key = 'state_' . $t_n['status'];
			$t_state = plugin_lang_get_defaulted( $t_state_key, (string)$t_n['status'] );
			echo '<tr><td>' . string_display_line( (string)$t_n['schluessel'] . ' – ' . (string)$t_n['bezeichnung'] ) . '</td>'
				. '<td>' . string_display_line( (string)$t_n['typ'] ) . '</td>'
				. '<td>' . string_display_line( $t_state ) . '</td>'
				. '<td>' . ( $t_n['soll_termin'] ? string_display_line( (string)$t_n['soll_termin'] ) : $t_dash ) . '</td>'
				. '<td>' . ( $t_n['gueltig_bis'] ? string_display_line( (string)$t_n['gueltig_bis'] ) : $t_dash ) . '</td>'
				. '<td>' . ( (int)$t_n['bug_id'] > 0 ? string_display_line( bug_format_id( (int)$t_n['bug_id'] ) ) : $t_dash ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	# --- Event participations ----------------------------------------------
	echo '<h3>' . string_display_line( plugin_lang_get( 'auskunft_section_teilnahmen' ) ) . '</h3>';
	if( empty( $p_data['teilnahmen'] ) ) {
		echo '<p class="qt-empty">' . string_display_line( plugin_lang_get( 'auskunft_none' ) ) . '</p>';
	} else {
		echo '<table class="qt-list"><thead><tr>'
			. '<th>' . string_display_line( plugin_lang_get( 'event_col_titel' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'label_event_massnahme' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'event_col_termin' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'event_col_status' ) ) . '</th>'
			. '</tr></thead><tbody>';
		foreach( $p_data['teilnahmen'] as $t_t ) {
			echo '<tr><td>' . string_display_line( (string)$t_t['titel'] ) . '</td>'
				. '<td>' . string_display_line( (string)$t_t['schluessel'] . ' – ' . (string)$t_t['bezeichnung'] ) . '</td>'
				. '<td>' . ( $t_t['termin'] ? string_display_line( (string)$t_t['termin'] ) : $t_dash ) . '</td>'
				. '<td>' . string_display_line( (string)$t_t['status'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	# --- Deletion-log entries ----------------------------------------------
	echo '<h3>' . string_display_line( plugin_lang_get( 'auskunft_section_loeschungen' ) ) . '</h3>';
	if( empty( $p_data['loeschungen'] ) ) {
		echo '<p class="qt-empty">' . string_display_line( plugin_lang_get( 'auskunft_none' ) ) . '</p>';
	} else {
		echo '<table class="qt-list"><thead><tr>'
			. '<th>' . string_display_line( plugin_lang_get( 'loeschung_col_when' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'label_event_massnahme' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'label_gueltig_bis' ) ) . '</th>'
			. '<th>' . string_display_line( plugin_lang_get( 'loeschung_col_grund' ) ) . '</th>'
			. '</tr></thead><tbody>';
		foreach( $p_data['loeschungen'] as $t_l ) {
			echo '<tr><td>' . string_display_line( date( 'Y-m-d H:i', (int)$t_l['date_created'] ) ) . '</td>'
				. '<td>' . string_display_line( (string)$t_l['massnahme_schluessel'] ) . '</td>'
				. '<td>' . ( $t_l['gueltig_bis'] ? string_display_line( (string)$t_l['gueltig_bis'] ) : $t_dash ) . '</td>'
				. '<td>' . string_display_line( (string)$t_l['grund'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}

/* -------------------------------------------------------------------------- *
 *  Standalone print document (browser -> Save as PDF)
 * -------------------------------------------------------------------------- */
if( $f_format === 'print' && $t_data !== false ) {
	$t_person = $t_data['person'];
	$t_name   = trim( (string)$t_person['nachname'] . ', ' . (string)$t_person['vorname'], ', ' );
	$t_title  = plugin_lang_get( 'auskunft_doc_title' );
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html lang="<?php echo string_attribute( lang_get_current() === 'german' ? 'de' : 'en' ); ?>">
<head>
<meta charset="utf-8" />
<title><?php echo string_display_line( $t_title . ' – ' . $t_name ); ?></title>
<style>
	* { box-sizing: border-box; }
	body { font-family: "DejaVu Sans", Arial, sans-serif; color: #222; font-size: 12px; margin: 24px; }
	h1 { font-size: 20px; margin: 0 0 2px; }
	h3 { font-size: 14px; margin: 20px 0 6px; border-bottom: 2px solid #444; padding-bottom: 2px; }
	.qt-meta { color: #555; font-size: 11px; margin: 0 0 4px; }
	.qt-legal { background: #f4f4f4; border: 1px solid #ddd; padding: 8px 10px; margin: 12px 0; font-size: 11px; }
	table { border-collapse: collapse; width: 100%; margin: 0 0 6px; }
	.qt-kv th { text-align: left; width: 34%; vertical-align: top; }
	th, td { border: 1px solid #bbb; padding: 4px 6px; text-align: left; vertical-align: top; }
	thead th { background: #efefef; }
	.qt-empty { color: #777; font-style: italic; margin: 0 0 6px; }
	.qt-print { margin: 16px 0; }
	@media print { .qt-print { display: none; } body { margin: 0; } }
</style>
</head>
<body>
	<h1><?php echo string_display_line( $t_title ); ?></h1>
	<p class="qt-meta"><?php echo string_display_line( sprintf( plugin_lang_get( 'auskunft_doc_meta' ),
		$t_today, date( 'H:i' ), user_get_name( auth_get_current_user_id() ) ) ); ?></p>
	<div class="qt-print">
		<button type="button" onclick="window.print()"><?php echo string_display_line( plugin_lang_get( 'auskunft_btn_print' ) ); ?></button>
	</div>
	<div class="qt-legal"><?php echo string_display_line( plugin_lang_get( 'auskunft_legal' ) ); ?></div>
	<?php qt_auskunft_render_body( $t_data, $t_today ); ?>
</body>
</html>
	<?php
	exit;
}

/* -------------------------------------------------------------------------- *
 *  In-layout page: selector + preview
 * -------------------------------------------------------------------------- */
layout_page_header( plugin_lang_get( 'auskunft_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-id-card-o"></i>
			<?php echo plugin_lang_get( 'auskunft_title' ); ?>
		</h4>
	</div>
	<div class="widget-body"><div class="widget-main">
		<span class="help-block"><?php echo plugin_lang_get( 'auskunft_intro' ); ?></span>
		<form class="form-inline" method="get" action="<?php echo plugin_page( 'auskunft' ); ?>">
			<input type="hidden" name="page" value="QualificationTracker/auskunft" />
			<label><?php echo plugin_lang_get( 'menu_person' ); ?></label>
			<select name="person_id" class="input-sm" style="min-width:22em">
				<option value="0">&ndash;</option>
			<?php foreach( $t_persons as $t_p ) {
				$t_label = trim( (string)$t_p['nachname'] . ', ' . (string)$t_p['vorname'], ', ' );
				if( (string)$t_p['personalnummer'] !== '' ) { $t_label .= ' (' . $t_p['personalnummer'] . ')'; }
			?>
				<option value="<?php echo (int)$t_p['id']; ?>" <?php echo $f_person_id === (int)$t_p['id'] ? 'selected="selected"' : ''; ?>>
					<?php echo string_display_line( $t_label ); ?>
				</option>
			<?php } ?>
			</select>
			&nbsp;
			<button type="submit" class="btn btn-sm btn-primary btn-white btn-round">
				<i class="ace-icon fa fa-search"></i> <?php echo plugin_lang_get( 'auskunft_show' ); ?>
			</button>
		</form>
	</div></div>
</div>

<?php if( $f_person_id > 0 && $t_data === false ) { ?>
	<div class="alert alert-warning"><?php echo plugin_lang_get( 'auskunft_person_unknown' ); ?></div>
<?php } else if( $t_data !== false ) { ?>
	<div class="widget-box widget-color-grey">
		<div class="widget-header widget-header-small">
			<h4 class="widget-title lighter">
				<i class="ace-icon fa fa-file-text-o"></i>
				<?php echo string_display_line( plugin_lang_get( 'auskunft_preview' ) ); ?>
			</h4>
			<div class="widget-toolbar">
				<a class="btn btn-xs btn-primary btn-white btn-round" target="_blank"
					href="<?php echo plugin_page( 'auskunft' ); ?>&person_id=<?php echo (int)$f_person_id; ?>&format=print">
					<i class="ace-icon fa fa-print"></i> <?php echo plugin_lang_get( 'auskunft_open_print' ); ?>
				</a>
			</div>
		</div>
		<div class="widget-body"><div class="widget-main">
			<div class="qt-auskunft"><?php qt_auskunft_render_body( $t_data, $t_today ); ?></div>
		</div></div>
	</div>
	<style>
		.qt-auskunft table { width: auto; min-width: 60%; margin-bottom: 12px; }
		.qt-auskunft .qt-kv th { text-align: left; padding-right: 16px; white-space: nowrap; }
		.qt-auskunft h3 { margin-top: 18px; }
		.qt-auskunft .qt-empty { color: #888; font-style: italic; }
	</style>
<?php } ?>
</div>

<?php
layout_page_end();
