<?php
/**
 * QualificationTracker – configuration page (F1.6).
 *
 * Edits the plugin defaults: management access level, due-date calculation
 * defaults, the per-department reference-month staffelung, the escalation day
 * thresholds and the target project for proof tickets.
 *
 * Status-value mapping and escalation recipients are added together with the
 * milestones that consume them (M2 / M5), to avoid speculative configuration.
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
# Configuring the plugin (including who may manage it) is an administrator task.
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'admin' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_CustomFields.php' );
plugin_require_api( 'core/QT_Integration.php' );

/* -------------------------------------------------------------------------- *
 *  Save
 * -------------------------------------------------------------------------- */
if( gpc_get_string( 'action', '' ) === 'update' ) {
	form_security_validate( 'plugin_QualificationTracker_config_update' );

	plugin_config_set( 'manage_threshold', gpc_get_int( 'manage_threshold' ) );

	$t_modus = gpc_get_string( 'faelligkeitsmodus_default', 'kalenderjahr' );
	if( !in_array( $t_modus, qt_catalog_modes(), true ) ) {
		$t_modus = 'kalenderjahr';
	}
	plugin_config_set( 'faelligkeitsmodus_default', $t_modus );

	$t_stichmonat = gpc_get_int( 'stichmonat_default', 11 );
	plugin_config_set( 'stichmonat_default', max( 1, min( 12, $t_stichmonat ) ) );

	plugin_config_set( 'karenz_tage_default', max( 0, gpc_get_int( 'karenz_tage_default', 42 ) ) );
	plugin_config_set( 'ersteinweisung_frist_tage', max( 0, gpc_get_int( 'ersteinweisung_frist_tage', 14 ) ) );

	# Escalation stages: four integer day offsets.
	$t_stufen = gpc_get_int_array( 'eskalation', array( 90, 30, 0, -30 ) );
	$t_stufen = array_map( 'intval', array_values( $t_stufen ) );
	plugin_config_set( 'eskalation_stufen_tage', $t_stufen );

	$t_zielprojekt = gpc_get_int( 'zielprojekt_id', 0 );
	plugin_config_set( 'zielprojekt_id', $t_zielprojekt );

	# F1.5: make sure the proof-ticket custom fields exist and, when a target
	# project is set, link them to it.
	qt_custom_fields_ensure();
	if( $t_zielprojekt > 0 ) {
		qt_custom_fields_link( $t_zielprojekt );
	}

	# Per-department reference month: keep only valid 1-12 entries.
	$t_raw = gpc_get_string_array( 'stichmonat_abteilung', array() );
	$t_map = array();
	foreach( $t_raw as $t_ab => $t_month ) {
		$t_month = (int)$t_month;
		if( $t_month >= 1 && $t_month <= 12 ) {
			$t_map[(string)$t_ab] = $t_month;
		}
	}
	plugin_config_set( 'stichmonat_abteilung', $t_map );

	form_security_purge( 'plugin_QualificationTracker_config_update' );
	print_successful_redirect( plugin_page( 'config', true ) );
	exit;
}

/* -------------------------------------------------------------------------- *
 *  Render
 * -------------------------------------------------------------------------- */
$t_manage_threshold = (int)plugin_config_get( 'manage_threshold' );
$t_modus_default    = plugin_config_get( 'faelligkeitsmodus_default' );
$t_stichmonat_def   = (int)plugin_config_get( 'stichmonat_default' );
$t_karenz_def       = (int)plugin_config_get( 'karenz_tage_default' );
$t_ersteinweisung   = (int)plugin_config_get( 'ersteinweisung_frist_tage' );
$t_stufen           = (array)plugin_config_get( 'eskalation_stufen_tage' );
$t_zielprojekt      = (int)plugin_config_get( 'zielprojekt_id' );
$t_abt_map          = (array)plugin_config_get( 'stichmonat_abteilung' );
$t_abteilungen      = qt_person_distinct_abteilungen();
$t_cf_status        = qt_custom_fields_status( $t_zielprojekt );

# Ensure exactly four escalation inputs.
$t_stufen = array_pad( array_slice( $t_stufen, 0, 4 ), 4, 0 );

layout_page_header( plugin_lang_get( 'config_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<form action="<?php echo plugin_page( 'config' ); ?>" method="post">
<?php echo form_security_field( 'plugin_QualificationTracker_config_update' ); ?>
<input type="hidden" name="action" value="update" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-cogs"></i>
			<?php echo plugin_lang_get( 'config_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed">
		<tr>
			<td colspan="2" class="category"><strong><?php echo plugin_lang_get( 'config_section_access' ); ?></strong></td>
		</tr>
		<tr>
			<th class="category" width="35%"><?php echo plugin_lang_get( 'config_label_manage_threshold' ); ?></th>
			<td>
				<select name="manage_threshold" class="input-sm">
					<?php print_enum_string_option_list( 'access_levels', $t_manage_threshold ); ?>
				</select>
				<span class="help-block" style="margin:4px 0 0"><?php echo plugin_lang_get( 'config_help_manage_threshold' ); ?></span>
			</td>
		</tr>

		<tr>
			<td colspan="2" class="category"><strong><?php echo plugin_lang_get( 'config_section_calc' ); ?></strong></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'config_label_modus_default' ); ?></th>
			<td>
				<select name="faelligkeitsmodus_default" class="input-sm">
				<?php foreach( qt_catalog_modes() as $t_mode ) { ?>
					<option value="<?php echo $t_mode; ?>" <?php echo $t_modus_default === $t_mode ? 'selected="selected"' : ''; ?>>
						<?php echo string_display_line( plugin_lang_get( 'mode_' . $t_mode ) ); ?>
					</option>
				<?php } ?>
				</select>
			</td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'config_label_stichmonat_default' ); ?></th>
			<td><input type="number" min="1" max="12" name="stichmonat_default" class="input-sm" style="width:8em"
				value="<?php echo $t_stichmonat_def; ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'config_label_karenz_default' ); ?></th>
			<td><input type="number" min="0" name="karenz_tage_default" class="input-sm" style="width:8em"
				value="<?php echo $t_karenz_def; ?>" /></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'config_label_ersteinweisung' ); ?></th>
			<td><input type="number" min="0" name="ersteinweisung_frist_tage" class="input-sm" style="width:8em"
				value="<?php echo $t_ersteinweisung; ?>" /></td>
		</tr>

		<tr>
			<td colspan="2" class="category"><strong><?php echo plugin_lang_get( 'config_section_stichmonat' ); ?></strong></td>
		</tr>
		<tr>
			<td colspan="2">
				<span class="help-block" style="margin:0 0 8px"><?php echo plugin_lang_get( 'config_help_stichmonat_abteilung' ); ?></span>
				<?php if( empty( $t_abteilungen ) ) { ?>
					<em><?php echo plugin_lang_get( 'config_no_abteilungen' ); ?></em>
				<?php } else { ?>
					<table class="table table-condensed" style="width:auto">
					<?php foreach( $t_abteilungen as $t_ab ) {
						$t_cur = isset( $t_abt_map[$t_ab] ) ? (int)$t_abt_map[$t_ab] : '';
					?>
						<tr>
							<td><?php echo string_display_line( $t_ab ); ?></td>
							<td><input type="number" min="1" max="12" style="width:6em"
								name="stichmonat_abteilung[<?php echo string_attribute( $t_ab ); ?>]"
								value="<?php echo $t_cur === '' ? '' : (int)$t_cur; ?>" /></td>
						</tr>
					<?php } ?>
					</table>
				<?php } ?>
			</td>
		</tr>

		<tr>
			<td colspan="2" class="category"><strong><?php echo plugin_lang_get( 'config_section_eskalation' ); ?></strong></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'config_label_eskalation' ); ?></th>
			<td>
				<?php for( $i = 0; $i < 4; $i++ ) { ?>
					<input type="number" name="eskalation[]" class="input-sm" style="width:6em;margin-right:4px"
						value="<?php echo (int)$t_stufen[$i]; ?>" />
				<?php } ?>
				<span class="help-block" style="margin:4px 0 0"><?php echo plugin_lang_get( 'config_help_eskalation' ); ?></span>
			</td>
		</tr>

		<tr>
			<td colspan="2" class="category"><strong><?php echo plugin_lang_get( 'config_section_zielprojekt' ); ?></strong></td>
		</tr>
		<tr>
			<th class="category"><?php echo plugin_lang_get( 'config_label_zielprojekt' ); ?></th>
			<td>
				<select name="zielprojekt_id" class="input-sm">
					<option value="0"><?php echo plugin_lang_get( 'none' ); ?></option>
					<?php print_project_option_list( $t_zielprojekt, false ); ?>
				</select>
				<span class="help-block" style="margin:4px 0 0"><?php echo plugin_lang_get( 'config_help_zielprojekt' ); ?></span>
			</td>
		</tr>

		<tr>
			<td colspan="2" class="category"><strong><?php echo plugin_lang_get( 'config_section_customfields' ); ?></strong></td>
		</tr>
		<tr>
			<td colspan="2">
				<span class="help-block" style="margin:0 0 8px"><?php echo plugin_lang_get( 'config_help_customfields' ); ?></span>
				<table class="table table-condensed" style="width:auto">
					<thead><tr>
						<th><?php echo plugin_lang_get( 'cf_col_field' ); ?></th>
						<th class="center"><?php echo plugin_lang_get( 'cf_col_exists' ); ?></th>
						<th class="center"><?php echo plugin_lang_get( 'cf_col_linked' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach( $t_cf_status as $t_cf ) { ?>
						<tr>
							<td><code><?php echo string_display_line( $t_cf['name'] ); ?></code></td>
							<td class="center"><?php echo $t_cf['exists'] ? '<i class="ace-icon fa fa-check green"></i>' : '<i class="ace-icon fa fa-minus grey"></i>'; ?></td>
							<td class="center"><?php echo $t_cf['linked'] ? '<i class="ace-icon fa fa-check green"></i>' : '<i class="ace-icon fa fa-minus grey"></i>'; ?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</td>
		</tr>

		<tr>
			<td colspan="2" class="category"><strong><?php echo plugin_lang_get( 'config_section_integration' ); ?></strong></td>
		</tr>
		<tr>
			<td colspan="2">
				<span class="help-block" style="margin:0 0 8px"><?php echo plugin_lang_get( 'config_help_integration' ); ?></span>
				<table class="table table-condensed" style="width:auto">
				<?php
				$t_integrations = array(
					'IssueRecurrence' => qt_integration_issuerecurrence(),
					'Reveille'        => qt_integration_reveille(),
				);
				foreach( $t_integrations as $t_name => $t_present ) {
				?>
					<tr>
						<td><code><?php echo string_display_line( $t_name ); ?></code></td>
						<td>
							<?php if( $t_present ) { ?>
								<span class="label label-success"><?php echo plugin_lang_get( 'config_integration_detected' ); ?></span>
							<?php } else { ?>
								<span class="label label-default"><?php echo plugin_lang_get( 'config_integration_absent' ); ?></span>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
				</table>
			</td>
		</tr>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			value="<?php echo string_attribute( plugin_lang_get( 'btn_save' ) ); ?>" />
	</div>
	</div>
</div>
</form>
</div>

<?php
layout_page_end();
