<?php
/**
 * QualificationTracker – historical proof CSV import (F6.2).
 *
 * Upload a semicolon-separated CSV (header row) to create historical proofs as
 * tickets in the target status. A dry-run validates without writing. The result
 * lists created/skipped counts and per-row errors.
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
require_api( 'string_api.php' );

auth_reauthenticate();
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'edit' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_CustomFields.php' );
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_ImportPersonen.php' );
plugin_require_api( 'core/QT_ImportNachweise.php' );

$t_result  = null;
$t_dry_run = false;
$t_error   = '';

if( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	form_security_validate( 'plugin_QualificationTracker_import_nachweise' );

	$t_dry_run = gpc_get_bool( 'dry_run', false );
	$f_file    = gpc_get_file( 'csv', null );

	if( !is_array( $f_file ) || !isset( $f_file['error'] ) || $f_file['error'] !== UPLOAD_ERR_OK
		|| !isset( $f_file['tmp_name'] ) || $f_file['tmp_name'] === '' ) {
		$t_error = plugin_lang_get( 'import_no_file' );
	} else {
		$t_text = file_get_contents( $f_file['tmp_name'] );
		$t_rows = qt_import_personen_parse( $t_text, ';' );
		$t_mapped = array();
		foreach( $t_rows as $t_row ) {
			$t_mapped[] = qt_import_nachweise_map_row( $t_row );
		}
		$t_result = qt_import_nachweise_run( $t_mapped, $t_dry_run, date( 'Y-m-d' ) );
	}

	form_security_purge( 'plugin_QualificationTracker_import_nachweise' );
}

layout_page_header( plugin_lang_get( 'menu_import_nachweise' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_error !== '' ) { ?>
	<div class="alert alert-warning"><?php echo string_display_line( $t_error ); ?></div>
<?php } ?>

<?php if( $t_result !== null ) { ?>
	<div class="alert alert-<?php echo empty( $t_result['errors'] ) ? 'success' : 'warning'; ?>">
		<i class="ace-icon fa fa-<?php echo $t_dry_run ? 'search' : 'check'; ?>"></i>
		<?php echo sprintf( plugin_lang_get( $t_dry_run ? 'import_nachweise_msg_dryrun' : 'import_nachweise_msg_done' ),
			(int)$t_result['created'], (int)$t_result['skipped'], count( $t_result['errors'] ) ); ?>
	</div>
	<?php if( !empty( $t_result['errors'] ) ) { ?>
	<div class="widget-box widget-color-red">
		<div class="widget-header widget-header-small"><h4 class="widget-title lighter">
			<i class="ace-icon fa fa-exclamation-triangle"></i> <?php echo plugin_lang_get( 'import_errors_title' ); ?>
		</h4></div>
		<div class="widget-body"><div class="widget-main no-padding"><div class="table-responsive">
		<table class="table table-bordered table-condensed table-striped">
			<thead><tr>
				<th><?php echo plugin_lang_get( 'import_col_row' ); ?></th>
				<th><?php echo plugin_lang_get( 'import_col_ref' ); ?></th>
				<th><?php echo plugin_lang_get( 'import_col_errors' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach( $t_result['errors'] as $t_e ) { ?>
				<tr>
					<td><?php echo (int)$t_e['zeile']; ?></td>
					<td><?php echo string_display_line( (string)$t_e['ref'] ); ?></td>
					<td><?php
						$t_msgs = array();
						foreach( $t_e['fehler'] as $t_key ) { $t_msgs[] = plugin_lang_get( $t_key ); }
						echo string_display_line( implode( '; ', $t_msgs ) );
					?></td>
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
			<i class="ace-icon fa fa-upload"></i>
			<?php echo plugin_lang_get( 'import_nachweise_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main">
		<span class="help-block"><?php echo plugin_lang_get( 'import_nachweise_intro' ); ?></span>
		<p><code><?php echo string_display_line( implode( ';', qt_import_nachweise_columns() ) ); ?></code></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo plugin_page( 'import_nachweise' ); ?>">
			<?php echo form_security_field( 'plugin_QualificationTracker_import_nachweise' ); ?>
			<div class="form-group">
				<input type="file" name="csv" accept=".csv,text/csv" required="required" />
			</div>
			<div class="checkbox">
				<label><input type="checkbox" name="dry_run" value="1" checked="checked" /> <?php echo plugin_lang_get( 'import_dry_run' ); ?></label>
			</div>
			<button type="submit" class="btn btn-primary btn-white btn-round">
				<i class="ace-icon fa fa-upload"></i> <?php echo plugin_lang_get( 'import_submit' ); ?>
			</button>
		</form>
	</div>
	</div>
</div>
</div>

<?php
layout_page_end();
