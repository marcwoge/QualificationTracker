<?php
/**
 * QualificationTracker – event mass completion (F3.4), attendance form.
 *
 * Shows the participants of an event with an attendance checkbox, a completion
 * date and the instructor. Submitting posts to veranstaltung_abschluss_do.php,
 * which completes the proofs of the present participants (reusing QT_Completion)
 * and marks the absent ones. Only participants with a child proof ticket (F3.3)
 * can be completed.
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
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'edit' );

plugin_require_api( 'core/QT_Catalog.php' );
plugin_require_api( 'core/QT_Event.php' );
plugin_require_api( 'core/QT_Participant.php' );

$f_id = gpc_get_int( 'id' );

$t_event = qt_event_get( $f_id );
if( $t_event === false ) {
	error_parameters( $f_id );
	trigger_error( ERROR_GENERIC, ERROR );
}

$t_massnahme   = qt_massnahme_get( (int)$t_event['massnahme_id'] );
$t_participants = qt_teilnehmer_load( $f_id );

# Only participants with a generated child ticket can be completed.
$t_completable = array();
foreach( $t_participants as $t_p ) {
	if( (int)$t_p['bug_id'] > 0 ) {
		$t_completable[] = $t_p;
	}
}

$t_default_date = qt_event_termin_date( $t_event['termin'] );
if( $t_default_date === '' ) {
	$t_default_date = date( 'Y-m-d' );
}
$t_default_instructor = $t_event['unterweisender'] === null ? '' : $t_event['unterweisender'];

layout_page_header( plugin_lang_get( 'abschluss_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-check-square-o"></i>
			<?php echo string_display_line( plugin_lang_get( 'abschluss_title' ) . ': ' . $t_event['titel'] ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<form method="post" action="<?php echo plugin_page( 'veranstaltung_abschluss_do' ); ?>">
	<?php echo form_security_field( 'plugin_QualificationTracker_veranstaltung_abschluss' ); ?>
	<input type="hidden" name="id" value="<?php echo $f_id; ?>" />

	<div class="widget-toolbox padding-8 clearfix">
		<a class="btn btn-sm btn-white btn-round"
			href="<?php echo plugin_page( 'veranstaltung_teilnehmer' ); ?>&amp;id=<?php echo $f_id; ?>">
			<i class="ace-icon fa fa-arrow-left"></i> <?php echo plugin_lang_get( 'teilnehmer_back' ); ?>
		</a>
		<span class="pull-right">
			<strong><?php echo string_display_line( (string)( $t_massnahme !== false ? $t_massnahme['schluessel'] . ' – ' . $t_massnahme['bezeichnung'] : '#' . (int)$t_event['massnahme_id'] ) ); ?></strong>
			&nbsp;·&nbsp;<?php echo string_display_line( substr( (string)$t_event['termin'], 0, 16 ) ); ?>
		</span>
	</div>

	<div class="widget-main">
		<div class="form-group">
			<label class="bold"><?php echo plugin_lang_get( 'abschluss_label_datum' ); ?></label>
			<input type="date" name="durchgefuehrt_am" class="input-sm"
				value="<?php echo string_attribute( $t_default_date ); ?>" required="required" />
			&nbsp;&nbsp;
			<label class="bold"><?php echo plugin_lang_get( 'abschluss_label_unterweisender' ); ?></label>
			<input type="text" name="durchfuehrender" class="input-sm" size="30"
				value="<?php echo string_attribute( $t_default_instructor ); ?>" />
		</div>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th class="center" style="width:60px"><?php echo plugin_lang_get( 'abschluss_col_anwesend' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'teilnehmer_col_ticket' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_completable as $t_p ) { $t_bug = (int)$t_p['bug_id']; ?>
			<tr>
				<td class="center">
					<input type="checkbox" name="present[]" value="<?php echo (int)$t_p['person_id']; ?>" checked="checked" />
				</td>
				<td><?php echo string_display_line( (string)$t_p['personalnummer'] ); ?></td>
				<td><?php echo string_display_line( trim( $t_p['nachname'] . ', ' . $t_p['vorname'], ', ' ) ); ?></td>
				<td><?php echo string_display_line( (string)$t_p['abteilung'] ); ?></td>
				<td class="center">
					<a href="<?php echo string_attribute( string_get_bug_view_url( $t_bug ) ); ?>"><?php echo bug_format_id( $t_bug ); ?></a>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_completable ) ) { ?>
			<tr><td colspan="5" class="center"><?php echo plugin_lang_get( 'abschluss_none' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>

	<?php if( !empty( $t_completable ) ) { ?>
	<div class="widget-toolbox padding-8 clearfix">
		<span class="help-block pull-left" style="margin:4px 12px 0 0"><?php echo plugin_lang_get( 'abschluss_hint' ); ?></span>
		<button type="submit" class="btn btn-primary btn-white btn-round btn-sm">
			<i class="ace-icon fa fa-check"></i> <?php echo plugin_lang_get( 'abschluss_submit' ); ?>
		</button>
	</div>
	<?php } ?>
	</form>
	</div>
</div>
</div>

<?php
layout_page_end();
