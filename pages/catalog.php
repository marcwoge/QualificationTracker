<?php
/**
 * QualificationTracker – measure catalogue list (F1.2).
 *
 * Reachable via Manage → QualificationTracker. Lists every measure with edit and
 * delete actions and a button to create a new one.
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

$t_massnahmen = qt_massnahme_load_all( true );
$t_msg = gpc_get_string( 'msg', '' );

layout_page_header( plugin_lang_get( 'catalog_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg === 'referenced' ) { ?>
	<div class="alert alert-warning"><?php echo plugin_lang_get( 'msg_referenced' ); ?></div>
<?php } else if( $t_msg === 'deleted' ) { ?>
	<div class="alert alert-success"><?php echo plugin_lang_get( 'msg_deleted' ); ?></div>
<?php } ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-list-alt"></i>
			<?php echo plugin_lang_get( 'catalog_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<a class="btn btn-primary btn-white btn-round btn-sm"
			href="<?php echo plugin_page( 'catalog_edit' ); ?>">
			<i class="ace-icon fa fa-plus"></i>
			<?php echo plugin_lang_get( 'btn_new_massnahme' ); ?>
		</a>
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
				<th class="center"><?php echo plugin_lang_get( 'col_intervall' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_wiederkehrend' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_sicherheitsrelevant' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_aktiv' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_aktionen' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_massnahmen as $t_m ) {
			$t_id = (int)$t_m['id'];
		?>
			<tr>
				<td><?php echo string_display_line( $t_m['schluessel'] ); ?></td>
				<td><?php echo string_display_line( $t_m['bezeichnung'] ); ?></td>
				<td><?php echo string_display_line( plugin_lang_get( 'type_' . $t_m['typ'] ) ); ?></td>
				<td><?php echo string_display_line( plugin_lang_get( 'mode_' . $t_m['faelligkeitsmodus'] ) ); ?></td>
				<td class="center"><?php echo $t_m['intervall_monate'] === null ? '&ndash;' : (int)$t_m['intervall_monate']; ?></td>
				<td class="center"><?php echo $t_m['wiederkehrend'] ? '<i class="ace-icon fa fa-check green"></i>' : ''; ?></td>
				<td class="center"><?php echo $t_m['sicherheitsrelevant'] ? '<i class="ace-icon fa fa-shield red"></i>' : ''; ?></td>
				<td class="center"><?php echo $t_m['aktiv'] ? '<i class="ace-icon fa fa-check green"></i>' : '<i class="ace-icon fa fa-ban grey"></i>'; ?></td>
				<td class="center">
					<a class="btn btn-xs btn-primary btn-white btn-round"
						href="<?php echo plugin_page( 'catalog_edit' ); ?>&amp;id=<?php echo $t_id; ?>">
						<i class="ace-icon fa fa-edit"></i> <?php echo plugin_lang_get( 'btn_edit' ); ?>
					</a>
					<form class="form-inline" style="display:inline"
						method="post" action="<?php echo plugin_page( 'catalog_delete' ); ?>"
						onsubmit="return confirm('<?php echo string_attribute( plugin_lang_get( 'confirm_delete' ) ); ?>');">
						<?php echo form_security_field( 'plugin_QualificationTracker_catalog_delete' ); ?>
						<input type="hidden" name="id" value="<?php echo $t_id; ?>" />
						<button type="submit" class="btn btn-xs btn-danger btn-white btn-round">
							<i class="ace-icon fa fa-trash-o"></i> <?php echo plugin_lang_get( 'btn_delete' ); ?>
						</button>
					</form>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_massnahmen ) ) { ?>
			<tr><td colspan="9" class="center"><?php echo plugin_lang_get( 'no_massnahmen' ); ?></td></tr>
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
