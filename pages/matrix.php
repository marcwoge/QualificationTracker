<?php
/**
 * QualificationTracker – qualification matrix (F4.1 / F4.2).
 *
 * Read-only person × measure grid. Each cell is coloured by the remaining
 * validity of the proof and links to its ticket. Filterable by department,
 * profile, measure type and cell status; the axes can be swapped (F4.2).
 * Pagination (F4.3) builds on this.
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
plugin_require_api( 'core/QT_Profile.php' );
plugin_require_api( 'core/QT_Matrix.php' );

# Rows per page; kept modest so the rendered table stays light for large staffs.
define( 'QT_MATRIX_PER_PAGE', 50 );

$f_abteilung = gpc_get_string( 'abteilung', '' );
$f_profil    = gpc_get_int( 'profil_id', 0 );
$f_typ       = gpc_get_string( 'typ', '' );
$f_status    = gpc_get_string( 'status', '' );
$f_transpose = gpc_get_bool( 'transpose', false );
$f_page      = gpc_get_int( 'mpage', 1 );
$t_today     = date( 'Y-m-d' );

$t_matrix = qt_matrix_build( $t_today, array(
	'abteilung' => $f_abteilung,
	'profil_id' => $f_profil,
	'typ'       => $f_typ,
	'status'    => $f_status,
	'page'      => $f_page,
	'per_page'  => QT_MATRIX_PER_PAGE,
) );

# Effective page after clamping by the builder.
$f_page = (int)$t_matrix['page'];

# Base query string that preserves the current filters/layout for nav links.
$t_base_qs = 'abteilung=' . urlencode( $f_abteilung ) . '&profil_id=' . $f_profil
	. '&typ=' . urlencode( $f_typ ) . '&status=' . urlencode( $f_status )
	. '&transpose=' . ( $f_transpose ? '1' : '0' );

$t_abteilungen = qt_person_distinct_abteilungen();
$t_profile     = qt_profil_load_all();

$t_state_lang = array(
	'gueltig'    => 'matrix_state_gueltig',
	'bald'       => 'matrix_state_bald',
	'offen'      => 'matrix_state_offen',
	'abgelaufen' => 'matrix_state_abgelaufen',
	'fehlt'      => 'matrix_state_fehlt',
);

# Preserve the current filters when toggling the layout.
$t_toggle_params = 'abteilung=' . urlencode( $f_abteilung ) . '&profil_id=' . $f_profil
	. '&typ=' . urlencode( $f_typ ) . '&status=' . urlencode( $f_status )
	. '&transpose=' . ( $f_transpose ? '0' : '1' );

layout_page_header( plugin_lang_get( 'matrix_title' ) );
layout_page_begin();
?>

<style>
	table.qt-matrix { border-collapse: collapse; }
	table.qt-matrix th, table.qt-matrix td { border: 1px solid #ccc; padding: 3px 6px; font-size: 11px; }
	table.qt-matrix thead th { background: #f5f5f5; white-space: nowrap; }
	table.qt-matrix th.qt-rot { writing-mode: vertical-rl; transform: rotate(180deg); height: 130px; text-align: left; vertical-align: bottom; font-weight: normal; }
	table.qt-matrix td.qt-head { white-space: nowrap; text-align: left; font-weight: bold; background: #fafafa; }
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
	form.qt-filters label { margin-left: 8px; }
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
		<form class="form-inline qt-filters" method="get" action="<?php echo plugin_page( 'matrix' ); ?>">
			<input type="hidden" name="page" value="QualificationTracker/matrix" />
			<input type="hidden" name="transpose" value="<?php echo $f_transpose ? '1' : '0'; ?>" />

			<label><?php echo plugin_lang_get( 'filter_abteilung' ); ?></label>
			<select name="abteilung" class="input-sm" onchange="this.form.submit()">
				<option value=""><?php echo plugin_lang_get( 'filter_all' ); ?></option>
			<?php foreach( $t_abteilungen as $t_ab ) { ?>
				<option value="<?php echo string_attribute( $t_ab ); ?>" <?php echo $f_abteilung === $t_ab ? 'selected="selected"' : ''; ?>><?php echo string_display_line( $t_ab ); ?></option>
			<?php } ?>
			</select>

			<label><?php echo plugin_lang_get( 'menu_profil' ); ?></label>
			<select name="profil_id" class="input-sm" onchange="this.form.submit()">
				<option value="0"><?php echo plugin_lang_get( 'filter_all' ); ?></option>
			<?php foreach( $t_profile as $t_pr ) { ?>
				<option value="<?php echo (int)$t_pr['id']; ?>" <?php echo $f_profil === (int)$t_pr['id'] ? 'selected="selected"' : ''; ?>><?php echo string_display_line( $t_pr['name'] ); ?></option>
			<?php } ?>
			</select>

			<label><?php echo plugin_lang_get( 'col_typ' ); ?></label>
			<select name="typ" class="input-sm" onchange="this.form.submit()">
				<option value=""><?php echo plugin_lang_get( 'filter_all' ); ?></option>
			<?php foreach( qt_catalog_types() as $t_t ) { ?>
				<option value="<?php echo $t_t; ?>" <?php echo $f_typ === $t_t ? 'selected="selected"' : ''; ?>><?php echo plugin_lang_get( 'type_' . $t_t ); ?></option>
			<?php } ?>
			</select>

			<label><?php echo plugin_lang_get( 'event_col_status' ); ?></label>
			<select name="status" class="input-sm" onchange="this.form.submit()">
				<option value=""><?php echo plugin_lang_get( 'filter_all' ); ?></option>
			<?php foreach( $t_state_lang as $t_key => $t_langkey ) { ?>
				<option value="<?php echo $t_key; ?>" <?php echo $f_status === $t_key ? 'selected="selected"' : ''; ?>><?php echo plugin_lang_get( $t_langkey ); ?></option>
			<?php } ?>
			</select>

			<a class="btn btn-xs btn-white btn-round" style="margin-left:12px"
				href="<?php echo plugin_page( 'matrix' ) . '&amp;' . str_replace( '&', '&amp;', $t_toggle_params ); ?>">
				<i class="ace-icon fa fa-exchange"></i> <?php echo plugin_lang_get( 'matrix_transpose' ); ?>
			</a>
		</form>
	</div>

	<div class="widget-toolbox padding-8">
		<span class="qt-legend">
			<?php foreach( $t_state_lang as $t_key => $t_langkey ) { ?>
				<span><i class="box qt-s-<?php echo $t_key; ?>"></i><?php echo plugin_lang_get( $t_langkey ); ?></span>
			<?php } ?>
			<span><i class="box qt-s-na"></i><?php echo plugin_lang_get( 'matrix_state_na' ); ?></span>
		</span>
	</div>

	<?php
	# Build the row/column axes; transpose swaps persons and measures.
	$t_person_items = array();
	foreach( $t_matrix['persons'] as $t_p ) {
		$t_person_items[] = array(
			'id'    => (int)$t_p['id'],
			'label' => trim( $t_p['nachname'] . ', ' . $t_p['vorname'], ', ' ),
			'title' => (string)$t_p['abteilung'],
		);
	}
	$t_measure_items = array();
	foreach( $t_matrix['massnahmen'] as $t_m ) {
		$t_measure_items[] = array(
			'id'    => (int)$t_m['id'],
			'label' => $t_m['schluessel'],
			'title' => $t_m['bezeichnung'],
		);
	}

	if( $f_transpose ) {
		$t_row_items = $t_measure_items; $t_col_items = $t_person_items; $t_rows_are_person = false;
		$t_corner = plugin_lang_get( 'label_event_massnahme' );
	} else {
		$t_row_items = $t_person_items; $t_col_items = $t_measure_items; $t_rows_are_person = true;
		$t_corner = plugin_lang_get( 'col_name' );
	}
	?>

	<div class="widget-main no-padding">
	<div class="table-responsive" style="overflow-x:auto">
	<table class="qt-matrix">
		<thead>
			<tr>
				<th><?php echo string_display_line( $t_corner ); ?></th>
			<?php foreach( $t_col_items as $t_col ) { ?>
				<th class="qt-rot" title="<?php echo string_attribute( $t_col['title'] ); ?>"><?php echo string_display_line( $t_col['label'] ); ?></th>
			<?php } ?>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_row_items as $t_row ) { ?>
			<tr>
				<td class="qt-head" title="<?php echo string_attribute( $t_row['title'] ); ?>"><?php echo string_display_line( $t_row['label'] ); ?></td>
			<?php foreach( $t_col_items as $t_col ) {
				$t_pid = $t_rows_are_person ? $t_row['id'] : $t_col['id'];
				$t_mid = $t_rows_are_person ? $t_col['id'] : $t_row['id'];
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
		<?php if( empty( $t_row_items ) ) { ?>
			<tr><td class="center"><?php echo plugin_lang_get( 'matrix_empty' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<span class="pull-left" style="margin-top:4px">
			<?php echo sprintf( plugin_lang_get( 'matrix_page_info' ),
				(int)$t_matrix['page'], (int)$t_matrix['page_count'], (int)$t_matrix['total'] ); ?>
		</span>
		<?php if( (int)$t_matrix['page_count'] > 1 ) { ?>
		<span class="btn-group pull-right">
			<a class="btn btn-xs btn-white btn-round <?php echo $f_page <= 1 ? 'disabled' : ''; ?>"
				href="<?php echo plugin_page( 'matrix' ) . '&amp;' . str_replace( '&', '&amp;', $t_base_qs ) . '&amp;mpage=' . ( $f_page - 1 ); ?>">
				<i class="ace-icon fa fa-chevron-left"></i> <?php echo plugin_lang_get( 'matrix_prev' ); ?>
			</a>
			<a class="btn btn-xs btn-white btn-round <?php echo $f_page >= (int)$t_matrix['page_count'] ? 'disabled' : ''; ?>"
				href="<?php echo plugin_page( 'matrix' ) . '&amp;' . str_replace( '&', '&amp;', $t_base_qs ) . '&amp;mpage=' . ( $f_page + 1 ); ?>">
				<?php echo plugin_lang_get( 'matrix_next' ); ?> <i class="ace-icon fa fa-chevron-right"></i>
			</a>
		</span>
		<?php } ?>
	</div>
	</div>
</div>
</div>

<?php
layout_page_end();
