<?php
/**
 * QualificationTracker – Bordmittel migration page (F8.6).
 *
 * Lifts an existing native ("Bordmittel") setup into the plugin's data
 * structure: pick the source project, run a dry run to see what would be
 * created, then execute. Idempotent; the proof tickets themselves are not
 * touched (each migrated proof points back at its original ticket).
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
require_api( 'custom_field_api.php' );
require_api( 'database_api.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'project_api.php' );
require_api( 'string_api.php' );

auth_reauthenticate();
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'admin' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_Migration.php' );

$f_project = gpc_get_int( 'project_id', 0 );
$f_dry_run = true;
$t_result  = null;

if( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	form_security_validate( 'plugin_QualificationTracker_migration' );
	$f_project = gpc_get_int( 'project_id', 0 );
	$f_dry_run = gpc_get_bool( 'dry_run', false );
	if( $f_project > 0 ) {
		$t_result = qt_migrate_run( $f_project, $f_dry_run );
	}
	form_security_purge( 'plugin_QualificationTracker_migration' );
}

layout_page_header( plugin_lang_get( 'migration_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_result !== null ) { ?>
	<div class="alert alert-<?php echo empty( $t_result['errors'] ) ? 'success' : 'warning'; ?>">
		<i class="ace-icon fa fa-<?php echo $f_dry_run ? 'search' : 'check'; ?>"></i>
		<?php echo string_display_line( plugin_lang_get( $f_dry_run ? 'migration_msg_dryrun' : 'migration_msg_done' ) ); ?>
	</div>
	<div class="widget-box widget-color-blue">
		<div class="widget-body"><div class="widget-main no-padding"><div class="table-responsive">
		<table class="table table-bordered table-condensed">
			<tr><th><?php echo plugin_lang_get( 'migration_row_tickets' ); ?></th><td><?php echo (int)$t_result['tickets']; ?></td></tr>
			<tr><th><?php echo plugin_lang_get( 'migration_row_persons' ); ?></th><td><?php echo (int)$t_result['persons_created']; ?></td></tr>
			<tr><th><?php echo plugin_lang_get( 'migration_row_measures' ); ?></th><td><?php echo (int)$t_result['measures_created']; ?></td></tr>
			<tr><th><?php echo plugin_lang_get( 'migration_row_proofs' ); ?></th><td><?php echo (int)$t_result['proofs_created']; ?></td></tr>
			<tr><th><?php echo plugin_lang_get( 'migration_row_existing' ); ?></th><td><?php echo (int)$t_result['proofs_existing']; ?></td></tr>
			<tr><th><?php echo plugin_lang_get( 'migration_row_skipped' ); ?></th><td><?php echo (int)$t_result['skipped']; ?></td></tr>
			<tr><th><?php echo plugin_lang_get( 'migration_row_errors' ); ?></th><td><?php echo count( $t_result['errors'] ); ?></td></tr>
		</table>
		</div></div></div>
	</div>
	<?php if( !empty( $t_result['errors'] ) ) { ?>
	<div class="widget-box widget-color-red">
		<div class="widget-header widget-header-small"><h4 class="widget-title lighter">
			<i class="ace-icon fa fa-exclamation-triangle"></i> <?php echo plugin_lang_get( 'import_errors_title' ); ?>
		</h4></div>
		<div class="widget-body"><div class="widget-main no-padding"><div class="table-responsive">
		<table class="table table-bordered table-condensed table-striped">
			<thead><tr>
				<th><?php echo plugin_lang_get( 'loeschung_col_ticket' ); ?></th>
				<th><?php echo plugin_lang_get( 'import_col_errors' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach( $t_result['errors'] as $t_e ) { ?>
				<tr>
					<td><?php echo (int)$t_e['bug_id'] > 0 ? string_display_line( bug_format_id( (int)$t_e['bug_id'] ) ) : '&ndash;'; ?></td>
					<td><?php echo string_display_line( plugin_lang_get( $t_e['error'] ) ); ?></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
		</div></div></div>
	</div>
	<?php } ?>
<?php } ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-exchange"></i>
			<?php echo plugin_lang_get( 'migration_title' ); ?>
		</h4>
	</div>
	<div class="widget-body"><div class="widget-main">
		<span class="help-block"><?php echo plugin_lang_get( 'migration_intro' ); ?></span>
		<form method="post" action="<?php echo plugin_page( 'migration' ); ?>">
			<?php echo form_security_field( 'plugin_QualificationTracker_migration' ); ?>
			<div class="form-group">
				<label><?php echo plugin_lang_get( 'migration_label_project' ); ?></label>
				<select name="project_id" class="input-sm" style="min-width:20em">
					<option value="0">&ndash;</option>
					<?php print_project_option_list( $f_project, false ); ?>
				</select>
			</div>
			<div class="checkbox">
				<label><input type="checkbox" name="dry_run" value="1" <?php echo $f_dry_run ? 'checked="checked"' : ''; ?> /> <?php echo plugin_lang_get( 'import_dry_run' ); ?></label>
			</div>
			<button type="submit" class="btn btn-primary btn-white btn-round">
				<i class="ace-icon fa fa-exchange"></i> <?php echo plugin_lang_get( 'migration_run' ); ?>
			</button>
		</form>
	</div></div>
</div>
</div>

<?php
layout_page_end();
