<?php
/**
 * QualificationTracker – profile-assignment list (F2.2).
 *
 * Reachable via Manage → QualificationTracker → Assignments. Lists person↔profile
 * assignments with their validity period, optionally filtered by person.
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

plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Assignment.php' );

$f_person = gpc_get_int( 'person_id', 0 );
$t_msg    = gpc_get_string( 'msg', '' );

$t_zuordnungen = qt_zuordnung_load_all( $f_person );
$t_personen    = qt_person_load_all();

layout_page_header( plugin_lang_get( 'zuordnung_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg === 'deleted' ) { ?>
	<div class="alert alert-success"><?php echo plugin_lang_get( 'zuordnung_msg_deleted' ); ?></div>
<?php } else if( $t_msg === 'generated' ) {
	$t_e = gpc_get_int( 'e', 0 );
?>
	<div class="alert <?php echo $t_e > 0 ? 'alert-warning' : 'alert-success'; ?>">
		<?php echo plugin_lang_get( 'generate_msg' ) . ' '
			. gpc_get_int( 'c', 0 ) . ' ' . plugin_lang_get( 'generate_created' ) . ', '
			. gpc_get_int( 's', 0 ) . ' ' . plugin_lang_get( 'generate_skipped' )
			. ( $t_e > 0 ? ' &mdash; ' . $t_e . ' ' . plugin_lang_get( 'import_errors' ) : '' ) . '.'; ?>
	</div>
<?php } else if( $t_msg === 'synced' ) {
	$t_e = gpc_get_int( 'e', 0 );
?>
	<div class="alert <?php echo $t_e > 0 ? 'alert-warning' : 'alert-success'; ?>">
		<?php echo plugin_lang_get( 'sync_msg' ) . ' '
			. gpc_get_int( 'en', 0 ) . ' ' . plugin_lang_get( 'sync_entfallen' ) . ', '
			. gpc_get_int( 'k', 0 ) . ' ' . plugin_lang_get( 'sync_kept' ) . ', '
			. gpc_get_int( 'c', 0 ) . ' ' . plugin_lang_get( 'generate_created' )
			. ( $t_e > 0 ? ' &mdash; ' . $t_e . ' ' . plugin_lang_get( 'import_errors' ) : '' ) . '.'; ?>
	</div>
<?php } ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-link"></i>
			<?php echo plugin_lang_get( 'zuordnung_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<a class="btn btn-primary btn-white btn-round btn-sm"
			href="<?php echo plugin_page( 'zuordnung_edit' ); ?>">
			<i class="ace-icon fa fa-plus"></i>
			<?php echo plugin_lang_get( 'btn_new_zuordnung' ); ?>
		</a>

		<form class="form-inline pull-right" method="get" action="<?php echo plugin_page( 'zuordnung' ); ?>">
			<input type="hidden" name="page" value="QualificationTracker/zuordnung" />
			<label><?php echo plugin_lang_get( 'filter_person' ); ?>&nbsp;</label>
			<select name="person_id" class="input-sm" onchange="this.form.submit()">
				<option value="0"><?php echo plugin_lang_get( 'filter_all' ); ?></option>
			<?php foreach( $t_personen as $t_p ) {
				$t_pid = (int)$t_p['id'];
				$t_label = trim( $t_p['nachname'] . ', ' . $t_p['vorname'], ', ' );
			?>
				<option value="<?php echo $t_pid; ?>" <?php echo $f_person === $t_pid ? 'selected="selected"' : ''; ?>>
					<?php echo string_display_line( $t_label ); ?>
				</option>
			<?php } ?>
			</select>
		</form>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_person' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_profil_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_gueltig_ab' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_gueltig_bis' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_aktionen' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_zuordnungen as $t_z ) {
			$t_id = (int)$t_z['id'];
			$t_person = trim( (string)$t_z['nachname'] . ', ' . (string)$t_z['vorname'], ', ' );
		?>
			<tr>
				<td><?php echo string_display_line( $t_person ); ?></td>
				<td><?php echo $t_z['personalnummer'] === null ? '&ndash;' : string_display_line( $t_z['personalnummer'] ); ?></td>
				<td><?php echo string_display_line( (string)$t_z['profil_name'] ); ?></td>
				<td><?php echo $t_z['gueltig_ab'] === null ? '&ndash;' : string_display_line( $t_z['gueltig_ab'] ); ?></td>
				<td><?php echo $t_z['gueltig_bis'] === null ? '&ndash;' : string_display_line( $t_z['gueltig_bis'] ); ?></td>
				<td class="center">
					<a class="btn btn-xs btn-success btn-white btn-round"
						href="<?php echo plugin_page( 'generate' ); ?>&amp;person_id=<?php echo (int)$t_z['person_id']; ?>">
						<i class="ace-icon fa fa-cogs"></i> <?php echo plugin_lang_get( 'btn_generate_chain' ); ?>
					</a>
					<a class="btn btn-xs btn-warning btn-white btn-round"
						href="<?php echo plugin_page( 'sync' ); ?>&amp;person_id=<?php echo (int)$t_z['person_id']; ?>">
						<i class="ace-icon fa fa-refresh"></i> <?php echo plugin_lang_get( 'btn_sync_chain' ); ?>
					</a>
					<a class="btn btn-xs btn-primary btn-white btn-round"
						href="<?php echo plugin_page( 'zuordnung_edit' ); ?>&amp;id=<?php echo $t_id; ?>">
						<i class="ace-icon fa fa-edit"></i> <?php echo plugin_lang_get( 'btn_edit' ); ?>
					</a>
					<form class="form-inline" style="display:inline"
						method="post" action="<?php echo plugin_page( 'zuordnung_delete' ); ?>"
						onsubmit="return confirm('<?php echo string_attribute( plugin_lang_get( 'confirm_delete_zuordnung' ) ); ?>');">
						<?php echo form_security_field( 'plugin_QualificationTracker_zuordnung_delete' ); ?>
						<input type="hidden" name="id" value="<?php echo $t_id; ?>" />
						<button type="submit" class="btn btn-xs btn-danger btn-white btn-round">
							<i class="ace-icon fa fa-trash-o"></i> <?php echo plugin_lang_get( 'btn_delete' ); ?>
						</button>
					</form>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_zuordnungen ) ) { ?>
			<tr><td colspan="6" class="center"><?php echo plugin_lang_get( 'no_zuordnungen' ); ?></td></tr>
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
