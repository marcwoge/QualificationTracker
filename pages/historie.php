<?php
/**
 * QualificationTracker – master-data change log (Änderungsprotokoll, F7.5).
 *
 * Shows the plugin-owned history of create/update/delete changes to the measure
 * catalogue, the activity profiles and the assignments – the data MantisBT's own
 * bug history cannot see. Optionally filtered by entity type. Read-only.
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
require_api( 'gpc_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );
require_api( 'user_api.php' );

auth_reauthenticate();
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'manage' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Profile.php' );
plugin_require_api( 'core/QT_History.php' );

$f_typ  = gpc_get_string( 'typ', '' );
if( $f_typ !== '' && !in_array( $f_typ, qt_historie_entities(), true ) ) {
	$f_typ = '';
}
$t_rows = qt_historie_load_recent( 200, $f_typ, 0 );

/**
 * Resolve a readable label for the entity a history row refers to. For create
 * and delete rows the stored label is used; for update rows the current name is
 * looked up, falling back to "#id" if the entity is gone.
 *
 * @param array $p_row History row.
 * @return string
 */
function qt_historie_entity_label( array $p_row ) {
	$t_typ = (string)$p_row['entity_typ'];
	$t_id  = (int)$p_row['entity_id'];

	if( (string)$p_row['aktion'] === 'create' && (string)$p_row['neu_wert'] !== '' ) {
		return (string)$p_row['neu_wert'];
	}
	if( (string)$p_row['aktion'] === 'delete' && (string)$p_row['alt_wert'] !== '' ) {
		return (string)$p_row['alt_wert'];
	}
	if( $t_typ === 'massnahme' ) {
		$t_m = qt_massnahme_get( $t_id );
		return is_array( $t_m ) ? (string)$t_m['schluessel'] : ( '#' . $t_id );
	}
	if( $t_typ === 'profil' ) {
		$t_p = qt_profil_get( $t_id );
		return is_array( $t_p ) ? (string)$t_p['name'] : ( '#' . $t_id );
	}
	return '#' . $t_id;
}

layout_page_header( plugin_lang_get( 'historie_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-history"></i>
			<?php echo plugin_lang_get( 'historie_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main">
		<span class="help-block"><?php echo plugin_lang_get( 'historie_intro' ); ?></span>
		<form class="form-inline" method="get" action="<?php echo plugin_page( 'historie' ); ?>">
			<input type="hidden" name="page" value="QualificationTracker/historie" />
			<label><?php echo plugin_lang_get( 'historie_filter_typ' ); ?></label>
			<select name="typ" class="input-sm" onchange="this.form.submit()">
				<option value=""><?php echo plugin_lang_get( 'historie_all' ); ?></option>
			<?php foreach( qt_historie_entities() as $t_e ) { ?>
				<option value="<?php echo string_attribute( $t_e ); ?>" <?php echo $f_typ === $t_e ? 'selected="selected"' : ''; ?>>
					<?php echo string_display_line( plugin_lang_get( 'historie_entity_' . $t_e ) ); ?>
				</option>
			<?php } ?>
			</select>
		</form>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead><tr>
			<th><?php echo plugin_lang_get( 'historie_col_when' ); ?></th>
			<th><?php echo plugin_lang_get( 'historie_col_user' ); ?></th>
			<th><?php echo plugin_lang_get( 'historie_col_entity' ); ?></th>
			<th><?php echo plugin_lang_get( 'historie_col_action' ); ?></th>
			<th><?php echo plugin_lang_get( 'historie_col_field' ); ?></th>
			<th><?php echo plugin_lang_get( 'historie_col_old' ); ?></th>
			<th><?php echo plugin_lang_get( 'historie_col_new' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if( empty( $t_rows ) ) { ?>
			<tr><td colspan="7" class="center"><em><?php echo plugin_lang_get( 'historie_empty' ); ?></em></td></tr>
		<?php } else { foreach( $t_rows as $t_r ) {
			$t_action = (string)$t_r['aktion'];
			$t_field  = (string)$t_r['feld'];
			$t_field_label = $t_field === '' ? '&ndash;'
				: string_display_line( plugin_lang_get_defaulted( 'label_' . $t_field, $t_field ) );
			$t_badge = $t_action === 'create' ? 'success' : ( $t_action === 'delete' ? 'important' : 'info' );
		?>
			<tr>
				<td><?php echo string_display_line( date( 'Y-m-d H:i', (int)$t_r['date_created'] ) ); ?></td>
				<td><?php echo string_display_line( user_get_name( (int)$t_r['user_id'] ) ); ?></td>
				<td>
					<span class="grey"><?php echo string_display_line( plugin_lang_get( 'historie_entity_' . $t_r['entity_typ'] ) ); ?></span>
					<?php echo string_display_line( qt_historie_entity_label( $t_r ) ); ?>
				</td>
				<td><span class="badge badge-<?php echo $t_badge; ?>"><?php echo string_display_line( plugin_lang_get( 'historie_action_' . $t_action ) ); ?></span></td>
				<td><?php echo $t_field_label; ?></td>
				<td><?php echo (string)$t_r['alt_wert'] === '' ? '&ndash;' : string_display_line( (string)$t_r['alt_wert'] ); ?></td>
				<td><?php echo (string)$t_r['neu_wert'] === '' ? '&ndash;' : string_display_line( (string)$t_r['neu_wert'] ); ?></td>
			</tr>
		<?php } } ?>
		</tbody>
	</table>
	</div>
	</div>
	</div>
</div>
</div>

<?php
layout_page_end();
