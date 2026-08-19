<?php
/**
 * QualificationTracker – records of processing page (Verarbeitungsverzeichnis, F7.6).
 *
 * Renders a configuration-aware version of the Art. 30 DSGVO record: the
 * retention periods, projects and roles shown are the ones actually configured.
 * The default view previews it in the layout; format=print emits a standalone,
 * print-optimised document (browser -> Save as PDF). The full fill-in template
 * ships as docs/Verarbeitungsverzeichnis.md.
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
require_api( 'gpc_api.php' );
require_api( 'helper_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'project_api.php' );
require_api( 'string_api.php' );

auth_reauthenticate();
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'manage' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Deletion.php' );
plugin_require_api( 'core/QT_Register.php' );

$f_format = gpc_get_string( 'format', '' );
$t_ctx    = qt_verzeichnis_context();
$t_today  = date( 'Y-m-d' );

/**
 * Render the record body (shared by preview and print document).
 *
 * @param array $p_ctx From qt_verzeichnis_context().
 * @return void
 */
function qt_verzeichnis_render_body( array $p_ctx ) {
	$t_vorsorge = $p_ctx['vorsorge_name'] !== ''
		? $p_ctx['vorsorge_name']
		: plugin_lang_get( 'verz_vorsorge_none' );

	$t_section = function( $p_key, $p_body ) {
		echo '<h3>' . string_display_line( plugin_lang_get( $p_key ) ) . '</h3>';
		echo '<p>' . $p_body . '</p>';
	};

	$t_section( 'verz_s_zwecke', string_display_line( plugin_lang_get( 'verz_b_zwecke' ) ) );
	$t_section( 'verz_s_betroffene', string_display_line( plugin_lang_get( 'verz_b_betroffene' ) ) );
	$t_section( 'verz_s_daten', string_display_line( plugin_lang_get( 'verz_b_daten' ) ) );
	$t_section( 'verz_s_empfaenger',
		string_display_line( sprintf( plugin_lang_get( 'verz_b_empfaenger' ), $t_vorsorge ) ) );
	$t_section( 'verz_s_rechtsgrundlage', string_display_line( plugin_lang_get( 'verz_b_rechtsgrundlage' ) ) );

	# --- Retention table (live values) --------------------------------------
	echo '<h3>' . string_display_line( plugin_lang_get( 'verz_s_loeschung' ) ) . '</h3>';
	echo '<table class="qt-list"><thead><tr>'
		. '<th>' . string_display_line( plugin_lang_get( 'col_typ' ) ) . '</th>'
		. '<th>' . string_display_line( plugin_lang_get( 'verz_col_frist' ) ) . '</th>'
		. '</tr></thead><tbody>';
	$t_rows = qt_verzeichnis_aufbewahrung_zeilen(
		$p_ctx['aufbewahrung_default'], $p_ctx['aufbewahrung_map'], qt_catalog_types() );
	foreach( $t_rows as $t_r ) {
		$t_label = $t_r['is_default']
			? plugin_lang_get( 'verz_default' )
			: plugin_lang_get( 'type_' . $t_r['typ'] );
		$t_frist = (int)$t_r['monate'] > 0
			? sprintf( plugin_lang_get( 'verz_months' ), (int)$t_r['monate'] )
			: plugin_lang_get( 'verz_no_delete' );
		echo '<tr><td>' . string_display_line( $t_label ) . '</td>'
			. '<td>' . string_display_line( $t_frist ) . '</td></tr>';
	}
	echo '</tbody></table>';

	$t_section( 'verz_s_drittland', string_display_line( plugin_lang_get( 'verz_b_drittland' ) ) );

	# --- TOM with live role levels ------------------------------------------
	$t_roles = sprintf( plugin_lang_get( 'verz_b_tom' ),
		get_enum_element( 'access_levels', $p_ctx['view_threshold'] ),
		get_enum_element( 'access_levels', $p_ctx['edit_threshold'] ),
		get_enum_element( 'access_levels', $p_ctx['manage_threshold'] ),
		get_enum_element( 'access_levels', $p_ctx['admin_threshold'] ) );
	$t_section( 'verz_s_tom', string_display_line( $t_roles ) );
}

/* -------------------------------------------------------------------------- *
 *  Standalone print document
 * -------------------------------------------------------------------------- */
if( $f_format === 'print' ) {
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html lang="<?php echo string_attribute( lang_get_current() === 'german' ? 'de' : 'en' ); ?>">
<head>
<meta charset="utf-8" />
<title><?php echo string_display_line( plugin_lang_get( 'verz_doc_title' ) ); ?></title>
<style>
	* { box-sizing: border-box; }
	body { font-family: "DejaVu Sans", Arial, sans-serif; color: #222; font-size: 12px; margin: 24px; }
	h1 { font-size: 20px; margin: 0 0 2px; }
	h3 { font-size: 14px; margin: 18px 0 4px; border-bottom: 2px solid #444; padding-bottom: 2px; }
	p { margin: 0 0 6px; }
	.qt-meta { color: #555; font-size: 11px; margin: 0 0 4px; }
	.qt-legal { background: #f4f4f4; border: 1px solid #ddd; padding: 8px 10px; margin: 12px 0; font-size: 11px; }
	table { border-collapse: collapse; width: auto; min-width: 50%; margin: 0 0 8px; }
	th, td { border: 1px solid #bbb; padding: 4px 8px; text-align: left; }
	thead th { background: #efefef; }
	.qt-print { margin: 16px 0; }
	@media print { .qt-print { display: none; } body { margin: 0; } }
</style>
</head>
<body>
	<h1><?php echo string_display_line( plugin_lang_get( 'verz_doc_title' ) ); ?></h1>
	<p class="qt-meta"><?php echo string_display_line( sprintf( plugin_lang_get( 'verz_doc_meta' ), $t_today ) ); ?></p>
	<div class="qt-print">
		<button type="button" onclick="window.print()"><?php echo string_display_line( plugin_lang_get( 'auskunft_btn_print' ) ); ?></button>
	</div>
	<div class="qt-legal"><?php echo string_display_line( plugin_lang_get( 'verz_legal' ) ); ?></div>
	<?php qt_verzeichnis_render_body( $t_ctx ); ?>
</body>
</html>
	<?php
	exit;
}

/* -------------------------------------------------------------------------- *
 *  In-layout page
 * -------------------------------------------------------------------------- */
layout_page_header( plugin_lang_get( 'verz_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-book"></i>
			<?php echo plugin_lang_get( 'verz_title' ); ?>
		</h4>
		<div class="widget-toolbar">
			<a class="btn btn-xs btn-primary btn-white btn-round" target="_blank"
				href="<?php echo plugin_page( 'verzeichnis' ); ?>&format=print">
				<i class="ace-icon fa fa-print"></i> <?php echo plugin_lang_get( 'auskunft_open_print' ); ?>
			</a>
		</div>
	</div>
	<div class="widget-body"><div class="widget-main">
		<span class="help-block"><?php echo plugin_lang_get( 'verz_intro' ); ?></span>
		<div class="alert alert-info"><?php echo plugin_lang_get( 'verz_template_hint' ); ?></div>
		<div class="qt-verzeichnis"><?php qt_verzeichnis_render_body( $t_ctx ); ?></div>
	</div></div>
</div>
<style>
	.qt-verzeichnis h3 { margin-top: 18px; }
	.qt-verzeichnis table { width: auto; min-width: 40%; margin-bottom: 12px; }
	.qt-verzeichnis th, .qt-verzeichnis td { padding: 4px 10px; }
</style>
</div>

<?php
layout_page_end();
