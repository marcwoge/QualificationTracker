<?php
/**
 * QualificationTracker – create / edit an activity profile (F2.1).
 *
 * Handles GET (show form) and POST (save). On a validation error the form is
 * re-rendered with the submitted values and the messages.
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
plugin_require_api( 'core/QT_Profile.php' );

$f_id     = gpc_get_int( 'id', 0 );
$f_action = gpc_get_string( 'action', '' );

$t_errors = array();
$t_data = array( 'name' => '', 'beschreibung' => '', 'aktiv' => 1 );
$t_selected = $f_id > 0 ? qt_profil_get_massnahmen( $f_id ) : array();

if( $f_action === 'save' ) {
	form_security_validate( 'plugin_QualificationTracker_profil_edit' );

	$t_data = array(
		'name'         => gpc_get_string( 'name', '' ),
		'beschreibung' => gpc_get_string( 'beschreibung', '' ),
		'aktiv'        => gpc_get_bool( 'aktiv', false ) ? 1 : 0,
	);
	$t_selected = gpc_get_int_array( 'massnahmen', array() );

	$t_errors = qt_profil_validate( $t_data );

	if( !in_array( 'error_profil_name_required', $t_errors, true )
		&& !in_array( 'error_profil_name_length', $t_errors, true )
		&& qt_profil_get_by_name( trim( $t_data['name'] ), $f_id ) !== false ) {
		$t_errors[] = 'error_profil_name_duplicate';
	}

	if( empty( $t_errors ) ) {
		if( $f_id > 0 ) {
			qt_profil_update( $f_id, $t_data );
			qt_profil_set_massnahmen( $f_id, $t_selected );
		} else {
			$t_new_id = qt_profil_create( $t_data );
			qt_profil_set_massnahmen( $t_new_id, $t_selected );
		}
		form_security_purge( 'plugin_QualificationTracker_profil_edit' );
		print_successful_redirect( plugin_page( 'profil', true ) );
		exit;
	}
} else if( $f_id > 0 ) {
	$t_row = qt_profil_get( $f_id );
	if( $t_row === false ) {
		error_parameters( $f_id );
		trigger_error( ERROR_GENERIC, ERROR );
	}
	$t_data = $t_row;
}

$t_candidates = qt_massnahme_load_all( true );
$t_title = $f_id > 0 ? plugin_lang_get( 'profil_form_edit_title' ) : plugin_lang_get( 'profil_form_new_title' );

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

<form action="<?php echo plugin_page( 'profil_edit' ); ?>" method="post">
<?php echo form_security_field( 'plugin_QualificationTracker_profil_edit' ); ?>
<input type="hidden" name="action" value="save" />
<input type="hidden" name="id" value="<?php echo (int)$f_id; ?>" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-id-badge"></i>
			<?php echo string_display_line( $t_title ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed">
		<tr>
			<th class="category" width="30%"><?php echo plugin_lang_get( 'label_profil_name' ); ?> <span class="required">*</span></th>
			<td><input type="text" name="name" maxlength="128" class="input-sm" style="width:100%"
				value="<?php echo string_attribute( (string)( $t_data['name'] ?? '' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_beschreibung' ); ?></th>
			<td><textarea name="beschreibung" rows="2" class="input-sm" style="width:100%"><?php
				echo string_textarea( (string)( $t_data['beschreibung'] ?? '' ) ); ?></textarea></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_profil_massnahmen' ); ?></th>
			<td>
			<?php if( empty( $t_candidates ) ) { ?>
				<span class="help-block" style="margin:0"><?php echo plugin_lang_get( 'no_massnahmen' ); ?></span>
			<?php } else { ?>
				<select name="massnahmen[]" multiple="multiple" size="10" class="input-sm" style="width:100%">
				<?php foreach( $t_candidates as $t_cand ) {
					$t_cand_id = (int)$t_cand['id'];
				?>
					<option value="<?php echo $t_cand_id; ?>" <?php echo in_array( $t_cand_id, $t_selected, true ) ? 'selected="selected"' : ''; ?>>
						<?php echo string_display_line( $t_cand['schluessel'] . ' — ' . $t_cand['bezeichnung'] ); ?>
					</option>
				<?php } ?>
				</select>
				<span class="help-block" style="margin:4px 0 0"><?php echo plugin_lang_get( 'help_profil_massnahmen' ); ?></span>
			<?php } ?>
			</td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_aktiv' ); ?></th>
			<td><label><input type="checkbox" name="aktiv" value="1" class="ace"
				<?php echo $t_data['aktiv'] ? 'checked="checked"' : ''; ?> /><span class="lbl">
				&nbsp;<?php echo plugin_lang_get( 'help_profil_aktiv' ); ?></span></label></td>
		</tr>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			value="<?php echo string_attribute( plugin_lang_get( 'btn_save' ) ); ?>" />
		<a class="btn btn-white btn-round" href="<?php echo plugin_page( 'profil' ); ?>">
			<?php echo plugin_lang_get( 'btn_cancel' ); ?>
		</a>
	</div>
	</div>
</div>
</form>
</div>

<?php
layout_page_end();
