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

/**
 * Number of a field of the weather observed (typed with comma or point), null if empty
 *
 * @param	mixed	$value	Value
 * @return	?float
 */
function dolitraceWeatherObsNumber($value): ?float
{
	if ($value === null || $value === '') {
		return null;
	}
	if (is_string($value)) {
		$value = str_replace(',', '.', trim($value));
		if ($value === '' || !is_numeric($value)) {
			return null;
		}
	}
	return (float) $value;
}

/**
 * Check the weather observed in the field of a record and complete it: without a time of the reading the start of the
 * record; without values no time
 *
 * @param	object		$object		Record (properties weather_obs_date, weather_obs_temp, weather_obs_wind, weather_obs_rh)
 * @param	mixed		$start		Start of the record (timestamp or string)
 * @param	mixed		$end		End of the record (timestamp or string, empty = start)
 * @return	string					Error, '' if OK
 */
function dolitraceWeatherObsCheck($object, $start, $end): string
{
	global $langs;

	$langs->load('dolitrace@dolitrace');
	$time = function ($value) {
		if ($value === null || $value === '') {
			return null;
		}
		return (is_numeric($value) ? (int) $value : (int) dol_stringtotime($value));
	};
	$limits = array('weather_obs_temp' => array(-30, 55, 'WeatherObsTemp'), 'weather_obs_wind' => array(0, 60, 'WeatherObsWind'), 'weather_obs_rh' => array(0, 100, 'WeatherObsRh'));
	$values = 0;
	foreach ($limits as $field => $limit) {
		if (is_string($object->$field) && trim($object->$field) !== '' && !is_numeric(str_replace(',', '.', trim($object->$field)))) {
			return $langs->transnoentities('ErrorWeatherObsValue', $langs->transnoentities($limit[2]), $limit[0], $limit[1]);
		}
		$value = dolitraceWeatherObsNumber($object->$field);
		if ($value !== null && ($value < $limit[0] || $value > $limit[1])) {
			return $langs->transnoentities('ErrorWeatherObsValue', $langs->transnoentities($limit[2]), $limit[0], $limit[1]);
		}
		$object->$field = $value;
		$values += ($value !== null ? 1 : 0);
	}
	$start = $time($start);
	$end = $time($end);
	$obsdate = $time($object->weather_obs_date);
	if (!$values) {
		$object->weather_obs_date = null;
		return '';
	}
	if (empty($obsdate)) {
		$obsdate = $start;
	}
	// Reading near the record: from 12 hours before the start to 12 hours after the end, not in the future
	if (!empty($start) && !empty($obsdate)) {
		$last = (!empty($end) ? $end : $start) + 12 * 3600;
		if ($obsdate < $start - 12 * 3600 || $obsdate > $last || $obsdate > dol_now() + 3600) {
			return $langs->transnoentities('ErrorWeatherObsDate', dol_print_date($obsdate, 'dayhour', 'tzuserrel'));
		}
	}
	$object->weather_obs_date = $obsdate;
	return '';
}

/**
 * Warnings on the weather observed in the field with a treatment of plant protection: wind and temperature over the
 * thresholds of doliField (5 m/s and 30 degrees C without doliField)
 *
 * @param	object	$object		Record
 * @return	string[]			Translated warnings
 */
function dolitraceWeatherObsWarnings($object): array
{
	global $langs;

	$langs->load('dolitrace@dolitrace');
	$limit = (dolitraceWeatherAvailable() ? DoliFieldWeather::thresholds() : array('wind' => 5.0, 'temp' => 30.0));
	$warnings = array();
	$wind = dolitraceWeatherObsNumber($object->weather_obs_wind ?? null);
	if ($wind !== null && $wind > $limit['wind']) {
		$warnings[] = $langs->transnoentities('WarningWeatherObsWind', price($wind, 0, $langs, 0, 0, 1), price($limit['wind'], 0, $langs, 0, 0, 1));
	}
	$temp = dolitraceWeatherObsNumber($object->weather_obs_temp ?? null);
	if ($temp !== null && $temp > $limit['temp']) {
		$warnings[] = $langs->transnoentities('WarningWeatherObsTemp', price($temp, 0, $langs, 0, 0, 1), price($limit['temp'], 0, $langs, 0, 0, 1));
	}
	return $warnings;
}

/**
 * Short text of the weather observed in the field ("12/05/2026 08:30: 18 °C, wind 2.5 m/s, RH 60%")
 *
 * @param	object	$object		Record
 * @return	string				'' without values
 */
function dolitraceWeatherObsText($object): string
{
	global $langs;

	$langs->load('dolitrace@dolitrace');
	$parts = array();
	$temp = dolitraceWeatherObsNumber($object->weather_obs_temp ?? null);
	$wind = dolitraceWeatherObsNumber($object->weather_obs_wind ?? null);
	$rh = dolitraceWeatherObsNumber($object->weather_obs_rh ?? null);
	if ($temp !== null) {
		$parts[] = price($temp, 0, $langs, 0, 0, 1).' °C';
	}
	if ($wind !== null) {
		$parts[] = $langs->transnoentities('WeatherObsWindShort', price($wind, 0, $langs, 0, 0, 1));
	}
	if ($rh !== null) {
		$parts[] = $langs->transnoentities('WeatherObsRhShort', price($rh, 0, $langs, 0, 0, 0));
	}
	if (!count($parts)) {
		return '';
	}
	return (!empty($object->weather_obs_date) ? dol_print_date($object->weather_obs_date, 'dayhour', 'tzuserrel').': ' : '').implode(', ', $parts);
}
