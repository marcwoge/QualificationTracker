<?php
/**
 * QualificationTracker – generate the proof-ticket chain for a person (F2.3).
 *
 * GET shows the plan (which tickets would be created / skipped); POST creates
 * them. A fuller cross-person dry-run follows in F2.6.
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

$f_person = gpc_get_int( 'person_id', 0 );
$t_person = qt_person_get( $f_person );
if( $t_person === false ) {
	error_parameters( $f_person );
	trigger_error( ERROR_GENERIC, ERROR );
}

# "Today" – the generator reads the clock once, here, and passes it down so the
# core logic stays clock-free and testable.
$t_today = date( 'Y-m-d' );

if( gpc_get_string( 'action', '' ) === 'generate' ) {
	form_security_validate( 'plugin_QualificationTracker_generate' );
	$t_summary = qt_generator_run_for_person( $f_person, $t_today );
	form_security_purge( 'plugin_QualificationTracker_generate' );
	print_successful_redirect( plugin_page( 'zuordnung', true )
		. '&msg=generated'
		. '&c=' . (int)$t_summary['created']
		. '&s=' . (int)$t_summary['skipped']
		. '&e=' . count( $t_summary['errors'] ) );
	exit;
}

$t_plan        = qt_generator_plan( $t_person, $t_today );
$t_zielprojekt = (int)plugin_config_get( 'zielprojekt_id' );
$t_person_name = trim( $t_person['nachname'] . ', ' . $t_person['vorname'], ', ' );

layout_page_header( plugin_lang_get( 'generate_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_zielprojekt <= 0 ) { ?>
	<div class="alert alert-danger"><?php echo plugin_lang_get( 'generate_no_zielprojekt' ); ?></div>
<?php } ?>

<form action="<?php echo plugin_page( 'generate' ); ?>" method="post">
<?php echo form_security_field( 'plugin_QualificationTracker_generate' ); ?>
<input type="hidden" name="action" value="generate" />
<input type="hidden" name="person_id" value="<?php echo (int)$f_person; ?>" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-cogs"></i>
			<?php echo plugin_lang_get( 'generate_title' ); ?>: <?php echo string_display_line( $t_person_name ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8">
		<span class="help-block" style="margin:0"><?php echo plugin_lang_get( 'generate_intro' ); ?></span>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_schluessel' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_bezeichnung' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_typ' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_modus' ); ?></th>
				<th><?php echo plugin_lang_get( 'label_soll_termin' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'generate_col_action' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_plan as $t_item ) { $t_m = $t_item['massnahme']; ?>
			<tr>
				<td><?php echo string_display_line( $t_m['schluessel'] ); ?></td>
				<td><?php echo string_display_line( $t_m['bezeichnung'] ); ?></td>
				<td><?php echo string_display_line( plugin_lang_get( 'type_' . $t_m['typ'] ) ); ?></td>
				<td><?php echo string_display_line( plugin_lang_get( 'mode_' . $t_m['faelligkeitsmodus'] ) ); ?></td>
				<td><?php echo $t_item['soll_termin'] === null ? '&ndash;' : string_display_line( $t_item['soll_termin'] ); ?></td>
				<td class="center">
					<?php if( $t_item['action'] === 'create' ) { ?>
						<span class="label label-success"><?php echo plugin_lang_get( 'generate_action_create' ); ?></span>
					<?php } else { ?>
						<span class="label label-default"><?php echo plugin_lang_get( 'generate_action_skip' ); ?></span>
					<?php } ?>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_plan ) ) { ?>
			<tr><td colspan="6" class="center"><?php echo plugin_lang_get( 'generate_nothing' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			<?php echo $t_zielprojekt <= 0 ? 'disabled="disabled"' : ''; ?>
			value="<?php echo string_attribute( plugin_lang_get( 'btn_generate' ) ); ?>" />
		<a class="btn btn-white btn-round" href="<?php echo plugin_page( 'zuordnung' ); ?>">
			<?php echo plugin_lang_get( 'btn_cancel' ); ?>
		</a>
	</div>
	</div>
</div>
</form>
</div>

<?php
layout_page_end();
