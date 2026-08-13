<?php
/**
 * QualificationTracker – event participant selection (F3.2).
 *
 * For one group event: lists the planned participants and offers the pool of
 * due candidates (persons who require the event's measure and have a gap),
 * filterable by department and gap kind. Capacity is shown with an overbooking
 * warning. Adding/removing is handled by veranstaltung_teilnehmer_update.php.
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
plugin_require_api( 'core/QT_Person.php' );
plugin_require_api( 'core/QT_Prerequisite.php' );
plugin_require_api( 'core/QT_CustomFields.php' );
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_SollIst.php' );
plugin_require_api( 'core/QT_Event.php' );
plugin_require_api( 'core/QT_Participant.php' );

$f_id        = gpc_get_int( 'id' );
$f_abteilung = gpc_get_string( 'abteilung', '' );
$f_art       = gpc_get_string( 'art', '' );
$t_msg       = gpc_get_string( 'msg', '' );

$t_event = qt_event_get( $f_id );
if( $t_event === false ) {
	error_parameters( $f_id );
	trigger_error( ERROR_GENERIC, ERROR );
}

$t_massnahme_id = (int)$t_event['massnahme_id'];
$t_massnahme    = qt_massnahme_get( $t_massnahme_id );
$t_today        = date( 'Y-m-d' );

$t_participants  = qt_teilnehmer_load( $f_id );
$t_count         = count( $t_participants );
$t_cap_state     = qt_teilnehmer_capacity_state( $t_event['kapazitaet'], $t_count );
$t_candidates    = qt_teilnehmer_candidates( $t_massnahme_id, $f_id, $t_today, $f_abteilung, $f_art );
$t_abteilungen   = qt_person_distinct_abteilungen();

# Labels / bootstrap classes per gap kind (subset relevant to candidates).
$t_art_class = array(
	'fehlt'      => 'warning',
	'offen'      => 'info',
	'abgelaufen' => 'danger',
);
$t_art_lang = array(
	'fehlt'      => 'sollist_art_fehlt',
	'offen'      => 'sollist_art_offen',
	'abgelaufen' => 'sollist_art_abgelaufen',
);

$t_cap_class = array(
	'unbegrenzt' => 'label-default',
	'frei'       => 'label-success',
	'voll'       => 'label-warning',
	'ueberbucht' => 'label-danger',
);

layout_page_header( plugin_lang_get( 'teilnehmer_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg !== '' ) { ?>
	<div class="alert alert-success"><?php echo string_display_line( plugin_lang_get( 'teilnehmer_msg_' . $t_msg ) ); ?></div>
<?php } ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-users"></i>
			<?php echo string_display_line( plugin_lang_get( 'teilnehmer_title' ) . ': ' . $t_event['titel'] ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<a class="btn btn-sm btn-white btn-round" href="<?php echo plugin_page( 'veranstaltung' ); ?>">
			<i class="ace-icon fa fa-arrow-left"></i> <?php echo plugin_lang_get( 'teilnehmer_back' ); ?>
		</a>
		<span class="pull-right">
			<strong><?php echo string_display_line( (string)( $t_massnahme !== false ? $t_massnahme['schluessel'] . ' – ' . $t_massnahme['bezeichnung'] : '#' . $t_massnahme_id ) ); ?></strong>
			&nbsp;·&nbsp;<?php echo string_display_line( substr( (string)$t_event['termin'], 0, 16 ) ); ?>
			&nbsp;·&nbsp;<?php echo plugin_lang_get( 'event_col_kapazitaet' ); ?>:
			<span class="label <?php echo $t_cap_class[$t_cap_state]; ?>">
				<?php echo $t_count . ' / ' . ( (int)$t_event['kapazitaet'] > 0 ? (int)$t_event['kapazitaet'] : '∞' ); ?>
			</span>
		</span>
	</div>

	<?php if( $t_cap_state === 'ueberbucht' ) { ?>
		<div class="alert alert-danger" style="margin:8px">
			<i class="ace-icon fa fa-exclamation-triangle"></i>
			<?php echo plugin_lang_get( 'teilnehmer_warn_overbooked' ); ?>
		</div>
	<?php } ?>

	<!-- Planned participants -->
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_aktionen' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_participants as $t_p ) { ?>
			<tr>
				<td><?php echo string_display_line( (string)$t_p['personalnummer'] ); ?></td>
				<td><?php echo string_display_line( trim( $t_p['nachname'] . ', ' . $t_p['vorname'], ', ' ) ); ?></td>
				<td><?php echo string_display_line( (string)$t_p['abteilung'] ); ?></td>
				<td class="center">
					<form class="form-inline" style="display:inline" method="post"
						action="<?php echo plugin_page( 'veranstaltung_teilnehmer_update' ); ?>">
						<?php echo form_security_field( 'plugin_QualificationTracker_veranstaltung_teilnehmer_update' ); ?>
						<input type="hidden" name="id" value="<?php echo $f_id; ?>" />
						<input type="hidden" name="action" value="remove" />
						<input type="hidden" name="teilnehmer_id" value="<?php echo (int)$t_p['id']; ?>" />
						<button type="submit" class="btn btn-xs btn-danger btn-white btn-round">
							<i class="ace-icon fa fa-user-times"></i> <?php echo plugin_lang_get( 'teilnehmer_remove' ); ?>
						</button>
					</form>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_participants ) ) { ?>
			<tr><td colspan="4" class="center"><?php echo plugin_lang_get( 'teilnehmer_none' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>
	</div>
</div>

<!-- Candidate pool -->
<div class="widget-box widget-color-green2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-user-plus"></i>
			<?php echo plugin_lang_get( 'teilnehmer_candidates_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<span class="help-block pull-left" style="margin:4px 12px 0 0"><?php echo plugin_lang_get( 'teilnehmer_candidates_intro' ); ?></span>
		<form class="form-inline pull-right" method="get" action="<?php echo plugin_page( 'veranstaltung_teilnehmer' ); ?>">
			<input type="hidden" name="page" value="QualificationTracker/veranstaltung_teilnehmer" />
			<input type="hidden" name="id" value="<?php echo $f_id; ?>" />
			<label><?php echo plugin_lang_get( 'filter_abteilung' ); ?>&nbsp;</label>
			<select name="abteilung" class="input-sm" onchange="this.form.submit()">
				<option value=""><?php echo plugin_lang_get( 'filter_all' ); ?></option>
			<?php foreach( $t_abteilungen as $t_ab ) { ?>
				<option value="<?php echo string_attribute( $t_ab ); ?>" <?php echo $f_abteilung === $t_ab ? 'selected="selected"' : ''; ?>>
					<?php echo string_display_line( $t_ab ); ?>
				</option>
			<?php } ?>
			</select>
			&nbsp;
			<label><?php echo plugin_lang_get( 'teilnehmer_filter_art' ); ?>&nbsp;</label>
			<select name="art" class="input-sm" onchange="this.form.submit()">
				<option value=""><?php echo plugin_lang_get( 'filter_all' ); ?></option>
			<?php foreach( $t_art_lang as $t_key => $t_langkey ) { ?>
				<option value="<?php echo $t_key; ?>" <?php echo $f_art === $t_key ? 'selected="selected"' : ''; ?>>
					<?php echo plugin_lang_get( $t_langkey ); ?>
				</option>
			<?php } ?>
			</select>
		</form>
	</div>

	<form method="post" action="<?php echo plugin_page( 'veranstaltung_teilnehmer_update' ); ?>">
	<?php echo form_security_field( 'plugin_QualificationTracker_veranstaltung_teilnehmer_update' ); ?>
	<input type="hidden" name="id" value="<?php echo $f_id; ?>" />
	<input type="hidden" name="action" value="add" />

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th class="center" style="width:32px">
					<input type="checkbox" onclick="var b=this.checked;var c=this.form.querySelectorAll('input.qt-cand');for(var i=0;i&lt;c.length;i++){c[i].checked=b;}" />
				</th>
				<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
				<th><?php echo plugin_lang_get( 'teilnehmer_col_art' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_candidates as $t_c ) { ?>
			<tr>
				<td class="center">
					<input type="checkbox" class="qt-cand" name="person_ids[]" value="<?php echo (int)$t_c['person_id']; ?>" />
				</td>
				<td><?php echo string_display_line( (string)$t_c['personalnummer'] ); ?></td>
				<td><?php echo string_display_line( $t_c['person'] ); ?></td>
				<td><?php echo string_display_line( (string)$t_c['abteilung'] ); ?></td>
				<td>
					<span class="label label-<?php echo $t_art_class[$t_c['art']]; ?>">
						<?php echo plugin_lang_get( $t_art_lang[$t_c['art']] ); ?>
					</span>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_candidates ) ) { ?>
			<tr><td colspan="5" class="center"><?php echo plugin_lang_get( 'teilnehmer_no_candidates' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>

	<?php if( !empty( $t_candidates ) ) { ?>
	<div class="widget-toolbox padding-8 clearfix">
		<button type="submit" class="btn btn-primary btn-white btn-round btn-sm">
			<i class="ace-icon fa fa-plus"></i> <?php echo plugin_lang_get( 'teilnehmer_add_selected' ); ?>
		</button>
	</div>
	<?php } ?>
	</form>
	</div>
</div>
</div>

<?php
layout_page_end();
