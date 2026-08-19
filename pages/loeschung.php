<?php
/**
 * QualificationTracker – deletion proposal list & log (F7.3).
 *
 * Lists the finished proofs whose retention period has elapsed as of today and
 * lets an administrator confirm their deletion. Below it shows the append-only
 * deletion log so past erasures stay auditable. The actual deletion runs in
 * loeschung_do.php (POST-only, CSRF-protected).
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
require_api( 'user_api.php' );

auth_reauthenticate();
# Permanent deletion of records is an administrator task.
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'admin' );

plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Deletion.php' );

$t_today   = date( 'Y-m-d' );
$t_msg     = gpc_get_string( 'msg', '' );
$t_cands   = qt_loesch_candidates( $t_today );
$t_log     = qt_loesch_log_load( 100 );

layout_page_header( plugin_lang_get( 'loeschung_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg === 'deleted' ) { ?>
	<div class="alert alert-success">
		<i class="ace-icon fa fa-check"></i>
		<?php echo sprintf( plugin_lang_get( 'loeschung_msg_done' ), gpc_get_int( 'deleted', 0 ), gpc_get_int( 'skipped', 0 ) ); ?>
	</div>
<?php } ?>

<div class="widget-box widget-color-red">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-trash-o"></i>
			<?php echo plugin_lang_get( 'loeschung_proposal_title' ); ?>
			<span class="badge badge-important"><?php echo count( $t_cands ); ?></span>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main">
		<span class="help-block"><?php echo plugin_lang_get( 'loeschung_intro' ); ?></span>
	</div>

	<?php if( empty( $t_cands ) ) { ?>
		<div class="widget-main"><em><?php echo plugin_lang_get( 'loeschung_none' ); ?></em></div>
	<?php } else { ?>
	<form method="post" action="<?php echo plugin_page( 'loeschung_do' ); ?>"
		onsubmit="return confirm('<?php echo string_attribute( plugin_lang_get( 'loeschung_confirm' ) ); ?>');">
		<?php echo form_security_field( 'plugin_QualificationTracker_loeschung_do' ); ?>

		<div class="widget-main no-padding">
		<div class="table-responsive">
		<table class="table table-bordered table-condensed table-striped">
			<thead>
				<tr>
					<th class="center" width="24">
						<input type="checkbox" onclick="var b=this.checked;var e=this.form.elements['ids[]'];if(e){if(e.length){for(var i=0;i&lt;e.length;i++)e[i].checked=b;}else{e.checked=b;}}" />
					</th>
					<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
					<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
					<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
					<th><?php echo plugin_lang_get( 'label_event_massnahme' ); ?></th>
					<th><?php echo plugin_lang_get( 'event_col_status' ); ?></th>
					<th><?php echo plugin_lang_get( 'loeschung_col_anker' ); ?></th>
					<th><?php echo plugin_lang_get( 'loeschung_col_frist' ); ?></th>
					<th><?php echo plugin_lang_get( 'loeschung_col_faellig' ); ?></th>
					<th class="center"><?php echo plugin_lang_get( 'loeschung_col_ticket' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach( $t_cands as $t_c ) {
				$t_name = trim( (string)$t_c['nachname'] . ', ' . (string)$t_c['vorname'], ', ' );
			?>
				<tr>
					<td class="center"><input type="checkbox" name="ids[]" value="<?php echo (int)$t_c['id']; ?>" checked="checked" /></td>
					<td><?php echo string_display_line( (string)$t_c['personalnummer'] ); ?></td>
					<td><?php echo string_display_line( $t_name ); ?></td>
					<td><?php echo string_display_line( (string)$t_c['abteilung'] ); ?></td>
					<td><?php echo string_display_line( (string)$t_c['schluessel'] . ' – ' . (string)$t_c['bezeichnung'] ); ?></td>
					<td><?php echo string_display_line( plugin_lang_get( 'state_' . $t_c['status'] ) ); ?></td>
					<td><?php echo $t_c['anker'] === null ? '&ndash;' : string_display_line( (string)$t_c['anker'] ); ?></td>
					<td class="center"><?php echo (int)$t_c['aufbewahrung_monate']; ?></td>
					<td><?php echo string_display_line( (string)$t_c['loeschdatum'] ); ?></td>
					<td class="center"><?php
						$t_bug = (int)$t_c['bug_id'];
						echo $t_bug > 0 ? string_display_line( bug_format_id( $t_bug ) ) : '&ndash;';
					?></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
		</div>
		</div>

		<div class="widget-toolbox padding-8 clearfix">
			<div class="form-inline pull-left">
				<label><?php echo plugin_lang_get( 'loeschung_label_grund' ); ?></label>
				<input type="text" name="grund" class="input-sm" style="width:24em" maxlength="191"
					value="<?php echo string_attribute( plugin_lang_get( 'loeschung_grund_default' ) ); ?>" />
			</div>
			<button type="submit" class="btn btn-sm btn-danger btn-white btn-round pull-right">
				<i class="ace-icon fa fa-trash-o"></i> <?php echo plugin_lang_get( 'loeschung_execute' ); ?>
			</button>
		</div>
	</form>
	<?php } ?>
	</div>
</div>

<div class="widget-box widget-color-grey">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-history"></i>
			<?php echo plugin_lang_get( 'loeschung_log_title' ); ?>
		</h4>
	</div>
	<div class="widget-body"><div class="widget-main no-padding"><div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead><tr>
			<th><?php echo plugin_lang_get( 'loeschung_col_when' ); ?></th>
			<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
			<th><?php echo plugin_lang_get( 'label_event_massnahme' ); ?></th>
			<th><?php echo plugin_lang_get( 'loeschung_col_ticket' ); ?></th>
			<th><?php echo plugin_lang_get( 'label_gueltig_bis' ); ?></th>
			<th><?php echo plugin_lang_get( 'loeschung_col_grund' ); ?></th>
			<th><?php echo plugin_lang_get( 'loeschung_col_user' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if( empty( $t_log ) ) { ?>
			<tr><td colspan="7" class="center"><em><?php echo plugin_lang_get( 'loeschung_log_empty' ); ?></em></td></tr>
		<?php } else { foreach( $t_log as $t_l ) { ?>
			<tr>
				<td><?php echo string_display_line( date( 'Y-m-d H:i', (int)$t_l['date_created'] ) ); ?></td>
				<td><?php echo string_display_line( (string)$t_l['personalnummer'] ); ?></td>
				<td><?php echo string_display_line( (string)$t_l['massnahme_schluessel'] ); ?></td>
				<td><?php echo (int)$t_l['bug_id'] > 0 ? string_display_line( bug_format_id( (int)$t_l['bug_id'] ) ) : '&ndash;'; ?></td>
				<td><?php echo ( $t_l['gueltig_bis'] === null || $t_l['gueltig_bis'] === '' ) ? '&ndash;' : string_display_line( (string)$t_l['gueltig_bis'] ); ?></td>
				<td><?php echo string_display_line( (string)$t_l['grund'] ); ?></td>
				<td><?php echo string_display_line( user_get_name( (int)$t_l['user_id'] ) ); ?></td>
			</tr>
		<?php } } ?>
		</tbody>
	</table>
	</div></div></div>
</div>
</div>

<?php
layout_page_end();
