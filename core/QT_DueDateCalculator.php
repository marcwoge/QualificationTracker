<?php
/**
 * QualificationTracker – due-date calculator (F1.8 / F1.9).
 *
 * The single source of truth for every follow-up date in the plugin. It is a
 * pure, side-effect-free class: no database, no MantisBT session, no $_POST,
 * no clock reads except where a caller passes "today" in explicitly. All dates
 * are ISO strings (YYYY-MM-DD). This is what makes the four due-date modes and
 * the anchor mechanism fully unit-testable.
 *
 * The four modes (Fälligkeitsmodi):
 *  - rollierend   follow-up = base + interval
 *  - kalenderjahr follow-up = 31 Dec of the target year
 *  - stichmonat   follow-up = last day of a configured month in the target year
 *  - extern       no calculation (the date comes from the presented document)
 *
 * Anchor retention (F1.9): if the measure was performed on or before its target
 * date and within the grace window, the next cycle is computed from the target
 * date (soll_termin), not from the actual date – this stops the interval from
 * drifting forward over the years.
 *
 * @package   QualificationTracker
 * @author    Marc-Philipp Woge <marc.woge@googlemail.com>
 * @copyright Copyright (c) 2026 Marc-Philipp Woge
 * @license   MIT
 */

class QT_DueDateCalculator {

	/**
	 * Number of days in a month.
	 *
	 * @param int $p_year
	 * @param int $p_month 1-12
	 * @return int
	 */
	public static function days_in_month( $p_year, $p_month ) {
		$t_date = new DateTimeImmutable(
			sprintf( '%04d-%02d-01', $p_year, $p_month ), new DateTimeZone( 'UTC' ) );
		return (int)$t_date->format( 't' );
	}

	/**
	 * Add whole months to a date, clamping the day to the last valid day of the
	 * target month (no overflow into the following month).
	 *
	 * 31 Jan + 1 month = 28/29 Feb; 31 Mar + 12 months = 31 Mar;
	 * 29 Feb + 12 months = 28 Feb.
	 *
	 * @param string $p_date  ISO date.
	 * @param int    $p_months May be negative.
	 * @return string ISO date.
	 */
	public static function add_months( $p_date, $p_months ) {
		$t_parts = explode( '-', substr( $p_date, 0, 10 ) );
		$t_y = (int)$t_parts[0];
		$t_m = (int)$t_parts[1];
		$t_d = (int)$t_parts[2];

		# Floor division keeps the month in 1..12 for positive and negative
		# offsets alike (PHP's intdiv truncates toward zero, which would not).
		$t_month_index = $t_y * 12 + ( $t_m - 1 ) + (int)$p_months;
		$t_ny = (int)floor( $t_month_index / 12 );
		$t_nm = $t_month_index - $t_ny * 12 + 1;

		$t_nd = min( $t_d, self::days_in_month( $t_ny, $t_nm ) );
		return sprintf( '%04d-%02d-%02d', $t_ny, $t_nm, $t_nd );
	}

	/**
	 * Add whole days to a date (UTC, DST-free).
	 *
	 * @param string $p_date
	 * @param int    $p_days
	 * @return string ISO date.
	 */
	public static function add_days( $p_date, $p_days ) {
		$t_date = new DateTimeImmutable( substr( $p_date, 0, 10 ), new DateTimeZone( 'UTC' ) );
		$t_days = (int)$p_days;
		return $t_date->modify( ( $t_days >= 0 ? '+' : '' ) . $t_days . ' days' )->format( 'Y-m-d' );
	}

	/**
	 * Whole days between two dates (absolute value).
	 *
	 * @param string $p_from
	 * @param string $p_to
	 * @return int
	 */
	public static function day_diff( $p_from, $p_to ) {
		$t_from = new DateTimeImmutable( substr( $p_from, 0, 10 ), new DateTimeZone( 'UTC' ) );
		$t_to   = new DateTimeImmutable( substr( $p_to, 0, 10 ), new DateTimeZone( 'UTC' ) );
		return (int)$t_from->diff( $t_to )->days;
	}

	/**
	 * Last day of the year of the given date.
	 *
	 * @param string $p_date
	 * @return string
	 */
	public static function last_day_of_year( $p_date ) {
		return sprintf( '%04d-12-31', (int)substr( $p_date, 0, 4 ) );
	}

	/**
	 * Last day of a given month.
	 *
	 * @param int $p_year
	 * @param int $p_month 1-12
	 * @return string
	 */
	public static function last_day_of_month( $p_year, $p_month ) {
		return sprintf( '%04d-%02d-%02d', $p_year, $p_month, self::days_in_month( $p_year, $p_month ) );
	}

	/**
	 * The base date for the next cycle, applying anchor retention (F1.9).
	 *
	 * base = performed date, unless the measure was performed on or before its
	 * target date AND within the grace window, in which case base = target date.
	 *
	 * @param string      $p_durchgefuehrt_am Performed date (ISO).
	 * @param string|null $p_soll_termin      Target date (ISO) or null/empty.
	 * @param int         $p_karenz_tage      Grace window in days.
	 * @return string ISO date.
	 */
	public static function base_date( $p_durchgefuehrt_am, $p_soll_termin, $p_karenz_tage ) {
		$t_durch = substr( (string)$p_durchgefuehrt_am, 0, 10 );

		if( $p_soll_termin !== null && $p_soll_termin !== '' ) {
			$t_soll = substr( (string)$p_soll_termin, 0, 10 );
			# Only a timely (on/before target) performance within the grace
			# window keeps the anchor. A late performance shifts the cycle back.
			if( $t_durch <= $t_soll && self::day_diff( $t_durch, $t_soll ) <= (int)$p_karenz_tage ) {
				return $t_soll;
			}
		}

		return $t_durch;
	}

	/**
	 * Compute the next follow-up date.
	 *
	 * @param string      $p_modus               One of the four modes.
	 * @param string|null $p_durchgefuehrt_am    Performed date (ISO). Ignored for 'extern'.
	 * @param string|null $p_soll_termin         Target date (ISO) or null.
	 * @param int         $p_intervall_monate    Interval in months (>= 1 for computing modes).
	 * @param int         $p_karenz_tage         Grace window in days.
	 * @param int|null    $p_massnahme_stichmonat Reference month of the measure (1-12).
	 * @param int|null    $p_abteilung_stichmonat Per-department override (1-12), wins if set.
	 * @return string|null ISO date, or null for 'extern' / when it cannot be computed.
	 */
	public static function next_due(
		$p_modus,
		$p_durchgefuehrt_am,
		$p_soll_termin,
		$p_intervall_monate,
		$p_karenz_tage,
		$p_massnahme_stichmonat = null,
		$p_abteilung_stichmonat = null
	) {
		# 'extern' is not calculated – the date comes from the document.
		if( $p_modus === 'extern' ) {
			return null;
		}
		if( $p_durchgefuehrt_am === null || $p_durchgefuehrt_am === '' ) {
			return null;
		}

		$t_base = self::base_date( $p_durchgefuehrt_am, $p_soll_termin, $p_karenz_tage );
		$t_roh  = self::add_months( $t_base, (int)$p_intervall_monate );

		switch( $p_modus ) {
			case 'rollierend':
				return $t_roh;

			case 'kalenderjahr':
				return self::last_day_of_year( $t_roh );

			case 'stichmonat':
				$t_monat = ( $p_abteilung_stichmonat !== null && $p_abteilung_stichmonat !== '' )
					? (int)$p_abteilung_stichmonat
					: (int)$p_massnahme_stichmonat;
				if( $t_monat < 1 || $t_monat > 12 ) {
					return null;
				}
				return self::last_day_of_month( (int)substr( $t_roh, 0, 4 ), $t_monat );
		}

		return null;   # unknown mode
	}

	/**
	 * Initial target date for a person's first cycle (no predecessor exists yet).
	 *
	 * soll_termin = entry date + first-instruction deadline. For the
	 * 'kalenderjahr' mode it is additionally clamped to 31 Dec of the entry year
	 * when that falls earlier (a first instruction due next year would miss the
	 * calendar-year cycle).
	 *
	 * @param string $p_eintritt   Entry date (ISO).
	 * @param int    $p_frist_tage First-instruction deadline in days (default 14).
	 * @param string $p_modus      Due-date mode.
	 * @return string ISO date.
	 */
	public static function initial_soll_termin( $p_eintritt, $p_frist_tage, $p_modus ) {
		$t_soll = self::add_days( $p_eintritt, (int)$p_frist_tage );

		if( $p_modus === 'kalenderjahr' ) {
			$t_year_end = sprintf( '%04d-12-31', (int)substr( $p_eintritt, 0, 4 ) );
			if( $t_year_end < $t_soll ) {
				$t_soll = $t_year_end;
			}
		}

		return $t_soll;
	}
}
