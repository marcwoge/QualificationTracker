<?php
/**
 * QualificationTracker – group-event suggestions (Terminvorschlag, F3.7).
 *
 * A planning aid for the safety officer: which measures currently need a group
 * event, a suggested date, and – respecting the optimal capacity – how the due
 * persons split into sessions. Read-only; from here the officer creates the
 * actual events (Veranstaltungen).
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
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'manage' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_CustomFields.php' );
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_SollIst.php' );
plugin_require_api( 'core/QT_Matrix.php' );
plugin_require_api( 'core/QT_Suggestion.php' );

$f_abteilung = qt_access_effective_abteilung( gpc_get_string( 'abteilung', '' ), qt_access_viewer_abteilung() );
$f_capacity  = gpc_get_int( 'kapazitaet', (int)plugin_config_get( 'vorschlag_kapazitaet' ) );
if( $f_capacity < 0 ) { $f_capacity = 0; }

$t_today   = date( 'Y-m-d' );
$t_filters = array();
if( $f_abteilung !== '' ) { $t_filters['abteilung'] = $f_abteilung; }

$t_proposals   = qt_vorschlag_build( $t_today, $f_capacity, $t_filters );
$t_abteilungen = qt_person_distinct_abteilungen();

$t_state_class = array( 'abgelaufen' => 'danger', 'fehlt' => 'default', 'offen' => 'info', 'bald' => 'warning' );

layout_page_header( plugin_lang_get( 'terminvorschlag_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-calendar-plus-o"></i>
			<?php echo plugin_lang_get( 'terminvorschlag_title' ); ?>
		</h4>
	</div>
	<div class="widget-body"><div class="widget-main">
		<span class="help-block"><?php echo plugin_lang_get( 'terminvorschlag_intro' ); ?></span>
		<form class="form-inline" method="get" action="<?php echo plugin_page( 'terminvorschlag' ); ?>">
			<input type="hidden" name="page" value="QualificationTracker/terminvorschlag" />
			<label><?php echo plugin_lang_get( 'col_abteilung' ); ?></label>
			<select name="abteilung" class="input-sm">
				<option value="">&ndash;</option>
			<?php foreach( $t_abteilungen as $t_ab ) { ?>
				<option value="<?php echo string_attribute( $t_ab ); ?>" <?php echo $f_abteilung === $t_ab ? 'selected="selected"' : ''; ?>>
					<?php echo string_display_line( $t_ab ); ?>
				</option>
			<?php } ?>
			</select>
			&nbsp;
			<label><?php echo plugin_lang_get( 'terminvorschlag_capacity' ); ?></label>
			<input type="number" name="kapazitaet" min="0" class="input-sm" style="width:6em" value="<?php echo (int)$f_capacity; ?>" />
			&nbsp;
			<button type="submit" class="btn btn-sm btn-primary btn-white btn-round">
				<i class="ace-icon fa fa-refresh"></i> <?php echo plugin_lang_get( 'terminvorschlag_refresh' ); ?>
			</button>
		</form>
	</div></div>
</div>

<?php if( empty( $t_proposals ) ) { ?>
	<div class="widget-box"><div class="widget-body"><div class="widget-main">
		<em><?php echo plugin_lang_get( 'terminvorschlag_none' ); ?></em>
	</div></div></div>
<?php } else { foreach( $t_proposals as $t_p ) {
	$t_m = $t_p['measure'];
	$t_sessions = $t_p['sessions'];
?>
	<div class="widget-box widget-color-blue">
		<div class="widget-header widget-header-small">
			<h4 class="widget-title lighter">
				<i class="ace-icon fa fa-users"></i>
				<?php echo string_display_line( (string)$t_m['schluessel'] . ' – ' . (string)$t_m['bezeichnung'] ); ?>
			</h4>
			<div class="widget-toolbar">
				<span class="label label-info"><?php echo sprintf( plugin_lang_get( 'terminvorschlag_summary' ),
					(int)$t_p['total'], count( $t_sessions ) ); ?></span>
				<span class="label label-success"><i class="ace-icon fa fa-calendar"></i>
					<?php echo string_display_line( plugin_lang_get( 'terminvorschlag_proposed' ) . ' ' . $t_p['termin'] ); ?></span>
			</div>
		</div>
		<div class="widget-body"><div class="widget-main no-padding">
		<?php foreach( $t_sessions as $t_i => $t_session ) { ?>
			<div class="padding-6" style="border-top:1px solid #eee">
				<strong><?php echo sprintf( plugin_lang_get( 'terminvorschlag_session' ), (int)$t_i + 1, count( $t_session ) ); ?></strong>
			</div>
			<div class="table-responsive">
			<table class="table table-bordered table-condensed table-striped" style="margin-bottom:0">
				<thead><tr>
					<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
					<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
					<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
					<th><?php echo plugin_lang_get( 'event_col_status' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach( $t_session as $t_c ) {
					$t_person = $t_c['person'];
					$t_name = trim( (string)$t_person['nachname'] . ', ' . (string)$t_person['vorname'], ', ' );
					$t_cls = isset( $t_state_class[$t_c['state']] ) ? $t_state_class[$t_c['state']] : 'default';
				?>
					<tr>
						<td><?php echo string_display_line( (string)$t_person['personalnummer'] ); ?></td>
						<td><?php echo string_display_line( $t_name ); ?></td>
						<td><?php echo string_display_line( (string)$t_person['abteilung'] ); ?></td>
						<td><span class="label label-<?php echo $t_cls; ?>"><?php echo string_display_line( plugin_lang_get( 'matrix_state_' . $t_c['state'] ) ); ?></span></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
			</div>
		<?php } ?>
		</div>
		<div class="widget-toolbox padding-6 clearfix">
			<a class="btn btn-xs btn-white btn-round pull-right" href="<?php echo plugin_page( 'veranstaltung' ); ?>">
				<i class="ace-icon fa fa-calendar-plus-o"></i> <?php echo plugin_lang_get( 'menu_event' ); ?>
			</a>
		</div>
		</div>
	</div>
<?php } } ?>
</div>

<?php
layout_page_end();
