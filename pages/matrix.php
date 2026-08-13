<?php
/**
 * QualificationTracker – qualification matrix (F4.1).
 *
 * Read-only person × measure grid. Each cell is coloured by the remaining
 * validity of the proof and links to its ticket. Filterable by department.
 * Filtering/grouping (F4.2) and pagination (F4.3) build on this.
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

$f_abteilung = gpc_get_string( 'abteilung', '' );
$t_today     = date( 'Y-m-d' );

$t_matrix      = qt_matrix_build( $t_today, $f_abteilung );
$t_abteilungen = qt_person_distinct_abteilungen();

# State → bootstrap contextual background class and short label key.
$t_state_class = array(
	'gueltig'    => 'success',
	'bald'       => 'warning',
	'offen'      => 'info',
	'abgelaufen' => 'danger',
	'fehlt'      => 'default',
);
$t_state_lang = array(
	'gueltig'    => 'matrix_state_gueltig',
	'bald'       => 'matrix_state_bald',
	'offen'      => 'matrix_state_offen',
	'abgelaufen' => 'matrix_state_abgelaufen',
	'fehlt'      => 'matrix_state_fehlt',
);

layout_page_header( plugin_lang_get( 'matrix_title' ) );
layout_page_begin();
?>

<style>
	table.qt-matrix { border-collapse: collapse; }
	table.qt-matrix th, table.qt-matrix td { border: 1px solid #ccc; padding: 3px 6px; font-size: 11px; }
	table.qt-matrix thead th { background: #f5f5f5; white-space: nowrap; }
	table.qt-matrix th.qt-rot { writing-mode: vertical-rl; transform: rotate(180deg); height: 130px; text-align: left; vertical-align: bottom; font-weight: normal; }
	table.qt-matrix td.qt-person { white-space: nowrap; text-align: left; font-weight: bold; background: #fafafa; }
	table.qt-matrix td.qt-cell { text-align: center; min-width: 34px; }
	table.qt-matrix td.qt-cell a { display: block; color: inherit; text-decoration: none; }
	td.qt-s-gueltig    { background: #d4edda; }
	td.qt-s-bald       { background: #fff3cd; }
	td.qt-s-offen      { background: #d9edf7; }
	td.qt-s-abgelaufen { background: #f2dede; }
	td.qt-s-fehlt      { background: #eeeeee; color: #999; }
	td.qt-s-na         { background: #ffffff; color: #ccc; }
	.qt-legend span { display: inline-block; margin-right: 12px; }
	.qt-legend i.box { display: inline-block; width: 12px; height: 12px; border: 1px solid #ccc; vertical-align: middle; margin-right: 4px; }
</style>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-th"></i>
			<?php echo plugin_lang_get( 'matrix_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<span class="qt-legend pull-left" style="margin:4px 0">
			<?php foreach( $t_state_lang as $t_key => $t_langkey ) { ?>
				<span><i class="box qt-s-<?php echo $t_key; ?>"></i><?php echo plugin_lang_get( $t_langkey ); ?></span>
			<?php } ?>
			<span><i class="box qt-s-na"></i><?php echo plugin_lang_get( 'matrix_state_na' ); ?></span>
		</span>
		<form class="form-inline pull-right" method="get" action="<?php echo plugin_page( 'matrix' ); ?>">
			<input type="hidden" name="page" value="QualificationTracker/matrix" />
			<label><?php echo plugin_lang_get( 'filter_abteilung' ); ?>&nbsp;</label>
			<select name="abteilung" class="input-sm" onchange="this.form.submit()">
				<option value=""><?php echo plugin_lang_get( 'filter_all' ); ?></option>
			<?php foreach( $t_abteilungen as $t_ab ) { ?>
				<option value="<?php echo string_attribute( $t_ab ); ?>" <?php echo $f_abteilung === $t_ab ? 'selected="selected"' : ''; ?>>
					<?php echo string_display_line( $t_ab ); ?>
				</option>
			<?php } ?>
			</select>
		</form>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive" style="overflow-x:auto">
	<table class="qt-matrix">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
			<?php foreach( $t_matrix['massnahmen'] as $t_m ) { ?>
				<th class="qt-rot" title="<?php echo string_attribute( $t_m['bezeichnung'] ); ?>">
					<?php echo string_display_line( $t_m['schluessel'] ); ?>
				</th>
			<?php } ?>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_matrix['persons'] as $t_person ) {
			$t_pid = (int)$t_person['id'];
			$t_name = trim( $t_person['nachname'] . ', ' . $t_person['vorname'], ', ' );
		?>
			<tr>
				<td class="qt-person"><?php echo string_display_line( $t_name ); ?></td>
			<?php foreach( $t_matrix['massnahmen'] as $t_m ) {
				$t_mid = (int)$t_m['id'];
				$t_cell = isset( $t_matrix['cells'][$t_pid][$t_mid] ) ? $t_matrix['cells'][$t_pid][$t_mid] : null;
				if( $t_cell === null ) { ?>
					<td class="qt-cell qt-s-na">&middot;</td>
				<?php continue; }
				$t_state = $t_cell['state'];
				$t_label = ( $t_state === 'gueltig' || $t_state === 'bald' )
					? ( $t_cell['rest'] === null ? '&infin;' : (int)$t_cell['rest'] )
					: plugin_lang_get( $t_state_lang[$t_state] );
				$t_title = plugin_lang_get( $t_state_lang[$t_state] )
					. ( $t_cell['rest'] !== null ? ' – ' . (int)$t_cell['rest'] . ' ' . plugin_lang_get( 'matrix_days' ) : '' );
			?>
				<td class="qt-cell qt-s-<?php echo $t_state; ?>" title="<?php echo string_attribute( $t_title ); ?>">
					<?php if( (int)$t_cell['bug_id'] > 0 ) { ?>
						<a href="<?php echo string_attribute( string_get_bug_view_url( (int)$t_cell['bug_id'] ) ); ?>"><?php echo $t_label; ?></a>
					<?php } else { echo $t_label; } ?>
				</td>
			<?php } ?>
			</tr>
		<?php } ?>
		<?php if( empty( $t_matrix['persons'] ) ) { ?>
			<tr><td class="center"><?php echo plugin_lang_get( 'matrix_empty' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>
	</div>
</div>
</div>

<?php
layout_page_end();
