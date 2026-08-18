<?php
/**
 * QualificationTracker – create / edit a group event (F3.1).
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
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'edit' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Event.php' );

$f_id     = gpc_get_int( 'id', 0 );
$f_action = gpc_get_string( 'action', '' );

$t_errors = array();
$t_data = array(
	'massnahme_id'   => 0,
	'titel'          => '',
	'termin'         => '',
	'ort'            => '',
	'unterweisender' => '',
	'kapazitaet'     => '',
	'status'         => 'geplant',
);

if( $f_action === 'save' ) {
	form_security_validate( 'plugin_QualificationTracker_veranstaltung_edit' );

	$t_data = array(
		'massnahme_id'   => gpc_get_int( 'massnahme_id', 0 ),
		'titel'          => gpc_get_string( 'titel', '' ),
		'termin'         => gpc_get_string( 'termin', '' ),
		'ort'            => gpc_get_string( 'ort', '' ),
		'unterweisender' => gpc_get_string( 'unterweisender', '' ),
		'kapazitaet'     => gpc_get_string( 'kapazitaet', '' ),
		'status'         => gpc_get_string( 'status', 'geplant' ),
	);

	$t_errors = qt_event_validate( $t_data );

	if( $t_data['massnahme_id'] > 0 && qt_massnahme_get( $t_data['massnahme_id'] ) === false ) {
		$t_errors[] = 'error_event_massnahme_required';
	}

	if( empty( $t_errors ) ) {
		if( $f_id > 0 ) {
			qt_event_update( $f_id, $t_data );
		} else {
			qt_event_create( $t_data );
		}
		form_security_purge( 'plugin_QualificationTracker_veranstaltung_edit' );
		print_successful_redirect( plugin_page( 'veranstaltung', true ) );
		exit;
	}
} else if( $f_id > 0 ) {
	$t_row = qt_event_get( $f_id );
	if( $t_row === false ) {
		error_parameters( $f_id );
		trigger_error( ERROR_GENERIC, ERROR );
	}
	$t_data = $t_row;
	# Present the datetime in the input's expected "YYYY-MM-DDTHH:MM" form.
	$t_data['termin'] = str_replace( ' ', 'T', substr( (string)$t_row['termin'], 0, 16 ) );
}

$t_candidates = qt_massnahme_load_all( true );
$t_val = function( $p_key ) use ( $t_data ) {
	return (string)( $t_data[$p_key] ?? '' );
};
$t_title = $f_id > 0 ? plugin_lang_get( 'event_form_edit_title' ) : plugin_lang_get( 'event_form_new_title' );

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

<form action="<?php echo plugin_page( 'veranstaltung_edit' ); ?>" method="post">
<?php echo form_security_field( 'plugin_QualificationTracker_veranstaltung_edit' ); ?>
<input type="hidden" name="action" value="save" />
<input type="hidden" name="id" value="<?php echo (int)$f_id; ?>" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-calendar-check-o"></i>
			<?php echo string_display_line( $t_title ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed">
		<tr>
			<th class="category" width="30%"><?php echo plugin_lang_get( 'label_event_massnahme' ); ?> <span class="required">*</span></th>
			<td>
				<select name="massnahme_id" class="input-sm" style="width:100%">
					<option value="0"><?php echo plugin_lang_get( 'none' ); ?></option>
				<?php foreach( $t_candidates as $t_cand ) {
					$t_cid = (int)$t_cand['id'];
				?>
					<option value="<?php echo $t_cid; ?>" <?php echo (int)$t_data['massnahme_id'] === $t_cid ? 'selected="selected"' : ''; ?>>
						<?php echo string_display_line( $t_cand['schluessel'] . ' — ' . $t_cand['bezeichnung'] ); ?>
					</option>
				<?php } ?>
				</select>
			</td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'event_col_titel' ); ?> <span class="required">*</span></th>
			<td><input type="text" name="titel" maxlength="191" class="input-sm" style="width:100%"
				value="<?php echo string_attribute( $t_val( 'titel' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'event_col_termin' ); ?> <span class="required">*</span></th>
			<td><input type="datetime-local" name="termin" class="input-sm"
				value="<?php echo string_attribute( $t_val( 'termin' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'event_col_ort' ); ?></th>
			<td><input type="text" name="ort" maxlength="191" class="input-sm" style="width:100%"
				value="<?php echo string_attribute( $t_val( 'ort' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'event_col_unterweisender' ); ?></th>
			<td><input type="text" name="unterweisender" maxlength="191" class="input-sm" style="width:100%"
				value="<?php echo string_attribute( $t_val( 'unterweisender' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'event_col_kapazitaet' ); ?></th>
			<td><input type="number" min="0" name="kapazitaet" class="input-sm" style="width:8em"
				value="<?php echo string_attribute( $t_val( 'kapazitaet' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'event_col_status' ); ?></th>
			<td>
				<select name="status" class="input-sm">
				<?php foreach( qt_event_statuses() as $t_status ) { ?>
					<option value="<?php echo $t_status; ?>" <?php echo $t_data['status'] === $t_status ? 'selected="selected"' : ''; ?>>
						<?php echo string_display_line( plugin_lang_get( 'event_status_' . $t_status ) ); ?>
					</option>
				<?php } ?>
				</select>
			</td>
		</tr>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			value="<?php echo string_attribute( plugin_lang_get( 'btn_save' ) ); ?>" />
		<a class="btn btn-white btn-round" href="<?php echo plugin_page( 'veranstaltung' ); ?>">
			<?php echo plugin_lang_get( 'btn_cancel' ); ?>
		</a>
	</div>
	</div>
</div>
</form>
</div>

<?php
layout_page_end();
