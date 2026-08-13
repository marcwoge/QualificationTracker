<?php
/**
 * QualificationTracker – open proofs list (F2.8).
 *
 * Lists proofs that are still open and offers a "complete" action per proof.
 * The bulk completion (one date + one attachment for many proofs) follows in
 * M3.
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
plugin_require_api( 'core/QT_Completion.php' );

$f_person = gpc_get_int( 'person_id', 0 );
$t_msg    = gpc_get_string( 'msg', '' );

$t_rows     = qt_completion_open_nachweise( $f_person );
$t_personen = qt_person_load_all();

layout_page_header( plugin_lang_get( 'nachweise_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg === 'completed' ) {
	$t_f = gpc_get_int( 'f', 0 );
?>
	<div class="alert alert-success">
		<?php echo plugin_lang_get( 'complete_msg' )
			. ( $t_f > 0 ? ' ' . plugin_lang_get( 'complete_followup_created' ) : '' ); ?>
	</div>
<?php } ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-check-square-o"></i>
			<?php echo plugin_lang_get( 'nachweise_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<form class="form-inline pull-right" method="get" action="<?php echo plugin_page( 'nachweise' ); ?>">
			<input type="hidden" name="page" value="QualificationTracker/nachweise" />
			<label><?php echo plugin_lang_get( 'filter_person' ); ?>&nbsp;</label>
			<select name="person_id" class="input-sm" onchange="this.form.submit()">
				<option value="0"><?php echo plugin_lang_get( 'filter_all' ); ?></option>
			<?php foreach( $t_personen as $t_p ) {
				$t_pid = (int)$t_p['id'];
			?>
				<option value="<?php echo $t_pid; ?>" <?php echo $f_person === $t_pid ? 'selected="selected"' : ''; ?>>
					<?php echo string_display_line( trim( $t_p['nachname'] . ', ' . $t_p['vorname'], ', ' ) ); ?>
				</option>
			<?php } ?>
			</select>
		</form>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_person' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_massnahme' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_typ' ); ?></th>
				<th><?php echo plugin_lang_get( 'label_soll_termin' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_aktionen' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_rows as $t_r ) {
			$t_id = (int)$t_r['id'];
		?>
			<tr>
				<td><?php echo string_display_line( trim( (string)$t_r['nachname'] . ', ' . (string)$t_r['vorname'], ', ' ) ); ?></td>
				<td><?php echo string_display_line( (string)$t_r['abteilung'] ); ?></td>
				<td><?php echo string_display_line( $t_r['schluessel'] . ' — ' . $t_r['bezeichnung'] ); ?></td>
				<td><?php echo string_display_line( plugin_lang_get( 'type_' . $t_r['typ'] ) ); ?></td>
				<td><?php echo $t_r['soll_termin'] === null ? '&ndash;' : string_display_line( $t_r['soll_termin'] ); ?></td>
				<td class="center">
					<a class="btn btn-xs btn-success btn-white btn-round"
						href="<?php echo plugin_page( 'complete' ); ?>&amp;nachweis_id=<?php echo $t_id; ?>">
						<i class="ace-icon fa fa-check"></i> <?php echo plugin_lang_get( 'btn_complete' ); ?>
					</a>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_rows ) ) { ?>
			<tr><td colspan="6" class="center"><?php echo plugin_lang_get( 'nachweise_none' ); ?></td></tr>
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
