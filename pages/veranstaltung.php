<?php
/**
 * QualificationTracker – group event list (F3.1).
 *
 * Reachable via Manage → QualificationTracker → Events. Lists group events with
 * their measure, date, location and capacity.
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
plugin_require_api( 'core/QT_Event.php' );

$t_events = qt_event_load_all();
$t_msg    = gpc_get_string( 'msg', '' );

layout_page_header( plugin_lang_get( 'event_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg === 'deleted' ) { ?>
	<div class="alert alert-success"><?php echo plugin_lang_get( 'event_msg_deleted' ); ?></div>
<?php } ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-calendar-check-o"></i>
			<?php echo plugin_lang_get( 'event_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<a class="btn btn-primary btn-white btn-round btn-sm"
			href="<?php echo plugin_page( 'veranstaltung_edit' ); ?>">
			<i class="ace-icon fa fa-plus"></i>
			<?php echo plugin_lang_get( 'btn_new_event' ); ?>
		</a>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'event_col_titel' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_massnahme' ); ?></th>
				<th><?php echo plugin_lang_get( 'event_col_termin' ); ?></th>
				<th><?php echo plugin_lang_get( 'event_col_ort' ); ?></th>
				<th><?php echo plugin_lang_get( 'event_col_unterweisender' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'event_col_kapazitaet' ); ?></th>
				<th><?php echo plugin_lang_get( 'event_col_status' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_aktionen' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_events as $t_e ) {
			$t_id = (int)$t_e['id'];
		?>
			<tr>
				<td><?php echo string_display_line( $t_e['titel'] ); ?></td>
				<td><?php echo string_display_line( (string)$t_e['schluessel'] ); ?></td>
				<td><?php echo string_display_line( substr( (string)$t_e['termin'], 0, 16 ) ); ?></td>
				<td><?php echo $t_e['ort'] === null ? '&ndash;' : string_display_line( $t_e['ort'] ); ?></td>
				<td><?php echo $t_e['unterweisender'] === null ? '&ndash;' : string_display_line( $t_e['unterweisender'] ); ?></td>
				<td class="center"><?php echo $t_e['kapazitaet'] === null ? '&ndash;' : (int)$t_e['kapazitaet']; ?></td>
				<td><?php echo string_display_line( plugin_lang_get( 'event_status_' . $t_e['status'] ) ); ?></td>
				<td class="center">
					<a class="btn btn-xs btn-primary btn-white btn-round"
						href="<?php echo plugin_page( 'veranstaltung_edit' ); ?>&amp;id=<?php echo $t_id; ?>">
						<i class="ace-icon fa fa-edit"></i> <?php echo plugin_lang_get( 'btn_edit' ); ?>
					</a>
					<form class="form-inline" style="display:inline"
						method="post" action="<?php echo plugin_page( 'veranstaltung_delete' ); ?>"
						onsubmit="return confirm('<?php echo string_attribute( plugin_lang_get( 'confirm_delete_event' ) ); ?>');">
						<?php echo form_security_field( 'plugin_QualificationTracker_veranstaltung_delete' ); ?>
						<input type="hidden" name="id" value="<?php echo $t_id; ?>" />
						<button type="submit" class="btn btn-xs btn-danger btn-white btn-round">
							<i class="ace-icon fa fa-trash-o"></i> <?php echo plugin_lang_get( 'btn_delete' ); ?>
						</button>
					</form>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_events ) ) { ?>
			<tr><td colspan="8" class="center"><?php echo plugin_lang_get( 'event_none' ); ?></td></tr>
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
