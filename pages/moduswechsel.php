<?php
/**
 * QualificationTracker – due-date mode change simulation (F5.7).
 *
 * Select a measure and a new due-date mode; the page simulates the effect on the
 * open proofs (old vs new target date) before anything is applied. Completed
 * cycles are shown as preserved and never recomputed.
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
plugin_require_api( 'core/QT_DueDateCalculator.php' );
plugin_require_api( 'core/QT_Generator.php' );
plugin_require_api( 'core/QT_ModeChange.php' );

$f_massnahme  = gpc_get_int( 'massnahme_id', 0 );
$f_modus      = gpc_get_string( 'modus', '' );
$f_stichmonat = gpc_get_int( 'stichmonat', 0 );
$t_msg        = gpc_get_string( 'msg', '' );

$t_massnahmen = qt_massnahme_load_all();

$t_sim = null;
if( $f_massnahme > 0 && in_array( $f_modus, qt_catalog_modi(), true ) ) {
	$t_sim = qt_moduswechsel_simulate( $f_massnahme, $f_modus, $f_stichmonat );
}

layout_page_header( plugin_lang_get( 'menu_moduswechsel' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg === 'applied' ) { ?>
	<div class="alert alert-success">
		<i class="ace-icon fa fa-check"></i>
		<?php echo sprintf( plugin_lang_get( 'moduswechsel_msg_applied' ), gpc_get_int( 'updated', 0 ), gpc_get_int( 'preserved', 0 ) ); ?>
	</div>
<?php } ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-random"></i>
			<?php echo plugin_lang_get( 'moduswechsel_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main">
		<span class="help-block"><?php echo plugin_lang_get( 'moduswechsel_intro' ); ?></span>
		<form class="form-inline" method="get" action="<?php echo plugin_page( 'moduswechsel' ); ?>">
			<input type="hidden" name="page" value="QualificationTracker/moduswechsel" />
			<label><?php echo plugin_lang_get( 'label_event_massnahme' ); ?></label>
			<select name="massnahme_id" class="input-sm">
				<option value="0">&ndash;</option>
			<?php foreach( $t_massnahmen as $t_m ) { ?>
				<option value="<?php echo (int)$t_m['id']; ?>" <?php echo $f_massnahme === (int)$t_m['id'] ? 'selected="selected"' : ''; ?>>
					<?php echo string_display_line( $t_m['schluessel'] . ' – ' . $t_m['bezeichnung'] . ' (' . plugin_lang_get( 'mode_' . $t_m['faelligkeitsmodus'] ) . ')' ); ?>
				</option>
			<?php } ?>
			</select>
			&nbsp;<i class="ace-icon fa fa-arrow-right"></i>&nbsp;
			<label><?php echo plugin_lang_get( 'label_modus' ); ?></label>
			<select name="modus" class="input-sm">
			<?php foreach( qt_catalog_modi() as $t_mo ) { ?>
				<option value="<?php echo $t_mo; ?>" <?php echo $f_modus === $t_mo ? 'selected="selected"' : ''; ?>><?php echo plugin_lang_get( 'mode_' . $t_mo ); ?></option>
			<?php } ?>
			</select>
			&nbsp;
			<label><?php echo plugin_lang_get( 'label_stichmonat' ); ?></label>
			<input type="number" name="stichmonat" min="1" max="12" class="input-sm" style="width:70px" value="<?php echo $f_stichmonat > 0 ? (int)$f_stichmonat : ''; ?>" />
			&nbsp;
			<button type="submit" class="btn btn-sm btn-white btn-round">
				<i class="ace-icon fa fa-search"></i> <?php echo plugin_lang_get( 'moduswechsel_simulate' ); ?>
			</button>
		</form>
	</div>

	<?php if( $t_sim !== null && $t_sim['massnahme'] !== false ) { ?>
	<div class="widget-toolbox padding-8 clearfix">
		<span class="pull-left" style="margin-top:4px">
			<?php echo sprintf( plugin_lang_get( 'moduswechsel_summary' ),
				count( $t_sim['affected'] ), (int)$t_sim['preserved'] ); ?>
		</span>
		<?php if( !empty( $t_sim['affected'] ) ) { ?>
		<form class="form-inline pull-right" method="post" action="<?php echo plugin_page( 'moduswechsel_apply' ); ?>"
			onsubmit="return confirm('<?php echo string_attribute( plugin_lang_get( 'moduswechsel_confirm' ) ); ?>');">
			<?php echo form_security_field( 'plugin_QualificationTracker_moduswechsel_apply' ); ?>
			<input type="hidden" name="massnahme_id" value="<?php echo $f_massnahme; ?>" />
			<input type="hidden" name="modus" value="<?php echo string_attribute( $f_modus ); ?>" />
			<input type="hidden" name="stichmonat" value="<?php echo (int)$f_stichmonat; ?>" />
			<button type="submit" class="btn btn-sm btn-primary btn-white btn-round">
				<i class="ace-icon fa fa-check"></i> <?php echo plugin_lang_get( 'moduswechsel_apply' ); ?>
			</button>
		</form>
		<?php } ?>
	</div>

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'event_col_status' ); ?></th>
				<th><?php echo plugin_lang_get( 'moduswechsel_col_old' ); ?></th>
				<th><?php echo plugin_lang_get( 'moduswechsel_col_new' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_sim['affected'] as $t_a ) { ?>
			<tr <?php echo $t_a['changed'] ? 'class="warning"' : ''; ?>>
				<td><?php echo string_display_line( (string)$t_a['personalnummer'] ); ?></td>
				<td><?php echo string_display_line( $t_a['person'] ); ?></td>
				<td><?php echo string_display_line( (string)$t_a['status'] ); ?></td>
				<td><?php echo $t_a['alt'] === null ? '&ndash;' : string_display_line( (string)$t_a['alt'] ); ?></td>
				<td><strong><?php echo $t_a['neu'] === null ? '&ndash;' : string_display_line( (string)$t_a['neu'] ); ?></strong></td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_sim['affected'] ) ) { ?>
			<tr><td colspan="5" class="center"><?php echo plugin_lang_get( 'moduswechsel_none' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>
	<?php } ?>
	</div>
</div>
</div>

<?php
layout_page_end();
