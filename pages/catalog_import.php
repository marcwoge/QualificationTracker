<?php
/**
 * QualificationTracker – import the bundled example catalogue (F1.7).
 *
 * GET shows a preview of the bundled catalogue with a per-measure status
 * (new / already present); POST imports it, optionally overwriting existing
 * measures.
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
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_CatalogImport.php' );

$t_path = qt_catalog_seed_path();
$t_content = is_readable( $t_path ) ? file_get_contents( $t_path ) : false;

if( $t_content === false ) {
	layout_page_header( plugin_lang_get( 'import_title' ) );
	layout_page_begin();
	echo '<div class="col-md-12 col-xs-12"><div class="space-10"></div>'
		. '<div class="alert alert-danger">' . plugin_lang_get( 'import_file_missing' ) . '</div></div>';
	layout_page_end();
	exit;
}

$t_rows = qt_yaml_parse_simple( $t_content );

if( gpc_get_string( 'action', '' ) === 'import' ) {
	form_security_validate( 'plugin_QualificationTracker_catalog_import' );
	$t_overwrite = gpc_get_bool( 'overwrite', false );
	$t_summary = qt_catalog_import( $t_rows, $t_overwrite );
	form_security_purge( 'plugin_QualificationTracker_catalog_import' );
	print_successful_redirect( plugin_page( 'catalog', true )
		. '&msg=imported'
		. '&c=' . (int)$t_summary['created']
		. '&u=' . (int)$t_summary['updated']
		. '&s=' . (int)$t_summary['skipped']
		. '&e=' . count( $t_summary['errors'] ) );
	exit;
}

layout_page_header( plugin_lang_get( 'import_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<form action="<?php echo plugin_page( 'catalog_import' ); ?>" method="post">
<?php echo form_security_field( 'plugin_QualificationTracker_catalog_import' ); ?>
<input type="hidden" name="action" value="import" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-download"></i>
			<?php echo plugin_lang_get( 'import_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8">
		<span class="help-block" style="margin:0"><?php echo plugin_lang_get( 'import_intro' ); ?></span>
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
				<th><?php echo plugin_lang_get( 'col_vorbedingungen' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'import_col_status' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_rows as $t_row ) {
			$t_key    = isset( $t_row['schluessel'] ) ? (string)$t_row['schluessel'] : '';
			$t_typ    = isset( $t_row['typ'] ) ? (string)$t_row['typ'] : '';
			$t_modus  = isset( $t_row['faelligkeitsmodus'] ) ? (string)$t_row['faelligkeitsmodus'] : '';
			$t_exists = $t_key !== '' && qt_massnahme_get_by_schluessel( $t_key, 0 ) !== false;
		?>
			<tr>
				<td><?php echo string_display_line( $t_key ); ?></td>
				<td><?php echo string_display_line( isset( $t_row['bezeichnung'] ) ? (string)$t_row['bezeichnung'] : '' ); ?></td>
				<td><?php echo $t_typ !== '' ? string_display_line( plugin_lang_get( 'type_' . $t_typ ) ) : ''; ?></td>
				<td><?php echo $t_modus !== '' ? string_display_line( plugin_lang_get( 'mode_' . $t_modus ) ) : ''; ?></td>
				<td><?php echo string_display_line( isset( $t_row['vorbedingungen'] ) ? (string)$t_row['vorbedingungen'] : '' ); ?></td>
				<td class="center">
					<?php if( $t_exists ) { ?>
						<span class="label label-warning"><?php echo plugin_lang_get( 'import_status_exists' ); ?></span>
					<?php } else { ?>
						<span class="label label-success"><?php echo plugin_lang_get( 'import_status_new' ); ?></span>
					<?php } ?>
				</td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<label style="margin-right:12px">
			<input type="checkbox" name="overwrite" value="1" class="ace" /><span class="lbl">
			&nbsp;<?php echo plugin_lang_get( 'import_overwrite' ); ?></span>
		</label>
		<input type="submit" class="btn btn-primary btn-white btn-round"
			value="<?php echo string_attribute( plugin_lang_get( 'btn_import_now' ) ); ?>" />
		<a class="btn btn-white btn-round" href="<?php echo plugin_page( 'catalog' ); ?>">
			<?php echo plugin_lang_get( 'btn_cancel' ); ?>
		</a>
	</div>
	</div>
</div>
</form>
</div>

<?php
layout_page_end();
