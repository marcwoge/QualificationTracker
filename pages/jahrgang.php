<?php
/**
 * QualificationTracker – forward-looking cohort generation (F2.8).
 *
 * GET previews the calendar-year / reference-month tickets that would be created
 * in advance for a chosen year; POST creates them. This is what makes group
 * scheduling (M3) possible.
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
require_api( 'form_api.php' );
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

$t_today       = date( 'Y-m-d' );
$t_zielprojekt = (int)plugin_config_get( 'zielprojekt_id' );
$f_year        = gpc_get_int( 'year', (int)date( 'Y' ) + 1 );
if( $f_year < 2000 || $f_year > 2100 ) {
	$f_year = (int)date( 'Y' ) + 1;
}

if( gpc_get_string( 'action', '' ) === 'jahrgang' ) {
	form_security_validate( 'plugin_QualificationTracker_jahrgang' );
	$t_summary = qt_generator_run_jahrgang( $f_year, $t_today );
	form_security_purge( 'plugin_QualificationTracker_jahrgang' );
	print_successful_redirect( plugin_page( 'jahrgang', true )
		. '&year=' . (int)$f_year . '&msg=generated'
		. '&c=' . (int)$t_summary['created']
		. '&s=' . (int)$t_summary['skipped']
		. '&p=' . (int)$t_summary['persons']
		. '&e=' . count( $t_summary['errors'] ) );
	exit;
}

$t_rows = qt_generator_jahrgang_plan( $f_year, $t_today );
$t_create = array();
$t_skip = 0;
foreach( $t_rows as $t_row ) {
	if( $t_row['action'] === 'create' ) {
		$t_create[] = $t_row;
	} else {
		$t_skip++;
	}
}
$t_msg = gpc_get_string( 'msg', '' );

layout_page_header( plugin_lang_get( 'jahrgang_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg === 'generated' ) {
	$t_e = gpc_get_int( 'e', 0 );
?>
	<div class="alert <?php echo $t_e > 0 ? 'alert-warning' : 'alert-success'; ?>">
		<?php echo plugin_lang_get( 'generate_msg' ) . ' '
			. gpc_get_int( 'c', 0 ) . ' ' . plugin_lang_get( 'generate_created' ) . ', '
			. gpc_get_int( 's', 0 ) . ' ' . plugin_lang_get( 'generate_skipped' )
			. ' (' . gpc_get_int( 'p', 0 ) . ' ' . plugin_lang_get( 'dryrun_persons' ) . ').'; ?>
	</div>
<?php } ?>

<?php if( $t_zielprojekt <= 0 ) { ?>
	<div class="alert alert-danger"><?php echo plugin_lang_get( 'generate_no_zielprojekt' ); ?></div>
<?php } ?>

<form action="<?php echo plugin_page( 'jahrgang' ); ?>" method="post">
<?php echo form_security_field( 'plugin_QualificationTracker_jahrgang' ); ?>
<input type="hidden" name="action" value="jahrgang" />
<input type="hidden" name="year" value="<?php echo (int)$f_year; ?>" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-calendar"></i>
			<?php echo plugin_lang_get( 'jahrgang_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<span class="help-block pull-left" style="margin:4px 12px 0 0"><?php echo plugin_lang_get( 'jahrgang_intro' ); ?></span>
		<span class="form-inline pull-right">
			<label><?php echo plugin_lang_get( 'jahrgang_year' ); ?>&nbsp;</label>
			<input type="number" min="2000" max="2100" class="input-sm" style="width:7em"
				value="<?php echo (int)$f_year; ?>"
				onchange="window.location='<?php echo plugin_page( 'jahrgang' ); ?>&amp;year='+this.value" />
			&nbsp;
			<span class="label label-success"><?php echo plugin_lang_get( 'generate_action_create' ) . ': ' . count( $t_create ); ?></span>
			<span class="label label-default"><?php echo plugin_lang_get( 'generate_action_skip' ) . ': ' . (int)$t_skip; ?></span>
		</span>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_person' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_schluessel' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_bezeichnung' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_modus' ); ?></th>
				<th><?php echo plugin_lang_get( 'label_soll_termin' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_create as $t_row ) { ?>
			<tr>
				<td><?php echo string_display_line( $t_row['person'] ); ?></td>
				<td><?php echo string_display_line( $t_row['abteilung'] ); ?></td>
				<td><?php echo string_display_line( $t_row['schluessel'] ); ?></td>
				<td><?php echo string_display_line( $t_row['bezeichnung'] ); ?></td>
				<td><?php echo string_display_line( plugin_lang_get( 'mode_' . $t_row['modus'] ) ); ?></td>
				<td><?php echo string_display_line( $t_row['soll_termin'] ); ?></td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_create ) ) { ?>
			<tr><td colspan="6" class="center"><?php echo plugin_lang_get( 'dryrun_nothing' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			<?php echo ( $t_zielprojekt <= 0 || empty( $t_create ) ) ? 'disabled="disabled"' : ''; ?>
			onclick="return confirm('<?php echo string_attribute( plugin_lang_get( 'jahrgang_confirm' ) ); ?>');"
			value="<?php echo string_attribute( plugin_lang_get( 'btn_generate_jahrgang' ) ); ?>" />
		<a class="btn btn-white btn-round" href="<?php echo plugin_page( 'dryrun' ); ?>">
			<?php echo plugin_lang_get( 'btn_cancel' ); ?>
		</a>
	</div>
	</div>
</div>
</form>
</div>

<?php
layout_page_end();
