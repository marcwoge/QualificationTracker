<?php
/**
 * QualificationTracker – target/actual report (F2.5).
 *
 * Read-only. Lists, per person, the required measures that have no valid proof,
 * plus the "appointment without qualification" case. Filterable by department.
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

auth_reauthenticate();
access_ensure_global_level( plugin_config_get( 'manage_threshold' ) );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_CustomFields.php' );
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_SollIst.php' );

$f_abteilung   = gpc_get_string( 'abteilung', '' );
$t_today       = date( 'Y-m-d' );
$t_gaps        = qt_sollist_gaps( $t_today, $f_abteilung );
$t_abteilungen = qt_person_distinct_abteilungen();

# Labels / bootstrap classes per gap kind.
$t_art_class = array(
	'fehlt'              => 'warning',
	'offen'              => 'info',
	'abgelaufen'         => 'danger',
	'vorbedingung_fehlt' => 'danger',
);
$t_art_lang = array(
	'fehlt'              => 'sollist_art_fehlt',
	'offen'              => 'sollist_art_offen',
	'abgelaufen'         => 'sollist_art_abgelaufen',
	'vorbedingung_fehlt' => 'sollist_art_vorbedingung',
);

# Summary counts.
$t_counts = array( 'fehlt' => 0, 'offen' => 0, 'abgelaufen' => 0, 'vorbedingung_fehlt' => 0 );
foreach( $t_gaps as $t_g ) {
	if( isset( $t_counts[$t_g['art']] ) ) {
		$t_counts[$t_g['art']]++;
	}
}

layout_page_header( plugin_lang_get( 'sollist_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-balance-scale"></i>
			<?php echo plugin_lang_get( 'sollist_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<span class="help-block pull-left" style="margin:4px 12px 0 0"><?php echo plugin_lang_get( 'sollist_intro' ); ?></span>
		<form class="form-inline pull-right" method="get" action="<?php echo plugin_page( 'sollist' ); ?>">
			<input type="hidden" name="page" value="QualificationTracker/sollist" />
			<label><?php echo plugin_lang_get( 'filter_abteilung' ); ?>&nbsp;</label>
			<select name="abteilung" class="input-sm" onchange="this.form.submit()">
				<option value=""><?php echo plugin_lang_get( 'filter_all' ); ?></option>
			<?php foreach( $t_abteilungen as $t_ab ) { ?>
				<option value="<?php echo string_attribute( $t_ab ); ?>" <?php echo $f_abteilung === $t_ab ? 'selected="selected"' : ''; ?>>
					<?php echo string_display_line( $t_ab ); ?>
				</option>
			<?php } ?>
			</select>
		</form>
	</div>

	<div class="widget-toolbox padding-8">
		<?php foreach( $t_counts as $t_art => $t_n ) { ?>
			<span class="label label-<?php echo $t_art_class[$t_art]; ?>">
				<?php echo plugin_lang_get( $t_art_lang[$t_art] ) . ': ' . (int)$t_n; ?>
			</span>
		<?php } ?>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_person' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_massnahme' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_typ' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_art' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_gaps as $t_g ) { ?>
			<tr>
				<td><?php echo string_display_line( $t_g['person'] ); ?></td>
				<td><?php echo $t_g['personalnummer'] === null ? '&ndash;' : string_display_line( $t_g['personalnummer'] ); ?></td>
				<td><?php echo string_display_line( $t_g['abteilung'] ); ?></td>
				<td><?php echo string_display_line( $t_g['schluessel'] . ' — ' . $t_g['bezeichnung'] ); ?></td>
				<td><?php echo string_display_line( plugin_lang_get( 'type_' . $t_g['typ'] ) ); ?></td>
				<td>
					<span class="label label-<?php echo $t_art_class[$t_g['art']]; ?>">
						<?php echo plugin_lang_get( $t_art_lang[$t_g['art']] );
						echo $t_g['detail'] !== '' ? ': ' . string_display_line( $t_g['detail'] ) : ''; ?>
					</span>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_gaps ) ) { ?>
			<tr><td colspan="6" class="center"><?php echo plugin_lang_get( 'sollist_no_gaps' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>
	</div>
</div>
</div>

<?php
layout_page_end();
