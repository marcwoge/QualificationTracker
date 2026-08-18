<?php
/**
 * QualificationTracker – printable attendance list (F3.5).
 *
 * A self-contained, print-optimised HTML page (browser "Print → Save as PDF")
 * with a signature column, the measure content and its legal basis. Kept
 * dependency-free on purpose: no PDF library is bundled. Read-only, so no CSRF.
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
require_api( 'lang_api.php' );
require_api( 'string_api.php' );

auth_reauthenticate();
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'edit' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Event.php' );
plugin_require_api( 'core/QT_Participant.php' );

$f_id = gpc_get_int( 'id' );

$t_event = qt_event_get( $f_id );
if( $t_event === false ) {
	error_parameters( $f_id );
	trigger_error( ERROR_GENERIC, ERROR );
}

$t_massnahme    = qt_massnahme_get( (int)$t_event['massnahme_id'] );
$t_participants = qt_teilnehmer_load( $f_id );

$t_dash = '&ndash;';
$t_meta = function( $p_value ) use ( $t_dash ) {
	return ( $p_value === null || $p_value === '' ) ? $t_dash : string_display_line( $p_value );
};

header( 'Content-Type: text/html; charset=UTF-8' );
$t_lang = lang_get_current();
?>
<!DOCTYPE html>
<html lang="<?php echo string_attribute( $t_lang ); ?>">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo string_display_line( plugin_lang_get( 'liste_title' ) . ' – ' . $t_event['titel'] ); ?></title>
<style>
	* { box-sizing: border-box; }
	body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color: #000; margin: 24px; font-size: 12px; }
	h1 { font-size: 18px; margin: 0 0 4px; }
	h2 { font-size: 13px; margin: 16px 0 4px; text-transform: uppercase; letter-spacing: .04em; color: #333; }
	.meta { width: 100%; border-collapse: collapse; margin: 8px 0 4px; }
	.meta td { padding: 2px 8px 2px 0; vertical-align: top; }
	.meta td.k { font-weight: bold; white-space: nowrap; width: 1%; }
	.content { border: 1px solid #999; padding: 8px 10px; margin: 6px 0; }
	table.list { width: 100%; border-collapse: collapse; margin-top: 6px; }
	table.list th, table.list td { border: 1px solid #333; padding: 6px 8px; text-align: left; }
	table.list th { background: #eee; }
	table.list td.nr { width: 32px; text-align: right; }
	table.list td.sig { width: 38%; }
	table.list tr { height: 34px; }
	.foot { margin-top: 28px; display: flex; justify-content: space-between; gap: 40px; }
	.foot .sigline { flex: 1; border-top: 1px solid #333; padding-top: 4px; }
	.toolbar { margin-bottom: 16px; }
	.toolbar button { padding: 6px 14px; font-size: 13px; cursor: pointer; }
	@media print {
		body { margin: 0; }
		.toolbar { display: none; }
		table.list tr { height: 40px; }
	}
</style>
</head>
<body>

<div class="toolbar">
	<button type="button" onclick="window.print()"><?php echo string_display_line( plugin_lang_get( 'liste_print' ) ); ?></button>
</div>

<h1><?php echo string_display_line( plugin_lang_get( 'liste_heading' ) ); ?></h1>

<table class="meta">
	<tr>
		<td class="k"><?php echo plugin_lang_get( 'label_event_massnahme' ); ?>:</td>
		<td><?php echo $t_massnahme !== false
			? string_display_line( $t_massnahme['schluessel'] . ' – ' . $t_massnahme['bezeichnung'] )
			: string_display_line( '#' . (int)$t_event['massnahme_id'] ); ?></td>
		<td class="k"><?php echo plugin_lang_get( 'event_col_termin' ); ?>:</td>
		<td><?php echo $t_meta( substr( (string)$t_event['termin'], 0, 16 ) ); ?></td>
	</tr>
	<tr>
		<td class="k"><?php echo plugin_lang_get( 'event_col_titel' ); ?>:</td>
		<td><?php echo $t_meta( $t_event['titel'] ); ?></td>
		<td class="k"><?php echo plugin_lang_get( 'event_col_ort' ); ?>:</td>
		<td><?php echo $t_meta( $t_event['ort'] ); ?></td>
	</tr>
	<tr>
		<td class="k"><?php echo plugin_lang_get( 'event_col_unterweisender' ); ?>:</td>
		<td><?php echo $t_meta( $t_event['unterweisender'] ); ?></td>
		<td class="k"><?php echo plugin_lang_get( 'label_rechtsgrundlage' ); ?>:</td>
		<td><?php echo $t_massnahme !== false ? $t_meta( $t_massnahme['rechtsgrundlage'] ) : $t_dash; ?></td>
	</tr>
</table>

<?php if( $t_massnahme !== false && $t_massnahme['bezeichnung'] !== '' ) { ?>
<h2><?php echo plugin_lang_get( 'liste_content' ); ?></h2>
<div class="content"><?php echo string_display_line( $t_massnahme['bezeichnung'] ); ?></div>
<?php } ?>

<h2><?php echo plugin_lang_get( 'liste_participants' ); ?></h2>
<table class="list">
	<thead>
		<tr>
			<th class="nr">#</th>
			<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
			<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
			<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
			<th><?php echo plugin_lang_get( 'liste_col_signature' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php $t_nr = 0; foreach( $t_participants as $t_p ) { $t_nr++; ?>
		<tr>
			<td class="nr"><?php echo $t_nr; ?></td>
			<td><?php echo $t_meta( $t_p['personalnummer'] ); ?></td>
			<td><?php echo string_display_line( trim( $t_p['nachname'] . ', ' . $t_p['vorname'], ', ' ) ); ?></td>
			<td><?php echo $t_meta( $t_p['abteilung'] ); ?></td>
			<td class="sig">&nbsp;</td>
		</tr>
	<?php } ?>
	<?php if( empty( $t_participants ) ) { ?>
		<tr><td colspan="5"><?php echo plugin_lang_get( 'teilnehmer_none' ); ?></td></tr>
	<?php } ?>
	</tbody>
</table>

<div class="foot">
	<div class="sigline"><?php echo plugin_lang_get( 'liste_sig_date_place' ); ?></div>
	<div class="sigline"><?php echo plugin_lang_get( 'liste_sig_instructor' ); ?></div>
</div>

</body>
</html>
