<?php
/**
 * QualificationTracker – create / edit a person (F1.4).
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
require_api( 'user_api.php' );

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

plugin_require_api( 'core/QT_Person.php' );

$f_id     = gpc_get_int( 'id', 0 );
$f_action = gpc_get_string( 'action', '' );

$t_errors = array();

# Defaults for a new person.
$t_data = array(
	'personalnummer'            => '',
	'typ'                       => 'intern',
	'fremdfirma'                => '',
	'nachname'                  => '',
	'vorname'                   => '',
	'abteilung'                 => '',
	'eintritt'                  => '',
	'austritt'                  => '',
	'vorgesetzter_user_id'      => 0,
	'verkuerztes_intervall_bis' => '',
	'aktiv'                     => 1,
);

if( $f_action === 'save' ) {
	form_security_validate( 'plugin_QualificationTracker_person_edit' );

	$t_data = array(
		'personalnummer'            => gpc_get_string( 'personalnummer', '' ),
		'typ'                       => gpc_get_string( 'typ', '' ),
		'fremdfirma'                => gpc_get_string( 'fremdfirma', '' ),
		'nachname'                  => gpc_get_string( 'nachname', '' ),
		'vorname'                   => gpc_get_string( 'vorname', '' ),
		'abteilung'                 => gpc_get_string( 'abteilung', '' ),
		'eintritt'                  => gpc_get_string( 'eintritt', '' ),
		'austritt'                  => gpc_get_string( 'austritt', '' ),
		'vorgesetzter_user_id'      => gpc_get_int( 'vorgesetzter_user_id', 0 ),
		'verkuerztes_intervall_bis' => gpc_get_string( 'verkuerztes_intervall_bis', '' ),
		'aktiv'                     => gpc_get_bool( 'aktiv', false ) ? 1 : 0,
	);

	$t_errors = qt_person_validate( $t_data );

	# Uniqueness of the personnel number (only when one is given).
	$t_pnr = trim( $t_data['personalnummer'] );
	if( $t_pnr !== '' && !in_array( 'error_personalnummer_length', $t_errors, true )
		&& qt_person_get_by_personalnummer( $t_pnr, $f_id ) !== false ) {
		$t_errors[] = 'error_personalnummer_duplicate';
	}

	# The supervisor, if given, must be an existing user.
	if( $t_data['vorgesetzter_user_id'] > 0 && !user_exists( $t_data['vorgesetzter_user_id'] ) ) {
		$t_errors[] = 'error_vorgesetzter_invalid';
	}

	if( empty( $t_errors ) ) {
		if( $f_id > 0 ) {
			qt_person_update( $f_id, $t_data );
		} else {
			qt_person_create( $t_data );
		}
		form_security_purge( 'plugin_QualificationTracker_person_edit' );
		print_successful_redirect( plugin_page( 'person', true ) );
		exit;
	}
} else if( $f_id > 0 ) {
	$t_row = qt_person_get( $f_id );
	if( $t_row === false ) {
		error_parameters( $f_id );
		trigger_error( ERROR_GENERIC, ERROR );
	}
	$t_data = $t_row;
}

# Helper: nullable DB values render as empty strings.
$t_val = function( $p_key ) use ( $t_data ) {
	return (string)( $t_data[$p_key] ?? '' );
};

$t_title = $f_id > 0 ? plugin_lang_get( 'person_form_edit_title' ) : plugin_lang_get( 'person_form_new_title' );

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

<form action="<?php echo plugin_page( 'person_edit' ); ?>" method="post">
<?php echo form_security_field( 'plugin_QualificationTracker_person_edit' ); ?>
<input type="hidden" name="action" value="save" />
<input type="hidden" name="id" value="<?php echo (int)$f_id; ?>" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-user"></i>
			<?php echo string_display_line( $t_title ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed">
		<tr>
			<th class="category" width="30%"><?php echo plugin_lang_get( 'label_nachname' ); ?> <span class="required">*</span></th>
			<td><input type="text" name="nachname" maxlength="128" class="input-sm" style="width:100%"
				value="<?php echo string_attribute( $t_val( 'nachname' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_vorname' ); ?></th>
			<td><input type="text" name="vorname" maxlength="128" class="input-sm" style="width:100%"
				value="<?php echo string_attribute( $t_val( 'vorname' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_personalnummer' ); ?></th>
			<td><input type="text" name="personalnummer" maxlength="64" class="input-sm"
				value="<?php echo string_attribute( $t_val( 'personalnummer' ) ); ?>" />
				<span class="help-block" style="margin:4px 0 0"><?php echo plugin_lang_get( 'help_personalnummer' ); ?></span></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_person_typ' ); ?></th>
			<td>
				<select name="typ" id="qt-person-typ" class="input-sm">
				<?php foreach( qt_person_types() as $t_type ) { ?>
					<option value="<?php echo $t_type; ?>" <?php echo $t_data['typ'] === $t_type ? 'selected="selected"' : ''; ?>>
						<?php echo string_display_line( plugin_lang_get( 'person_typ_' . $t_type ) ); ?>
					</option>
				<?php } ?>
				</select>
			</td>
		</tr>
		<tr class="qt-row-fremdfirma">
			<th class="category"><?php echo plugin_lang_get( 'label_fremdfirma' ); ?></th>
			<td><input type="text" name="fremdfirma" maxlength="128" class="input-sm" style="width:100%"
				value="<?php echo string_attribute( $t_val( 'fremdfirma' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_abteilung' ); ?></th>
			<td><input type="text" name="abteilung" maxlength="128" class="input-sm" style="width:100%"
				value="<?php echo string_attribute( $t_val( 'abteilung' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_eintritt' ); ?></th>
			<td><input type="date" name="eintritt" class="input-sm"
				value="<?php echo string_attribute( $t_val( 'eintritt' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_austritt' ); ?></th>
			<td><input type="date" name="austritt" class="input-sm"
				value="<?php echo string_attribute( $t_val( 'austritt' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_vorgesetzter' ); ?></th>
			<td>
				<select name="vorgesetzter_user_id" class="input-sm">
					<option value="0"><?php echo plugin_lang_get( 'none' ); ?></option>
					<?php print_user_option_list( (int)( $t_data['vorgesetzter_user_id'] ?? 0 ) ); ?>
				</select>
				<span class="help-block" style="margin:4px 0 0"><?php echo plugin_lang_get( 'help_vorgesetzter' ); ?></span>
			</td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_jugendschutz' ); ?></th>
			<td><input type="date" name="verkuerztes_intervall_bis" class="input-sm"
				value="<?php echo string_attribute( $t_val( 'verkuerztes_intervall_bis' ) ); ?>" />
				<span class="help-block" style="margin:4px 0 0"><?php echo plugin_lang_get( 'help_jugendschutz' ); ?></span></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_aktiv' ); ?></th>
			<td><label><input type="checkbox" name="aktiv" value="1" class="ace"
				<?php echo $t_data['aktiv'] ? 'checked="checked"' : ''; ?> /><span class="lbl">
				&nbsp;<?php echo plugin_lang_get( 'help_person_aktiv' ); ?></span></label></td>
		</tr>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			value="<?php echo string_attribute( plugin_lang_get( 'btn_save' ) ); ?>" />
		<a class="btn btn-white btn-round" href="<?php echo plugin_page( 'person' ); ?>">
			<?php echo plugin_lang_get( 'btn_cancel' ); ?>
		</a>
	</div>
	</div>
</div>
</form>
</div>

<script type="text/javascript">
/* Show the employer field only for external staff (leiharbeit / fremdfirma). */
(function () {
	var typ = document.getElementById( 'qt-person-typ' );
	function refresh() {
		var row = document.querySelector( '.qt-row-fremdfirma' );
		if( row ) { row.style.display = ( typ.value === 'intern' ) ? 'none' : ''; }
	}
	if( typ ) {
		typ.addEventListener( 'change', refresh );
		refresh();
	}
})();
</script>

<?php
layout_page_end();
