<?php
/**
 * QualificationTracker – create / edit a profile assignment (F2.2).
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

plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Profile.php' );
plugin_require_api( 'core/QT_Assignment.php' );

$f_id     = gpc_get_int( 'id', 0 );
$f_action = gpc_get_string( 'action', '' );

$t_errors = array();
$t_data = array( 'person_id' => 0, 'profil_id' => 0, 'gueltig_ab' => '', 'gueltig_bis' => '' );

if( $f_action === 'save' ) {
	form_security_validate( 'plugin_QualificationTracker_zuordnung_edit' );

	$t_data = array(
		'person_id'   => gpc_get_int( 'person_id', 0 ),
		'profil_id'   => gpc_get_int( 'profil_id', 0 ),
		'gueltig_ab'  => gpc_get_string( 'gueltig_ab', '' ),
		'gueltig_bis' => gpc_get_string( 'gueltig_bis', '' ),
	);

	$t_errors = qt_zuordnung_validate( $t_data );

	# Referenced person and profile must exist.
	if( $t_data['person_id'] > 0 && qt_person_get( $t_data['person_id'] ) === false ) {
		$t_errors[] = 'error_zuordnung_person_required';
	}
	if( $t_data['profil_id'] > 0 && qt_profil_get( $t_data['profil_id'] ) === false ) {
		$t_errors[] = 'error_zuordnung_profil_required';
	}

	# Avoid a second open assignment of the same person to the same profile.
	if( !in_array( 'error_zuordnung_person_required', $t_errors, true )
		&& !in_array( 'error_zuordnung_profil_required', $t_errors, true )
		&& trim( $t_data['gueltig_bis'] ) === ''
		&& qt_zuordnung_open_exists( $t_data['person_id'], $t_data['profil_id'], $f_id ) ) {
		$t_errors[] = 'error_zuordnung_duplicate_open';
	}

	if( empty( $t_errors ) ) {
		if( $f_id > 0 ) {
			qt_zuordnung_update( $f_id, $t_data );
		} else {
			qt_zuordnung_create( $t_data );
		}
		form_security_purge( 'plugin_QualificationTracker_zuordnung_edit' );
		print_successful_redirect( plugin_page( 'zuordnung', true ) );
		exit;
	}
} else if( $f_id > 0 ) {
	$t_row = qt_zuordnung_get( $f_id );
	if( $t_row === false ) {
		error_parameters( $f_id );
		trigger_error( ERROR_GENERIC, ERROR );
	}
	$t_data = $t_row;
}

$t_personen = qt_person_load_all();
$t_profile  = qt_profil_load_all( false );
$t_val = function( $p_key ) use ( $t_data ) {
	return (string)( $t_data[$p_key] ?? '' );
};
$t_title = $f_id > 0 ? plugin_lang_get( 'zuordnung_form_edit_title' ) : plugin_lang_get( 'zuordnung_form_new_title' );

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

<form action="<?php echo plugin_page( 'zuordnung_edit' ); ?>" method="post">
<?php echo form_security_field( 'plugin_QualificationTracker_zuordnung_edit' ); ?>
<input type="hidden" name="action" value="save" />
<input type="hidden" name="id" value="<?php echo (int)$f_id; ?>" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-link"></i>
			<?php echo string_display_line( $t_title ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed">
		<tr>
			<th class="category" width="30%"><?php echo plugin_lang_get( 'label_person' ); ?> <span class="required">*</span></th>
			<td>
				<select name="person_id" class="input-sm" style="width:100%">
					<option value="0"><?php echo plugin_lang_get( 'none' ); ?></option>
				<?php foreach( $t_personen as $t_p ) {
					$t_pid = (int)$t_p['id'];
					$t_label = trim( $t_p['nachname'] . ', ' . $t_p['vorname'], ', ' );
					if( $t_p['personalnummer'] !== null && $t_p['personalnummer'] !== '' ) {
						$t_label .= ' (' . $t_p['personalnummer'] . ')';
					} else if( $t_p['abteilung'] !== '' ) {
						$t_label .= ' (' . $t_p['abteilung'] . ')';
					}
				?>
					<option value="<?php echo $t_pid; ?>" <?php echo (int)$t_data['person_id'] === $t_pid ? 'selected="selected"' : ''; ?>>
						<?php echo string_display_line( $t_label ); ?>
					</option>
				<?php } ?>
				</select>
			</td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_profil' ); ?> <span class="required">*</span></th>
			<td>
				<select name="profil_id" class="input-sm" style="width:100%">
					<option value="0"><?php echo plugin_lang_get( 'none' ); ?></option>
				<?php foreach( $t_profile as $t_pr ) {
					$t_prid = (int)$t_pr['id'];
				?>
					<option value="<?php echo $t_prid; ?>" <?php echo (int)$t_data['profil_id'] === $t_prid ? 'selected="selected"' : ''; ?>>
						<?php echo string_display_line( $t_pr['name'] ); ?>
					</option>
				<?php } ?>
				</select>
			</td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_gueltig_ab' ); ?></th>
			<td><input type="date" name="gueltig_ab" class="input-sm"
				value="<?php echo string_attribute( $t_val( 'gueltig_ab' ) ); ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'label_gueltig_bis' ); ?></th>
			<td><input type="date" name="gueltig_bis" class="input-sm"
				value="<?php echo string_attribute( $t_val( 'gueltig_bis' ) ); ?>" />
				<span class="help-block" style="margin:4px 0 0"><?php echo plugin_lang_get( 'help_gueltig_bis' ); ?></span></td>
		</tr>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			value="<?php echo string_attribute( plugin_lang_get( 'btn_save' ) ); ?>" />
		<a class="btn btn-white btn-round" href="<?php echo plugin_page( 'zuordnung' ); ?>">
			<?php echo plugin_lang_get( 'btn_cancel' ); ?>
		</a>
	</div>
	</div>
</div>
</form>
</div>

<?php
layout_page_end();
