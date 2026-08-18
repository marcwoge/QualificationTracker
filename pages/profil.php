<?php
/**
 * QualificationTracker – activity-profile list (F2.1).
 *
 * Reachable via Manage → QualificationTracker → Profiles. Lists profiles with
 * their assigned measures and edit/delete actions.
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
plugin_require_api( 'core/QT_Profile.php' );

$t_profile = qt_profil_load_all( true );
$t_msg     = gpc_get_string( 'msg', '' );

# Map measure id -> key, for the assigned-measures column.
$t_key_by_id = array();
foreach( qt_massnahme_load_all( true ) as $t_m ) {
	$t_key_by_id[(int)$t_m['id']] = $t_m['schluessel'];
}

layout_page_header( plugin_lang_get( 'profil_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg === 'referenced' ) { ?>
	<div class="alert alert-warning"><?php echo plugin_lang_get( 'profil_msg_referenced' ); ?></div>
<?php } else if( $t_msg === 'deleted' ) { ?>
	<div class="alert alert-success"><?php echo plugin_lang_get( 'profil_msg_deleted' ); ?></div>
<?php } ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-id-badge"></i>
			<?php echo plugin_lang_get( 'profil_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<a class="btn btn-primary btn-white btn-round btn-sm"
			href="<?php echo plugin_page( 'profil_edit' ); ?>">
			<i class="ace-icon fa fa-plus"></i>
			<?php echo plugin_lang_get( 'btn_new_profil' ); ?>
		</a>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_profil_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_beschreibung' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_profil_massnahmen' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_aktiv' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_aktionen' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_profile as $t_p ) {
			$t_id = (int)$t_p['id'];
			$t_keys = array();
			foreach( qt_profil_get_massnahmen( $t_id ) as $t_mid ) {
				$t_keys[] = isset( $t_key_by_id[$t_mid] ) ? $t_key_by_id[$t_mid] : ( '#' . $t_mid );
			}
		?>
			<tr>
				<td><?php echo string_display_line( $t_p['name'] ); ?></td>
				<td><?php echo string_display_line( $t_p['beschreibung'] ); ?></td>
				<td>
					<span class="badge"><?php echo count( $t_keys ); ?></span>
					<?php echo empty( $t_keys ) ? '' : ' ' . string_display_line( implode( ', ', $t_keys ) ); ?>
				</td>
				<td class="center"><?php echo $t_p['aktiv'] ? '<i class="ace-icon fa fa-check green"></i>' : '<i class="ace-icon fa fa-ban grey"></i>'; ?></td>
				<td class="center">
					<a class="btn btn-xs btn-primary btn-white btn-round"
						href="<?php echo plugin_page( 'profil_edit' ); ?>&amp;id=<?php echo $t_id; ?>">
						<i class="ace-icon fa fa-edit"></i> <?php echo plugin_lang_get( 'btn_edit' ); ?>
					</a>
					<form class="form-inline" style="display:inline"
						method="post" action="<?php echo plugin_page( 'profil_delete' ); ?>"
						onsubmit="return confirm('<?php echo string_attribute( plugin_lang_get( 'confirm_delete_profil' ) ); ?>');">
						<?php echo form_security_field( 'plugin_QualificationTracker_profil_delete' ); ?>
						<input type="hidden" name="id" value="<?php echo $t_id; ?>" />
						<button type="submit" class="btn btn-xs btn-danger btn-white btn-round">
							<i class="ace-icon fa fa-trash-o"></i> <?php echo plugin_lang_get( 'btn_delete' ); ?>
						</button>
					</form>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_profile ) ) { ?>
			<tr><td colspan="5" class="center"><?php echo plugin_lang_get( 'no_profile' ); ?></td></tr>
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
