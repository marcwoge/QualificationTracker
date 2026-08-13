<?php
/**
 * QualificationTracker – complete a single proof (F2.8).
 *
 * GET shows the completion form (performed date, validity for external
 * measures, instructor); POST records it, sets the proof valid and generates
 * the follow-up ticket for recurring measures.
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
plugin_require_api( 'core/QT_Completion.php' );

$f_id = gpc_get_int( 'nachweis_id', 0 );
$t_nw = qt_nachweis_get( $f_id );
if( $t_nw === false ) {
	error_parameters( $f_id );
	trigger_error( ERROR_GENERIC, ERROR );
}
$t_m = qt_massnahme_get( (int)$t_nw['massnahme_id'] );
$t_person = qt_person_get( (int)$t_nw['person_id'] );

if( gpc_get_string( 'action', '' ) === 'complete' ) {
	form_security_validate( 'plugin_QualificationTracker_complete' );
	$t_summary = qt_completion_complete(
		$f_id,
		gpc_get_string( 'durchgefuehrt_am', '' ),
		gpc_get_string( 'gueltig_bis', '' ),
		gpc_get_string( 'durchfuehrender', '' ) );
	form_security_purge( 'plugin_QualificationTracker_complete' );
	print_successful_redirect( plugin_page( 'nachweise', true )
		. '&msg=completed&f=' . (int)$t_summary['followup_created'] );
	exit;
}

$t_is_extern   = ( $t_m !== false && $t_m['faelligkeitsmodus'] === 'extern' );
$t_person_name = $t_person !== false ? trim( $t_person['nachname'] . ', ' . $t_person['vorname'], ', ' ) : '';
$t_today       = date( 'Y-m-d' );

layout_page_header( plugin_lang_get( 'complete_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<form action="<?php echo plugin_page( 'complete' ); ?>" method="post">
<?php echo form_security_field( 'plugin_QualificationTracker_complete' ); ?>
<input type="hidden" name="action" value="complete" />
<input type="hidden" name="nachweis_id" value="<?php echo (int)$f_id; ?>" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-check"></i>
			<?php echo plugin_lang_get( 'complete_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed">
		<tr>
			<th class="category" width="30%"><?php echo plugin_lang_get( 'col_person' ); ?></th>
			<td><?php echo string_display_line( $t_person_name ); ?></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'col_massnahme' ); ?></th>
			<td><?php echo $t_m !== false ? string_display_line( $t_m['schluessel'] . ' — ' . $t_m['bezeichnung'] ) : ''; ?></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_durchgefuehrt_am' ); ?> <span class="required">*</span></th>
			<td><input type="date" name="durchgefuehrt_am" class="input-sm"
				value="<?php echo string_attribute( $t_today ); ?>" /></td>
		</tr>
		<?php if( $t_is_extern ) { ?>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_gueltig_bis_extern' ); ?></th>
			<td><input type="date" name="gueltig_bis" class="input-sm" />
				<span class="help-block" style="margin:4px 0 0"><?php echo plugin_lang_get( 'help_gueltig_bis_extern' ); ?></span></td>
		</tr>
		<?php } else { ?>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_gueltig_bis' ); ?></th>
			<td><em><?php echo plugin_lang_get( 'complete_auto_bis' ); ?></em></td>
		</tr>
		<?php } ?>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_durchfuehrender' ); ?></th>
			<td><input type="text" name="durchfuehrender" maxlength="191" class="input-sm" style="width:100%" /></td>
		</tr>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			value="<?php echo string_attribute( plugin_lang_get( 'btn_complete_now' ) ); ?>" />
		<a class="btn btn-white btn-round" href="<?php echo plugin_page( 'nachweise' ); ?>">
			<?php echo plugin_lang_get( 'btn_cancel' ); ?>
		</a>
	</div>
	</div>
</div>
</form>
</div>

<?php
layout_page_end();
