<?php
/**
 * QualificationTracker – person register list (F1.4).
 *
 * Reachable via Manage → QualificationTracker → Persons. Lists persons with an
 * optional department filter and edit/delete actions.
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
require_api( 'user_api.php' );

auth_reauthenticate();
plugin_require_api( 'core/QT_Access.php' );
qt_access_ensure( 'edit' );

plugin_require_api( 'core/QT_Person.php' );

$f_abteilung = gpc_get_string( 'abteilung', '' );
$t_msg       = gpc_get_string( 'msg', '' );

$t_personen    = qt_person_load_all( $f_abteilung );
$t_abteilungen = qt_person_distinct_abteilungen();

layout_page_header( plugin_lang_get( 'person_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if( $t_msg === 'referenced' ) { ?>
	<div class="alert alert-warning"><?php echo plugin_lang_get( 'person_msg_referenced' ); ?></div>
<?php } else if( $t_msg === 'deleted' ) { ?>
	<div class="alert alert-success"><?php echo plugin_lang_get( 'person_msg_deleted' ); ?></div>
<?php } ?>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-users"></i>
			<?php echo plugin_lang_get( 'person_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-toolbox padding-8 clearfix">
		<a class="btn btn-primary btn-white btn-round btn-sm"
			href="<?php echo plugin_page( 'person_edit' ); ?>">
			<i class="ace-icon fa fa-plus"></i>
			<?php echo plugin_lang_get( 'btn_new_person' ); ?>
		</a>

		<form class="form-inline pull-right" method="get" action="<?php echo plugin_page( 'person' ); ?>">
			<input type="hidden" name="page" value="QualificationTracker/person" />
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

	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_personalnummer' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_name' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_person_typ' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_abteilung' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_eintritt' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_austritt' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_vorgesetzter' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_jugendschutz' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_aktiv' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_aktionen' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach( $t_personen as $t_p ) {
			$t_id = (int)$t_p['id'];
			$t_sup = (int)$t_p['vorgesetzter_user_id'];
			$t_sup_name = ( $t_sup > 0 && user_exists( $t_sup ) ) ? user_get_name( $t_sup ) : '';
		?>
			<tr>
				<td><?php echo $t_p['personalnummer'] === null ? '&ndash;' : string_display_line( $t_p['personalnummer'] ); ?></td>
				<td><?php echo string_display_line( trim( $t_p['nachname'] . ', ' . $t_p['vorname'], ', ' ) ); ?></td>
				<td><?php echo string_display_line( plugin_lang_get( 'person_typ_' . $t_p['typ'] ) )
					. ( $t_p['fremdfirma'] !== null ? ' <span class="grey">(' . string_display_line( $t_p['fremdfirma'] ) . ')</span>' : '' ); ?></td>
				<td><?php echo string_display_line( $t_p['abteilung'] ); ?></td>
				<td><?php echo $t_p['eintritt'] === null ? '&ndash;' : string_display_line( $t_p['eintritt'] ); ?></td>
				<td><?php echo $t_p['austritt'] === null ? '&ndash;' : string_display_line( $t_p['austritt'] ); ?></td>
				<td><?php echo $t_sup_name === '' ? '&ndash;' : string_display_line( $t_sup_name ); ?></td>
				<td class="center"><?php echo $t_p['verkuerztes_intervall_bis'] === null ? '' : string_display_line( $t_p['verkuerztes_intervall_bis'] ); ?></td>
				<td class="center"><?php echo $t_p['aktiv'] ? '<i class="ace-icon fa fa-check green"></i>' : '<i class="ace-icon fa fa-ban grey"></i>'; ?></td>
				<td class="center">
					<a class="btn btn-xs btn-primary btn-white btn-round"
						href="<?php echo plugin_page( 'person_edit' ); ?>&amp;id=<?php echo $t_id; ?>">
						<i class="ace-icon fa fa-edit"></i> <?php echo plugin_lang_get( 'btn_edit' ); ?>
					</a>
					<form class="form-inline" style="display:inline"
						method="post" action="<?php echo plugin_page( 'person_delete' ); ?>"
						onsubmit="return confirm('<?php echo string_attribute( plugin_lang_get( 'confirm_delete_person' ) ); ?>');">
						<?php echo form_security_field( 'plugin_QualificationTracker_person_delete' ); ?>
						<input type="hidden" name="id" value="<?php echo $t_id; ?>" />
						<button type="submit" class="btn btn-xs btn-danger btn-white btn-round">
							<i class="ace-icon fa fa-trash-o"></i> <?php echo plugin_lang_get( 'btn_delete' ); ?>
						</button>
					</form>
				</td>
			</tr>
		<?php } ?>
		<?php if( empty( $t_personen ) ) { ?>
			<tr><td colspan="10" class="center"><?php echo plugin_lang_get( 'no_personen' ); ?></td></tr>
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
