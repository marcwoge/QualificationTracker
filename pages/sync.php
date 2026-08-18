<?php
/**
 * QualificationTracker – profile-change sync for a person (F2.7).
 *
 * GET previews which proofs would be cancelled (obsolete, not valid), which are
 * kept (obsolete but valid), and which new tickets would be created; POST
 * applies it.
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
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'manage' );

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

$t_today = date( 'Y-m-d' );

if( gpc_get_string( 'action', '' ) === 'sync' ) {
	form_security_validate( 'plugin_QualificationTracker_sync' );
	$t_summary = qt_generator_sync_person( $f_person, $t_today );
	form_security_purge( 'plugin_QualificationTracker_sync' );
	print_successful_redirect( plugin_page( 'zuordnung', true )
		. '&msg=synced'
		. '&en=' . (int)$t_summary['entfallen']
		. '&k=' . (int)$t_summary['kept']
		. '&c=' . (int)$t_summary['created']
		. '&e=' . count( $t_summary['errors'] ) );
	exit;
}

$t_preview     = qt_generator_sync_preview( $t_person, $t_today );
$t_person_name = trim( $t_person['nachname'] . ', ' . $t_person['vorname'], ', ' );
$t_zielprojekt = (int)plugin_config_get( 'zielprojekt_id' );

layout_page_header( plugin_lang_get( 'sync_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_zielprojekt <= 0 ) { ?>
	<div class="alert alert-danger"><?php echo plugin_lang_get( 'generate_no_zielprojekt' ); ?></div>
<?php } ?>

<form action="<?php echo plugin_page( 'sync' ); ?>" method="post">
<?php echo form_security_field( 'plugin_QualificationTracker_sync' ); ?>
<input type="hidden" name="action" value="sync" />
<input type="hidden" name="person_id" value="<?php echo (int)$f_person; ?>" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-refresh"></i>
			<?php echo plugin_lang_get( 'sync_title' ); ?>: <?php echo string_display_line( $t_person_name ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8">
		<span class="help-block" style="margin:0"><?php echo plugin_lang_get( 'sync_intro' ); ?></span>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed">
		<thead>
			<tr><th colspan="4"><strong><?php echo plugin_lang_get( 'sync_section_obsolete' ); ?></strong></th></tr>
			<tr>
				<th><?php echo plugin_lang_get( 'col_schluessel' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_bezeichnung' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_typ' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'generate_col_action' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_preview['obsolete'] as $t_o ) {
			$t_m = $t_o['massnahme'];
			if( $t_m === false ) { continue; }
		?>
			<tr>
				<td><?php echo string_display_line( $t_m['schluessel'] ); ?></td>
				<td><?php echo string_display_line( $t_m['bezeichnung'] ); ?></td>
				<td><?php echo string_display_line( plugin_lang_get( 'type_' . $t_m['typ'] ) ); ?></td>
				<td class="center">
					<?php if( $t_o['action'] === 'behalten' ) { ?>
						<span class="label label-success"><?php echo plugin_lang_get( 'sync_action_behalten' ); ?></span>
					<?php } else { ?>
						<span class="label label-warning"><?php echo plugin_lang_get( 'sync_action_entfallen' ); ?></span>
					<?php } ?>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_preview['obsolete'] ) ) { ?>
			<tr><td colspan="4" class="center"><?php echo plugin_lang_get( 'sync_no_obsolete' ); ?></td></tr>
		<?php } ?>
		</tbody>
		<thead>
			<tr><th colspan="4"><strong><?php echo plugin_lang_get( 'sync_section_new' ); ?></strong></th></tr>
			<tr>
				<th><?php echo plugin_lang_get( 'col_schluessel' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_bezeichnung' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_typ' ); ?></th>
				<th><?php echo plugin_lang_get( 'label_soll_termin' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_preview['new'] as $t_item ) { $t_m = $t_item['massnahme']; ?>
			<tr>
				<td><?php echo string_display_line( $t_m['schluessel'] ); ?></td>
				<td><?php echo string_display_line( $t_m['bezeichnung'] ); ?></td>
				<td><?php echo string_display_line( plugin_lang_get( 'type_' . $t_m['typ'] ) ); ?></td>
				<td><?php echo $t_item['soll_termin'] === null ? '&ndash;' : string_display_line( $t_item['soll_termin'] ); ?></td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_preview['new'] ) ) { ?>
			<tr><td colspan="4" class="center"><?php echo plugin_lang_get( 'sync_no_new' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			<?php echo $t_zielprojekt <= 0 ? 'disabled="disabled"' : ''; ?>
			value="<?php echo string_attribute( plugin_lang_get( 'btn_sync_apply' ) ); ?>" />
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
