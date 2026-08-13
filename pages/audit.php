<?php
/**
 * QualificationTracker – as-of-date audit report (F4.5).
 *
 * A self-contained, print-optimised HTML page (browser "Print → Save as PDF")
 * showing the compliance rate per department as of a key date, plus a list of
 * the outstanding deficiencies. Kept dependency-free: no PDF library. Read-only.
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
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_CustomFields.php' );
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_SollIst.php' );
plugin_require_api( 'core/QT_Matrix.php' );

$f_stichtag  = gpc_get_string( 'stichtag', '' );
$f_abteilung = gpc_get_string( 'abteilung', '' );
$f_typ       = gpc_get_string( 'typ', '' );

if( $f_stichtag === '' || !qt_person_valid_date( $f_stichtag ) || $f_stichtag === '' ) {
	$f_stichtag = date( 'Y-m-d' );
}

$t_report = qt_matrix_compliance( $f_stichtag, array(
	'abteilung' => $f_abteilung,
	'typ'       => $f_typ,
) );

$t_state_lang = array(
	'offen'      => 'matrix_state_offen',
	'abgelaufen' => 'matrix_state_abgelaufen',
	'fehlt'      => 'matrix_state_fehlt',
);

# Collect the deficiencies (non-fulfilled cells) for the detail list.
$t_gaps = array();
foreach( $t_report['matrix']['persons'] as $t_person ) {
	$t_pid = (int)$t_person['id'];
	foreach( $t_report['matrix']['cells'][$t_pid] as $t_cell ) {
		if( $t_cell['state'] === 'gueltig' || $t_cell['state'] === 'bald' ) {
			continue;
		}
		$t_gaps[] = array(
			'abteilung' => ( $t_person['abteilung'] !== '' ? $t_person['abteilung'] : '—' ),
			'person'    => trim( $t_person['nachname'] . ', ' . $t_person['vorname'], ', ' ),
			'massnahme' => $t_cell['massnahme']['schluessel'] . ' – ' . $t_cell['massnahme']['bezeichnung'],
			'state'     => $t_cell['state'],
		);
	}
}

header( 'Content-Type: text/html; charset=UTF-8' );
?>
<!DOCTYPE html>
<html lang="<?php echo string_attribute( lang_get_current() ); ?>">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo string_display_line( plugin_lang_get( 'audit_title' ) . ' – ' . $f_stichtag ); ?></title>
<style>
	* { box-sizing: border-box; }
	body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color: #000; margin: 24px; font-size: 12px; }
	h1 { font-size: 18px; margin: 0 0 2px; }
	h2 { font-size: 13px; margin: 18px 0 4px; text-transform: uppercase; letter-spacing: .04em; color: #333; }
	.sub { color: #555; margin-bottom: 8px; }
	table { width: 100%; border-collapse: collapse; margin-top: 6px; }
	th, td { border: 1px solid #333; padding: 5px 8px; text-align: left; }
	th { background: #eee; }
	td.num { text-align: right; }
	tr.total td { font-weight: bold; background: #f5f5f5; }
	.rate { font-weight: bold; }
	.bar { display: inline-block; height: 9px; background: #d4edda; border: 1px solid #9ccaa9; vertical-align: middle; margin-left: 6px; }
	.toolbar { margin-bottom: 16px; }
	.toolbar button { padding: 6px 14px; font-size: 13px; cursor: pointer; }
	.filters { margin: 6px 0 2px; }
	@media print { body { margin: 0; } .toolbar { display: none; } }
</style>
</head>
<body>

<div class="toolbar">
	<button type="button" onclick="window.print()"><?php echo string_display_line( plugin_lang_get( 'liste_print' ) ); ?></button>
	<form method="get" action="<?php echo string_attribute( plugin_page( 'audit' ) ); ?>" class="filters" style="display:inline-block; margin-left:16px">
		<input type="hidden" name="page" value="QualificationTracker/audit" />
		<input type="hidden" name="abteilung" value="<?php echo string_attribute( $f_abteilung ); ?>" />
		<input type="hidden" name="typ" value="<?php echo string_attribute( $f_typ ); ?>" />
		<label><?php echo plugin_lang_get( 'audit_stichtag' ); ?>
			<input type="date" name="stichtag" value="<?php echo string_attribute( $f_stichtag ); ?>" onchange="this.form.submit()" />
		</label>
	</form>
</div>

<h1><?php echo string_display_line( plugin_lang_get( 'audit_title' ) ); ?></h1>
<div class="sub">
	<?php echo plugin_lang_get( 'audit_stichtag' ) . ': ' . string_display_line( $f_stichtag ); ?>
	<?php if( $f_abteilung !== '' ) { echo ' · ' . plugin_lang_get( 'filter_abteilung' ) . ': ' . string_display_line( $f_abteilung ); } ?>
</div>

<h2><?php echo plugin_lang_get( 'audit_by_department' ); ?></h2>
<table>
	<thead>
		<tr>
			<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
			<th class="num"><?php echo plugin_lang_get( 'audit_persons' ); ?></th>
			<th class="num"><?php echo plugin_lang_get( 'audit_soll' ); ?></th>
			<th class="num"><?php echo plugin_lang_get( 'audit_erfuellt' ); ?></th>
			<th class="num"><?php echo plugin_lang_get( 'matrix_state_offen' ); ?></th>
			<th class="num"><?php echo plugin_lang_get( 'matrix_state_abgelaufen' ); ?></th>
			<th class="num"><?php echo plugin_lang_get( 'matrix_state_fehlt' ); ?></th>
			<th class="num"><?php echo plugin_lang_get( 'audit_rate' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php
	$t_render_row = function( $p_name, array $p_s, $p_is_total ) {
		$t_rate = isset( $p_s['rate'] ) ? $p_s['rate'] : 0;
		?>
		<tr class="<?php echo $p_is_total ? 'total' : ''; ?>">
			<td><?php echo string_display_line( $p_name ); ?></td>
			<td class="num"><?php echo (int)$p_s['persons']; ?></td>
			<td class="num"><?php echo (int)$p_s['soll']; ?></td>
			<td class="num"><?php echo (int)$p_s['erfuellt']; ?></td>
			<td class="num"><?php echo (int)$p_s['offen']; ?></td>
			<td class="num"><?php echo (int)$p_s['abgelaufen']; ?></td>
			<td class="num"><?php echo (int)$p_s['fehlt']; ?></td>
			<td class="num rate"><?php echo number_format( $t_rate, 1 ); ?>&nbsp;%<span class="bar" style="width:<?php echo (int)round( $t_rate / 2 ); ?>px"></span></td>
		</tr>
		<?php
	};
	foreach( $t_report['departments'] as $t_name => $t_stats ) {
		$t_render_row( $t_name, $t_stats, false );
	}
	?>
	</tbody>
	<tfoot>
		<?php $t_render_row( plugin_lang_get( 'audit_total' ), $t_report['total'], true ); ?>
	</tfoot>
</table>

<h2><?php echo plugin_lang_get( 'audit_deficiencies' ); ?> (<?php echo count( $t_gaps ); ?>)</h2>
<?php if( empty( $t_gaps ) ) { ?>
	<p><?php echo plugin_lang_get( 'audit_no_deficiencies' ); ?></p>
<?php } else { ?>
<table>
	<thead>
		<tr>
			<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
			<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
			<th><?php echo plugin_lang_get( 'label_event_massnahme' ); ?></th>
			<th><?php echo plugin_lang_get( 'event_col_status' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach( $t_gaps as $t_g ) { ?>
		<tr>
			<td><?php echo string_display_line( $t_g['abteilung'] ); ?></td>
			<td><?php echo string_display_line( $t_g['person'] ); ?></td>
			<td><?php echo string_display_line( $t_g['massnahme'] ); ?></td>
			<td><?php echo plugin_lang_get( $t_state_lang[$t_g['state']] ); ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
<?php } ?>

</body>
</html>
<?php
exit;
