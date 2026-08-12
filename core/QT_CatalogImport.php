<?php
/**
 * QualificationTracker – example-catalogue import (F1.7).
 *
 * Ships a starter catalogue as YAML and imports it into qt_massnahme, reusing
 * the catalogue validation (F1.2) and prerequisite logic (F1.3).
 *
 * The plugin must run from a plain `git clone` with no Composer packages, so
 * instead of a YAML library there is a tiny reader for exactly the restricted
 * format the bundled file uses: a sequence of flat mappings with scalar values.
 * qt_yaml_parse_simple() is pure and unit-tested.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

/**
 * Parse a scalar YAML value: strip surrounding quotes, recognise booleans and
 * integers, otherwise return the string. Pure.
 *
 * @param string $p_value
 * @return string|int|bool
 */
function qt_yaml_scalar( $p_value ) {
	$t_value = trim( $p_value );
	if( $t_value === '' ) {
		return '';
	}

	$t_first = $t_value[0];
	if( ( $t_first === '"' || $t_first === "'" ) && substr( $t_value, -1 ) === $t_first && strlen( $t_value ) >= 2 ) {
		return substr( $t_value, 1, -1 );
	}

	$t_lower = strtolower( $t_value );
	if( $t_lower === 'true' ) {
		return true;
	}
	if( $t_lower === 'false' ) {
		return false;
	}
	if( preg_match( '/^-?\d+$/', $t_value ) ) {
		return (int)$t_value;
	}

	return $t_value;
}

/**
 * Parse the restricted YAML used by the bundled catalogue: a sequence of flat
 * mappings. A line starting with "- " opens a new item and carries its first
 * key; indented "key: value" lines add to the current item. Blank lines and
 * comment lines (#) are ignored. Pure function.
 *
 * @param string $p_text
 * @return array List of associative arrays.
 */
function qt_yaml_parse_simple( $p_text ) {
	$t_rows = array();
	$t_current = null;

	foreach( preg_split( '/\r\n|\r|\n/', $p_text ) as $t_line ) {
		$t_stripped = ltrim( rtrim( $t_line ) );
		if( $t_stripped === '' || $t_stripped[0] === '#' ) {
			continue;
		}

		if( strncmp( $t_stripped, '- ', 2 ) === 0 ) {
			if( $t_current !== null ) {
				$t_rows[] = $t_current;
			}
			$t_current = array();
			qt_yaml_apply_kv( $t_current, substr( $t_stripped, 2 ) );
		} else if( $t_current !== null ) {
			qt_yaml_apply_kv( $t_current, $t_stripped );
		}
	}

	if( $t_current !== null ) {
		$t_rows[] = $t_current;
	}

	return $t_rows;
}

/**
 * Split a "key: value" fragment and store the scalar in the item. Pure.
 *
 * @param array  $p_item By reference.
 * @param string $p_fragment
 * @return void
 */
function qt_yaml_apply_kv( array &$p_item, $p_fragment ) {
	$t_pos = strpos( $p_fragment, ':' );
	if( $t_pos === false ) {
		return;
	}
	$t_key = trim( substr( $p_fragment, 0, $t_pos ) );
	if( $t_key === '' ) {
		return;
	}
	$p_item[$t_key] = qt_yaml_scalar( substr( $p_fragment, $t_pos + 1 ) );
}

/**
 * Path to the bundled example catalogue.
 *
 * @return string
 */
function qt_catalog_seed_path() {
	return dirname( __DIR__ ) . '/files/beispielkatalog.yaml';
}

/**
 * Import parsed catalogue rows into qt_massnahme.
 *
 * Existing measures (matched by key) are skipped unless $p_overwrite is set.
 * Prerequisites (comma-separated keys in the `vorbedingungen` field) are
 * resolved and applied after all measures are present.
 *
 * @param array $p_rows      Parsed rows.
 * @param bool  $p_overwrite Update existing measures instead of skipping.
 * @return array Summary: created, updated, skipped, errors[].
 */
function qt_catalog_import( array $p_rows, $p_overwrite = false ) {
	$t_summary = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
	$t_to_link = array();

	foreach( $p_rows as $t_row ) {
		# Extract prerequisites (applied after all measures exist).
		$t_prereqs = array();
		if( isset( $t_row['vorbedingungen'] ) ) {
			foreach( explode( ',', (string)$t_row['vorbedingungen'] ) as $t_key ) {
				$t_key = trim( $t_key );
				if( $t_key !== '' ) {
					$t_prereqs[] = $t_key;
				}
			}
			unset( $t_row['vorbedingungen'] );
		}

		# Sensible defaults for fields the file may omit.
		$t_row += array(
			'aktiv'               => true,
			'wiederkehrend'       => true,
			'sicherheitsrelevant' => false,
			'karenz_tage'         => plugin_config_get( 'karenz_tage_default' ),
			'vorlaufzeit_tage'    => 0,
			'stichmonat'          => '',
			'intervall_monate'    => '',
			'rechtsgrundlage'     => '',
			'nachweisart'         => '',
		);

		$t_errors = qt_massnahme_validate( $t_row );
		if( !empty( $t_errors ) ) {
			$t_summary['errors'][] = array(
				'schluessel' => isset( $t_row['schluessel'] ) ? (string)$t_row['schluessel'] : '?',
				'errors'     => $t_errors,
			);
			continue;
		}

		$t_existing = qt_massnahme_get_by_schluessel( $t_row['schluessel'], 0 );
		$t_id = 0;
		$t_process_prereqs = false;

		if( $t_existing !== false ) {
			if( $p_overwrite ) {
				qt_massnahme_update( (int)$t_existing['id'], $t_row );
				$t_id = (int)$t_existing['id'];
				$t_summary['updated']++;
				$t_process_prereqs = true;
			} else {
				$t_summary['skipped']++;
			}
		} else {
			$t_id = qt_massnahme_create( $t_row );
			$t_summary['created']++;
			$t_process_prereqs = true;
		}

		if( $t_process_prereqs && $t_id > 0 ) {
			$t_to_link[(string)$t_row['schluessel']] = array( $t_id, $t_prereqs );
		}
	}

	# Resolve prerequisites now that every measure exists.
	foreach( $t_to_link as $t_info ) {
		list( $t_id, $t_prereqs ) = $t_info;
		$t_ids = array();
		foreach( $t_prereqs as $t_key ) {
			$t_pm = qt_massnahme_get_by_schluessel( $t_key, 0 );
			if( $t_pm !== false ) {
				$t_ids[] = (int)$t_pm['id'];
			}
		}
		qt_vorbedingung_set( $t_id, $t_ids );
	}

	return $t_summary;
}
