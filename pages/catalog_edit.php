<?php
/**
 * QualificationTracker – create / edit a measure (F1.2).
 *
 * Handles both the GET (show form) and POST (save) paths. On a validation error
 * the form is re-rendered with the submitted values and the error messages, so
 * nothing is lost.
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

$f_id     = gpc_get_int( 'id', 0 );
$f_action = gpc_get_string( 'action', '' );

$t_errors = array();

# Default values for a new measure.
$t_data = array(
	'schluessel'          => '',
	'bezeichnung'         => '',
	'typ'                 => 'UW',
	'faelligkeitsmodus'   => plugin_config_get( 'faelligkeitsmodus_default' ),
	'intervall_monate'    => '',
	'stichmonat'          => plugin_config_get( 'stichmonat_default' ),
	'karenz_tage'         => plugin_config_get( 'karenz_tage_default' ),
	'vorlaufzeit_tage'    => 0,
	'wiederkehrend'       => 1,
	'sicherheitsrelevant' => 0,
	'rechtsgrundlage'     => '',
	'nachweisart'         => '',
	'aktiv'               => 1,
);

if( $f_action === 'save' ) {
	form_security_validate( 'plugin_QualificationTracker_catalog_edit' );

	# Collect the submitted values.
	$t_data = array(
		'schluessel'          => gpc_get_string( 'schluessel', '' ),
		'bezeichnung'         => gpc_get_string( 'bezeichnung', '' ),
		'typ'                 => gpc_get_string( 'typ', '' ),
		'faelligkeitsmodus'   => gpc_get_string( 'faelligkeitsmodus', '' ),
		'intervall_monate'    => gpc_get_string( 'intervall_monate', '' ),
		'stichmonat'          => gpc_get_string( 'stichmonat', '' ),
		'karenz_tage'         => gpc_get_string( 'karenz_tage', '0' ),
		'vorlaufzeit_tage'    => gpc_get_string( 'vorlaufzeit_tage', '0' ),
		'wiederkehrend'       => gpc_get_bool( 'wiederkehrend', false ) ? 1 : 0,
		'sicherheitsrelevant' => gpc_get_bool( 'sicherheitsrelevant', false ) ? 1 : 0,
		'rechtsgrundlage'     => gpc_get_string( 'rechtsgrundlage', '' ),
		'nachweisart'         => gpc_get_string( 'nachweisart', '' ),
		'aktiv'               => gpc_get_bool( 'aktiv', false ) ? 1 : 0,
	);

	$t_errors = qt_massnahme_validate( $t_data );

	# Uniqueness of the key (needs the database, so checked here).
	if( !in_array( 'error_schluessel_required', $t_errors, true )
		&& !in_array( 'error_schluessel_length', $t_errors, true )
		&& qt_massnahme_get_by_schluessel( trim( $t_data['schluessel'] ), $f_id ) !== false ) {
		$t_errors[] = 'error_schluessel_duplicate';
	}

	if( empty( $t_errors ) ) {
		if( $f_id > 0 ) {
			qt_massnahme_update( $f_id, $t_data );
		} else {
			qt_massnahme_create( $t_data );
		}
		form_security_purge( 'plugin_QualificationTracker_catalog_edit' );
		print_successful_redirect( plugin_page( 'catalog', true ) );
		exit;
	}
} else if( $f_id > 0 ) {
	# Edit: load the existing row.
	$t_row = qt_massnahme_get( $f_id );
	if( $t_row === false ) {
		error_parameters( $f_id );
		trigger_error( ERROR_GENERIC, ERROR );
	}
	$t_data = $t_row;
}

$t_title = $f_id > 0 ? plugin_lang_get( 'form_edit_title' ) : plugin_lang_get( 'form_new_title' );

layout_page_header( $t_title );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( !empty( $t_errors ) ) { ?>
	<div class="alert alert-danger">
		<ul style="margin-bottom:0">
		<?php foreach( $t_errors as $t_error ) { ?>
			<li><?php echo string_display_line( plugin_lang_get( $t_error ) ); ?></li>
		<?php } ?>
		</ul>
	</div>
<?php } ?>

<form action="<?php echo plugin_page( 'catalog_edit' ); ?>" method="post">
<?php echo form_security_field( 'plugin_QualificationTracker_catalog_edit' ); ?>
<input type="hidden" name="action" value="save" />
<input type="hidden" name="id" value="<?php echo (int)$f_id; ?>" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-edit"></i>
			<?php echo string_display_line( $t_title ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed">
		<tr>
			<th class="category" width="30%"><?php echo plugin_lang_get( 'label_schluessel' ); ?> <span class="required">*</span></th>
			<td><input type="text" name="schluessel" maxlength="64" class="input-sm"
				value="<?php echo string_attribute( $t_data['schluessel'] ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_bezeichnung' ); ?> <span class="required">*</span></th>
			<td><input type="text" name="bezeichnung" maxlength="191" class="input-sm" style="width:100%"
				value="<?php echo string_attribute( $t_data['bezeichnung'] ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_typ' ); ?></th>
			<td>
				<select name="typ" class="input-sm">
				<?php foreach( qt_catalog_types() as $t_type ) { ?>
					<option value="<?php echo $t_type; ?>" <?php echo $t_data['typ'] === $t_type ? 'selected="selected"' : ''; ?>>
						<?php echo string_display_line( plugin_lang_get( 'type_' . $t_type ) ); ?>
					</option>
				<?php } ?>
				</select>
			</td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_modus' ); ?></th>
			<td>
				<select name="faelligkeitsmodus" id="qt-modus" class="input-sm">
				<?php foreach( qt_catalog_modes() as $t_mode ) { ?>
					<option value="<?php echo $t_mode; ?>" <?php echo $t_data['faelligkeitsmodus'] === $t_mode ? 'selected="selected"' : ''; ?>>
						<?php echo string_display_line( plugin_lang_get( 'mode_' . $t_mode ) ); ?>
					</option>
				<?php } ?>
				</select>
				<span class="help-block" style="margin:4px 0 0"><?php echo plugin_lang_get( 'help_modus' ); ?></span>
			</td>
		</tr>
		<tr class="qt-row-intervall">
			<th class="category"><?php echo plugin_lang_get( 'label_intervall' ); ?></th>
			<td><input type="number" min="1" max="600" name="intervall_monate" class="input-sm" style="width:8em"
				value="<?php echo $t_data['intervall_monate'] === null ? '' : string_attribute( $t_data['intervall_monate'] ); ?>" /></td>
		</tr>
		<tr class="qt-row-stichmonat">
			<th class="category"><?php echo plugin_lang_get( 'label_stichmonat' ); ?></th>
			<td><input type="number" min="1" max="12" name="stichmonat" class="input-sm" style="width:8em"
				value="<?php echo $t_data['stichmonat'] === null ? '' : string_attribute( $t_data['stichmonat'] ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_karenz' ); ?></th>
			<td><input type="number" min="0" name="karenz_tage" class="input-sm" style="width:8em"
				value="<?php echo string_attribute( $t_data['karenz_tage'] ); ?>" />
				<span class="help-block" style="margin:4px 0 0"><?php echo plugin_lang_get( 'help_karenz' ); ?></span></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_vorlauf' ); ?></th>
			<td><input type="number" min="0" name="vorlaufzeit_tage" class="input-sm" style="width:8em"
				value="<?php echo string_attribute( $t_data['vorlaufzeit_tage'] ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_wiederkehrend' ); ?></th>
			<td><label><input type="checkbox" name="wiederkehrend" value="1" class="ace"
				<?php echo $t_data['wiederkehrend'] ? 'checked="checked"' : ''; ?> /><span class="lbl">
				&nbsp;<?php echo plugin_lang_get( 'help_wiederkehrend' ); ?></span></label></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_sicherheitsrelevant' ); ?></th>
			<td><label><input type="checkbox" name="sicherheitsrelevant" value="1" class="ace"
				<?php echo $t_data['sicherheitsrelevant'] ? 'checked="checked"' : ''; ?> /><span class="lbl">
				&nbsp;<?php echo plugin_lang_get( 'help_sicherheitsrelevant' ); ?></span></label></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_rechtsgrundlage' ); ?></th>
			<td><input type="text" name="rechtsgrundlage" maxlength="191" class="input-sm" style="width:100%"
				value="<?php echo string_attribute( $t_data['rechtsgrundlage'] ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_nachweisart' ); ?></th>
			<td><input type="text" name="nachweisart" maxlength="64" class="input-sm" style="width:100%"
				value="<?php echo string_attribute( $t_data['nachweisart'] ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_aktiv' ); ?></th>
			<td><label><input type="checkbox" name="aktiv" value="1" class="ace"
				<?php echo $t_data['aktiv'] ? 'checked="checked"' : ''; ?> /><span class="lbl">
				&nbsp;<?php echo plugin_lang_get( 'help_aktiv' ); ?></span></label></td>
		</tr>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			value="<?php echo string_attribute( plugin_lang_get( 'btn_save' ) ); ?>" />
		<a class="btn btn-white btn-round" href="<?php echo plugin_page( 'catalog' ); ?>">
			<?php echo plugin_lang_get( 'btn_cancel' ); ?>
		</a>
	</div>
	</div>
</div>
</form>
</div>

<script type="text/javascript">
/* Show the interval only for computing modes, the reference month only for
   the "stichmonat" mode. */
(function () {
	var modus = document.getElementById( 'qt-modus' );
	function refresh() {
		var v = modus.value;
		var iv = document.querySelector( '.qt-row-intervall' );
		var sm = document.querySelector( '.qt-row-stichmonat' );
		if( iv ) { iv.style.display = ( v === 'extern' ) ? 'none' : ''; }
		if( sm ) { sm.style.display = ( v === 'stichmonat' ) ? '' : 'none'; }
	}
	if( modus ) {
		modus.addEventListener( 'change', refresh );
		refresh();
	}
})();
</script>

<?php
layout_page_end();
