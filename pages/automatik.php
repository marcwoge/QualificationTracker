<?php
/**
 * QualificationTracker – automation overview (M5), expiry watchdog (F5.1).
 *
 * Shows the proofs whose validity has ended and lets a manager run the expiry
 * sweep manually. The nightly/CLI run (F5.5) calls the same qt_expiry_run().
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'bug_api.php' );
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
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_SollIst.php' );
plugin_require_api( 'core/QT_Matrix.php' );
plugin_require_api( 'core/QT_Integration.php' );
plugin_require_api( 'core/QT_Expiry.php' );
plugin_require_api( 'core/QT_Reactivation.php' );
plugin_require_api( 'core/QT_Escalation.php' );
plugin_require_api( 'core/QT_Ruhen.php' );

$t_today   = date( 'Y-m-d' );
$t_preview = qt_expiry_find( $t_today );
$t_msg     = gpc_get_string( 'msg', '' );

# Reactivation preview (F5.2).
$t_reveille = qt_integration_reveille();
$t_held     = qt_reactivation_held_status( $t_reveille );
$t_stufen   = plugin_config_get( 'eskalation_stufen_tage' );
$t_react    = array();
$t_react_todo = 0;
foreach( qt_reactivation_candidates() as $t_c ) {
	$t_vorlauf = qt_reactivation_vorlauf( $t_c, $t_stufen );
	$t_c['wake']    = qt_reactivation_wake_date( $t_c['soll_termin'], $t_vorlauf );
	$t_c['dormant'] = qt_reactivation_is_dormant( $t_c['wake'], $t_today );
	$t_status = (int)$t_c['bug_id'] > 0 && bug_exists( (int)$t_c['bug_id'] ) ? (int)bug_get_field( (int)$t_c['bug_id'], 'status' ) : 0;
	if( $t_c['dormant'] && $t_status !== $t_held ) {
		$t_c['todo'] = 'defer';
		$t_react_todo++;
	} else if( !$t_c['dormant'] && !$t_reveille && $t_status === $t_held ) {
		$t_c['todo'] = 'reactivate';
		$t_react_todo++;
	} else {
		$t_c['todo'] = '';
	}
	$t_react[] = $t_c;
}

# Escalation preview (F5.3).
$t_esk_stufen = (array)plugin_config_get( 'eskalation_stufen_tage' );
$t_esk = array();
$t_esk_todo = 0;
foreach( qt_eskalation_candidates() as $t_c ) {
	$t_reached = qt_eskalation_reached_count( $t_c['soll_termin'], $t_today, $t_esk_stufen );
	$t_stored = (int)$t_c['eskalation_stufe'];
	if( $t_reached <= 0 && $t_stored <= 0 ) {
		continue;
	}
	$t_c['reached'] = $t_reached;
	$t_c['stored']  = $t_stored;
	if( $t_reached > $t_stored ) {
		$t_esk_todo++;
	}
	$t_esk[] = $t_c;
}

# Ruhensvermerk preview (F5.4).
$t_ruhen = array();
$t_ruhen_todo = 0;
$t_ruhen_cache = array();
foreach( qt_ruhen_candidates() as $t_c ) {
	$t_pid = (int)$t_c['person_id'];
	if( !isset( $t_ruhen_cache[$t_pid] ) ) {
		$t_by = array();
		foreach( qt_nachweis_load_for_person( $t_pid ) as $t_nw ) {
			$t_by[(int)$t_nw['massnahme_id']][] = $t_nw;
		}
		$t_ruhen_cache[$t_pid] = $t_by;
	}
	$t_states = qt_ruhen_prereq_states( (int)$t_c['massnahme_id'], $t_ruhen_cache[$t_pid], $t_today );
	$t_c['rest'] = qt_ruhen_should_rest( $t_states );
	$t_c['is_ruht'] = !empty( $t_c['ruht'] );
	if( $t_c['rest'] && !$t_c['is_ruht'] ) {
		$t_c['todo'] = 'suspend';
		$t_ruhen_todo++;
	} else if( !$t_c['rest'] && $t_c['is_ruht'] ) {
		$t_c['todo'] = 'lift';
		$t_ruhen_todo++;
	} else {
		$t_c['todo'] = '';
	}
	# Only appointments that actually have safety-relevant prerequisites are of interest.
	if( !empty( $t_states ) ) {
		$t_ruhen[] = $t_c;
	}
}

layout_page_header( plugin_lang_get( 'menu_automatik' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg === 'expired' ) { ?>
	<div class="alert alert-success">
		<i class="ace-icon fa fa-check"></i>
		<?php echo sprintf( plugin_lang_get( 'watchdog_msg_done' ), gpc_get_int( 'count', 0 ) ); ?>
	</div>
<?php } else if( $t_msg === 'reactivated' ) { ?>
	<div class="alert alert-success">
		<i class="ace-icon fa fa-check"></i>
		<?php echo sprintf( plugin_lang_get( 'reactivation_msg_done' ), gpc_get_int( 'deferred', 0 ), gpc_get_int( 'reactivated', 0 ) ); ?>
	</div>
<?php } else if( $t_msg === 'escalated' ) { ?>
	<div class="alert alert-success">
		<i class="ace-icon fa fa-check"></i>
		<?php echo sprintf( plugin_lang_get( 'eskalation_msg_done' ), gpc_get_int( 'notified', 0 ), gpc_get_int( 'stages', 0 ) ); ?>
	</div>
<?php } else if( $t_msg === 'ruhen' ) { ?>
	<div class="alert alert-success">
		<i class="ace-icon fa fa-check"></i>
		<?php echo sprintf( plugin_lang_get( 'ruhen_msg_done' ), gpc_get_int( 'suspended', 0 ), gpc_get_int( 'lifted', 0 ) ); ?>
	</div>
<?php } ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-clock-o"></i>
			<?php echo plugin_lang_get( 'watchdog_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<span class="help-block pull-left" style="margin:4px 12px 0 0"><?php echo plugin_lang_get( 'watchdog_intro' ); ?></span>
		<form class="form-inline pull-right" method="post" action="<?php echo plugin_page( 'automatik_run' ); ?>">
			<?php echo form_security_field( 'plugin_QualificationTracker_automatik_run' ); ?>
			<button type="submit" class="btn btn-sm btn-primary btn-white btn-round"
				<?php echo empty( $t_preview ) ? 'disabled="disabled"' : ''; ?>>
				<i class="ace-icon fa fa-play"></i>
				<?php echo plugin_lang_get( 'watchdog_run' ); ?>
				<?php if( !empty( $t_preview ) ) { echo '(' . count( $t_preview ) . ')'; } ?>
			</button>
		</form>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
				<th><?php echo plugin_lang_get( 'label_event_massnahme' ); ?></th>
				<th><?php echo plugin_lang_get( 'export_gueltig_bis' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'teilnehmer_col_ticket' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_preview as $t_r ) { $t_bug = (int)$t_r['bug_id']; ?>
			<tr>
				<td><?php echo string_display_line( (string)$t_r['personalnummer'] ); ?></td>
				<td><?php echo string_display_line( trim( $t_r['nachname'] . ', ' . $t_r['vorname'], ', ' ) ); ?></td>
				<td><?php echo string_display_line( (string)$t_r['abteilung'] ); ?></td>
				<td><?php echo string_display_line( $t_r['schluessel'] . ' – ' . $t_r['bezeichnung'] ); ?></td>
				<td><?php echo string_display_line( (string)$t_r['gueltig_bis'] ); ?></td>
				<td class="center">
					<?php if( $t_bug > 0 ) { ?>
						<a href="<?php echo string_attribute( string_get_bug_view_url( $t_bug ) ); ?>"><?php echo bug_format_id( $t_bug ); ?></a>
					<?php } else { echo '&ndash;'; } ?>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_preview ) ) { ?>
			<tr><td colspan="6" class="center"><?php echo plugin_lang_get( 'watchdog_none' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>
	</div>
</div>

<!-- Ablaufreaktivierung (F5.2) -->
<div class="widget-box widget-color-green2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-bell-o"></i>
			<?php echo plugin_lang_get( 'reactivation_title' ); ?>
			<?php if( $t_reveille ) { ?>
				<span class="label label-info"><?php echo plugin_lang_get( 'reactivation_mode_reveille' ); ?></span>
			<?php } else { ?>
				<span class="label label-default"><?php echo plugin_lang_get( 'reactivation_mode_native' ); ?></span>
			<?php } ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<span class="help-block pull-left" style="margin:4px 12px 0 0"><?php echo plugin_lang_get( 'reactivation_intro' ); ?></span>
		<form class="form-inline pull-right" method="post" action="<?php echo plugin_page( 'automatik_run' ); ?>">
			<?php echo form_security_field( 'plugin_QualificationTracker_automatik_run' ); ?>
			<input type="hidden" name="action" value="reactivation" />
			<button type="submit" class="btn btn-sm btn-primary btn-white btn-round"
				<?php echo $t_react_todo === 0 ? 'disabled="disabled"' : ''; ?>>
				<i class="ace-icon fa fa-play"></i>
				<?php echo plugin_lang_get( 'reactivation_run' ); ?>
				<?php if( $t_react_todo > 0 ) { echo '(' . $t_react_todo . ')'; } ?>
			</button>
		</form>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'label_event_massnahme' ); ?></th>
				<th><?php echo plugin_lang_get( 'export_soll_termin' ); ?></th>
				<th><?php echo plugin_lang_get( 'reactivation_col_wake' ); ?></th>
				<th><?php echo plugin_lang_get( 'reactivation_col_state' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'teilnehmer_col_ticket' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_react as $t_c ) { $t_bug = (int)$t_c['bug_id']; ?>
			<tr>
				<td><?php echo string_display_line( trim( $t_c['nachname'] . ', ' . $t_c['vorname'], ', ' ) ); ?></td>
				<td><?php echo string_display_line( $t_c['schluessel'] . ' – ' . $t_c['bezeichnung'] ); ?></td>
				<td><?php echo string_display_line( (string)$t_c['soll_termin'] ); ?></td>
				<td><?php echo string_display_line( (string)$t_c['wake'] ); ?></td>
				<td>
					<?php if( $t_c['dormant'] ) { ?>
						<span class="label label-default"><?php echo plugin_lang_get( 'reactivation_state_dormant' ); ?></span>
					<?php } else { ?>
						<span class="label label-success"><?php echo plugin_lang_get( 'reactivation_state_active' ); ?></span>
					<?php }
					if( $t_c['todo'] === 'defer' ) { echo ' <span class="label label-warning">' . plugin_lang_get( 'reactivation_todo_defer' ) . '</span>'; }
					else if( $t_c['todo'] === 'reactivate' ) { echo ' <span class="label label-primary">' . plugin_lang_get( 'reactivation_todo_reactivate' ) . '</span>'; }
					?>
				</td>
				<td class="center">
					<?php if( $t_bug > 0 ) { ?>
						<a href="<?php echo string_attribute( string_get_bug_view_url( $t_bug ) ); ?>"><?php echo bug_format_id( $t_bug ); ?></a>
					<?php } else { echo '&ndash;'; } ?>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_react ) ) { ?>
			<tr><td colspan="6" class="center"><?php echo plugin_lang_get( 'reactivation_none' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>
	</div>
</div>

<!-- Eskalationsstufen (F5.3) -->
<div class="widget-box widget-color-orange">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-bullhorn"></i>
			<?php echo plugin_lang_get( 'eskalation_title' ); ?>
			<span class="grey" style="font-weight:normal">(<?php echo string_display_line( implode( ' / ', array_map( 'intval', $t_esk_stufen ) ) ); ?>)</span>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<span class="help-block pull-left" style="margin:4px 12px 0 0"><?php echo plugin_lang_get( 'eskalation_intro' ); ?></span>
		<form class="form-inline pull-right" method="post" action="<?php echo plugin_page( 'automatik_run' ); ?>">
			<?php echo form_security_field( 'plugin_QualificationTracker_automatik_run' ); ?>
			<input type="hidden" name="action" value="escalation" />
			<button type="submit" class="btn btn-sm btn-primary btn-white btn-round"
				<?php echo $t_esk_todo === 0 ? 'disabled="disabled"' : ''; ?>>
				<i class="ace-icon fa fa-play"></i>
				<?php echo plugin_lang_get( 'eskalation_run' ); ?>
				<?php if( $t_esk_todo > 0 ) { echo '(' . $t_esk_todo . ')'; } ?>
			</button>
		</form>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'label_event_massnahme' ); ?></th>
				<th><?php echo plugin_lang_get( 'export_soll_termin' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'eskalation_col_stage' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_esk as $t_c ) { ?>
			<tr>
				<td><?php echo string_display_line( trim( $t_c['nachname'] . ', ' . $t_c['vorname'], ', ' ) ); ?></td>
				<td><?php echo string_display_line( $t_c['schluessel'] . ' – ' . $t_c['bezeichnung'] ); ?></td>
				<td><?php echo string_display_line( (string)$t_c['soll_termin'] ); ?></td>
				<td class="center">
					<span class="label <?php echo $t_c['reached'] > $t_c['stored'] ? 'label-warning' : 'label-default'; ?>">
						<?php echo (int)$t_c['reached'] . ' / ' . count( $t_esk_stufen ); ?>
					</span>
					<?php if( $t_c['reached'] > $t_c['stored'] ) { echo '<span class="label label-primary">' . plugin_lang_get( 'eskalation_todo' ) . '</span>'; } ?>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_esk ) ) { ?>
			<tr><td colspan="4" class="center"><?php echo plugin_lang_get( 'eskalation_none' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>
	</div>
</div>

<!-- Ruhensvermerk (F5.4) -->
<div class="widget-box widget-color-red">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-ban"></i>
			<?php echo plugin_lang_get( 'ruhen_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<span class="help-block pull-left" style="margin:4px 12px 0 0"><?php echo plugin_lang_get( 'ruhen_intro' ); ?></span>
		<form class="form-inline pull-right" method="post" action="<?php echo plugin_page( 'automatik_run' ); ?>">
			<?php echo form_security_field( 'plugin_QualificationTracker_automatik_run' ); ?>
			<input type="hidden" name="action" value="ruhen" />
			<button type="submit" class="btn btn-sm btn-primary btn-white btn-round"
				<?php echo $t_ruhen_todo === 0 ? 'disabled="disabled"' : ''; ?>>
				<i class="ace-icon fa fa-play"></i>
				<?php echo plugin_lang_get( 'ruhen_run' ); ?>
				<?php if( $t_ruhen_todo > 0 ) { echo '(' . $t_ruhen_todo . ')'; } ?>
			</button>
		</form>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'label_event_massnahme' ); ?></th>
				<th><?php echo plugin_lang_get( 'reactivation_col_state' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'teilnehmer_col_ticket' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_ruhen as $t_c ) { $t_bug = (int)$t_c['bug_id']; ?>
			<tr>
				<td><?php echo string_display_line( trim( $t_c['nachname'] . ', ' . $t_c['vorname'], ', ' ) ); ?></td>
				<td><?php echo string_display_line( $t_c['schluessel'] . ' – ' . $t_c['bezeichnung'] ); ?></td>
				<td>
					<?php if( $t_c['is_ruht'] ) { ?>
						<span class="label label-danger"><?php echo plugin_lang_get( 'ruhen_state_resting' ); ?></span>
					<?php } else { ?>
						<span class="label label-success"><?php echo plugin_lang_get( 'reactivation_state_active' ); ?></span>
					<?php }
					if( $t_c['todo'] === 'suspend' ) { echo ' <span class="label label-warning">' . plugin_lang_get( 'ruhen_todo_suspend' ) . '</span>'; }
					else if( $t_c['todo'] === 'lift' ) { echo ' <span class="label label-primary">' . plugin_lang_get( 'ruhen_todo_lift' ) . '</span>'; }
					?>
				</td>
				<td class="center">
					<?php if( $t_bug > 0 ) { ?>
						<a href="<?php echo string_attribute( string_get_bug_view_url( $t_bug ) ); ?>"><?php echo bug_format_id( $t_bug ); ?></a>
					<?php } else { echo '&ndash;'; } ?>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_ruhen ) ) { ?>
			<tr><td colspan="4" class="center"><?php echo plugin_lang_get( 'ruhen_none' ); ?></td></tr>
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
