<?php
/**
 * QualificationTracker – automation overview (M5), expiry watchdog (F5.1).
 *
 * Shows the proofs whose validity has ended and lets a manager run the expiry
 * sweep manually. The nightly/CLI run (F5.5) calls the same qt_expiry_run().
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
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

plugin_require_api( 'core/QT_Expiry.php' );

$t_today   = date( 'Y-m-d' );
$t_preview = qt_expiry_find( $t_today );
$t_msg     = gpc_get_string( 'msg', '' );

layout_page_header( plugin_lang_get( 'menu_automatik' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg === 'expired' ) { ?>
	<div class="alert alert-success">
		<i class="ace-icon fa fa-check"></i>
		<?php echo sprintf( plugin_lang_get( 'watchdog_msg_done' ), gpc_get_int( 'count', 0 ) ); ?>
	</div>
<?php } ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-clock-o"></i>
			<?php echo plugin_lang_get( 'watchdog_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<span class="help-block pull-left" style="margin:4px 12px 0 0"><?php echo plugin_lang_get( 'watchdog_intro' ); ?></span>
		<form class="form-inline pull-right" method="post" action="<?php echo plugin_page( 'automatik_run' ); ?>">
			<?php echo form_security_field( 'plugin_QualificationTracker_automatik_run' ); ?>
			<button type="submit" class="btn btn-sm btn-primary btn-white btn-round"
				<?php echo empty( $t_preview ) ? 'disabled="disabled"' : ''; ?>>
				<i class="ace-icon fa fa-play"></i>
				<?php echo plugin_lang_get( 'watchdog_run' ); ?>
				<?php if( !empty( $t_preview ) ) { echo '(' . count( $t_preview ) . ')'; } ?>
			</button>
		</form>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
				<th><?php echo plugin_lang_get( 'label_event_massnahme' ); ?></th>
				<th><?php echo plugin_lang_get( 'export_gueltig_bis' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'teilnehmer_col_ticket' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_preview as $t_r ) { $t_bug = (int)$t_r['bug_id']; ?>
			<tr>
				<td><?php echo string_display_line( (string)$t_r['personalnummer'] ); ?></td>
				<td><?php echo string_display_line( trim( $t_r['nachname'] . ', ' . $t_r['vorname'], ', ' ) ); ?></td>
				<td><?php echo string_display_line( (string)$t_r['abteilung'] ); ?></td>
				<td><?php echo string_display_line( $t_r['schluessel'] . ' – ' . $t_r['bezeichnung'] ); ?></td>
				<td><?php echo string_display_line( (string)$t_r['gueltig_bis'] ); ?></td>
				<td class="center">
					<?php if( $t_bug > 0 ) { ?>
						<a href="<?php echo string_attribute( string_get_bug_view_url( $t_bug ) ); ?>"><?php echo bug_format_id( $t_bug ); ?></a>
					<?php } else { echo '&ndash;'; } ?>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_preview ) ) { ?>
			<tr><td colspan="6" class="center"><?php echo plugin_lang_get( 'watchdog_none' ); ?></td></tr>
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
