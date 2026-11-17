<?php
/* Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/dolitrace_weather.lib.php
 * \ingroup dolitrace
 * \brief   Weather conditions of treatments and operations, from doliField (only when doliField is enabled)
 *
 * The conditions of the plot of the production plan in the period of the record are read from the weather point of
 * doliField (observed, reanalysis or the last forecast of each hour). They are proposed on the draft and copied as
 * text in the record at the validation when the field is empty: the validated record does not depend on data that
 * change later (a forecast replaced by the reanalysis).
 */

/**
 * Tell if the weather of doliField can be used
 *
 * @return	bool
 */
function dolitraceWeatherAvailable(): bool
{
	return isModEnabled('dolifield') && dol_include_once('/dolifield/class/dolifieldweather.class.php') && class_exists('DoliFieldWeather');
}

/**
 * Weather conditions of the plot of a production plan in a period
 *
 * @param	DoliDB	$db				Database handler
 * @param	int		$fk_cropplan	Id of the production plan
 * @param	int		$from			Start (timestamp)
 * @param	int		$to				End (timestamp, 0 = one hour)
 * @return	?array<string,mixed>	Conditions (DoliFieldWeather::getConditions()), null if not available
 */
function dolitraceWeatherConditions(DoliDB $db, int $fk_cropplan, int $from, int $to = 0): ?array
{
	if ($fk_cropplan <= 0 || $from <= 0 || !dolitraceWeatherAvailable()) {
		return null;
	}
	$resql = $db->query("SELECT fk_plot FROM ".$db->prefix()."dolitrace_cropplan WHERE rowid = ".((int) $fk_cropplan));
	$obj = ($resql ? $db->fetch_object($resql) : null);
	if (!$obj || !($obj->fk_plot > 0)) {
		return null;
	}
	$weather = new DoliFieldWeather($db);
	return $weather->getConditions((int) $obj->fk_plot, $from, $to);
}

/**
 * Text of the weather conditions to copy in a record
 *
 * @param	?array<string,mixed>	$conditions		Conditions (dolitraceWeatherConditions())
 * @param	int						$maxlength		Size of the field
 * @return	string									'' if no conditions
 */
function dolitraceWeatherText(?array $conditions, int $maxlength): string
{
	if ($conditions === null || !class_exists('DoliFieldWeather')) {
		return '';
	}
	return DoliFieldWeather::formatConditions($conditions, $maxlength);
}

/**
 * Warnings on the weather of a treatment of plant protection (wind, rain, temperature over the thresholds of doliField)
 *
 * @param	?array<string,mixed>	$conditions		Conditions (dolitraceWeatherConditions())
 * @return	string[]								Translated warnings
 */
function dolitraceWeatherWarnings(?array $conditions): array
{
	if ($conditions === null || !class_exists('DoliFieldWeather')) {
		return array();
	}
	return DoliFieldWeather::conditionWarnings($conditions);
}
