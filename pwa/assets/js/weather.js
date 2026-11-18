/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * Weather of doliField on the phone (data downloaded with the rest, shown also without network):
 * forecast of the plot, windows for the treatments, conditions expected at the hour of a record.
 *
 * The register keeps the weather copied by the office at the validation (best data of that moment): what the app
 * shows here is only a help in the field.
 */

import { t, fmtNumber } from './i18n.js';

const KIND_FORECAST = 3;

/** Weather point of a plot, null if none */
export function weatherOf(W, plotId) {
	if (!W || !W.plots || !W.stations) {
		return null;
	}
	const id = W.plots[plotId];
	return id ? W.stations[id] || null : null;
}

/** Icon of a symbol of MET Norway */
function symbolIcon(symbol) {
	const s = String(symbol || '');
	if (s.includes('thunder')) {
		return '⛈️';
	}
	if (s.includes('snow') || s.includes('sleet')) {
		return '🌨️';
	}
	if (s.includes('rain')) {
		return '🌧️';
	}
	if (s.includes('fog')) {
		return '🌫️';
	}
	if (s.startsWith('partlycloudy')) {
		return '⛅';
	}
	if (s.startsWith('fair')) {
		return '🌤️';
	}
	if (s.startsWith('cloudy')) {
		return '☁️';
	}
	if (s.startsWith('clearsky')) {
		return '☀️';
	}
	return '';
}

function n(value, decimals = 1) {
	return value === null || value === undefined ? '-' : fmtNumber(value, decimals);
}

function dayLabel(iso) {
	const d = new Date(iso + 'T12:00:00');
	return d.toLocaleDateString(document.documentElement.lang || undefined, { weekday: 'short', day: 'numeric', month: 'short' });
}

function hourLabel(seconds, withDay) {
	const d = new Date(seconds * 1000);
	const hm = d.toLocaleTimeString(document.documentElement.lang || undefined, { hour: '2-digit', minute: '2-digit' });
	return withDay ? d.toLocaleDateString(document.documentElement.lang || undefined, { weekday: 'short', day: 'numeric' }) + ' ' + hm : hm;
}

function todayIso() {
	const d = new Date();
	return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

/** Source of the data: forecast of MET Norway of the run, with the age */
function sourceLine(st) {
	if (!st.run) {
		return t('WeatherSourcePoint', st.label);
	}
	const hours = (Date.now() - new Date(st.run).getTime()) / 3600000;
	return t('WeatherSource', new Date(st.run).toLocaleString(document.documentElement.lang || undefined, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }), st.label) + (hours > 24 ? ' ' + t('WeatherOld') : '');
}

/**
 * Card of the weather of a plot: next days and windows for the treatments
 *
 * @param {Function} h  Builder of the elements
 * @param {object} W  Weather data
 * @param {number} plotId  Id of the plot
 * @returns {HTMLElement|null}
 */
export function weatherCard(h, W, plotId) {
	const st = weatherOf(W, plotId);
	if (!st) {
		return null;
	}
	const today = todayIso();
	const days = (st.days || []).filter((d) => d.date >= today).slice(0, 3);
	const now = Date.now() / 1000;
	const windows = (st.windows || []).filter((w) => w[1] > now).map((w) => [Math.max(w[0], Math.floor(now / 3600) * 3600), w[1]]).filter((w) => w[1] - w[0] >= 3600);
	return h('div.card.weather', null,
		h('div.card-title', null, t('Weather')),
		days.length
			? h('table.weather-days', null, h('tbody', null, days.map((d) => h('tr', null,
				h('td.weather-icon', null, symbolIcon(d.symbol)),
				h('td', null, dayLabel(d.date)),
				h('td.num', null, n(d.tmin, 0) + '° / ' + n(d.tmax, 0) + '°'),
				h('td.num', null, '💧 ' + n(d.precip) + ' mm'),
				h('td.num', null, '💨 ' + n(d.wind_max) + ' m/s'),
			))))
			: h('p.empty', null, t('WeatherNoForecast')),
		h('h3', null, t('TreatmentWindows')),
		windows.length
			? h('ul.records', null, windows.map((w) => h('li', null, hourLabel(w[0], true) + ' – ' + hourLabel(w[1], new Date(w[0] * 1000).getDate() !== new Date(w[1] * 1000).getDate()))))
			: h('p.empty', null, t('NoTreatmentWindows')),
		h('div.item-sub', null, t('TreatmentWindowsHelp', n(W.thresholds.wind), n(W.thresholds.temp, 0), n(W.thresholds.rain), W.thresholds.rainhours)),
		h('div.item-sub', null, sourceLine(st)),
	);
}

/**
 * Conditions expected in a period, as DoliFieldWeather::getConditions(): temperature and wind of the hours, rain of
 * the period and of the hours after it
 *
 * @param {object} st  Weather point
 * @param {object} limit  Thresholds of doliField
 * @param {number} from  Start (seconds)
 * @param {number} to  End (seconds, 0 = one hour)
 * @returns {object|null}  null if no data for the period
 */
export function conditions(st, limit, from, to) {
	const h0 = Math.floor(from / 3600) * 3600;
	const h1 = Math.max(h0 + 3600, Math.ceil(Math.max(from, to || 0) / 3600) * 3600);
	const end = h1 + limit.rainhours * 3600;
	const temps = [];
	const winds = [];
	let precip = null;
	let after = null;
	let coveredTo = h1;
	let forecast = false;
	for (const [time, kind, temp, wind, rain, rainHours] of st.hours || []) {
		if (time >= h0 && time <= h1) {
			if (temp !== null) {
				temps.push(temp);
				forecast = forecast || kind === KIND_FORECAST;
			}
			if (wind !== null) {
				winds.push(wind);
			}
		}
		if (rain === null || time <= h0) {
			continue;
		}
		if (time <= h1) {
			precip = (precip || 0) + rain;
		} else if (time - rainHours * 3600 < end) {
			after = (after || 0) + rain;
			coveredTo = Math.max(coveredTo, time);
		}
	}
	// Steps of 6 hours: the nearest within 3 hours
	if (!temps.length) {
		let best = null;
		for (const hour of st.hours || []) {
			const distance = hour[0] < h0 ? h0 - hour[0] : Math.max(0, hour[0] - h1);
			if (hour[2] !== null && distance <= 3 * 3600 && (!best || distance < best[0])) {
				best = [distance, hour];
			}
		}
		if (!best) {
			return null;
		}
		temps.push(best[1][2]);
		forecast = best[1][1] === KIND_FORECAST;
		if (best[1][3] !== null) {
			winds.push(best[1][3]);
		}
	}
	if (coveredTo < end) {
		after = null;
	}
	return {
		temp_min: Math.min(...temps),
		temp_max: Math.max(...temps),
		wind_max: winds.length ? Math.max(...winds) : null,
		precip,
		precip_after: after,
		after_hours: limit.rainhours,
		forecast,
	};
}

/** Warnings of a treatment of plant protection, as DoliFieldWeather::conditionWarnings() */
export function conditionWarnings(c, limit) {
	const warnings = [];
	if (c.wind_max !== null && c.wind_max > limit.wind) {
		warnings.push(t('WWWind', n(c.wind_max), n(limit.wind)));
	}
	const rain = (c.precip || 0) + (c.precip_after || 0);
	if (rain >= limit.rain) {
		warnings.push(t('WWRain', n(rain), c.after_hours));
	}
	if (c.temp_max !== null && c.temp_max > limit.temp) {
		warnings.push(t('WWTemp', n(c.temp_max), n(limit.temp, 0)));
	}
	return warnings;
}

/**
 * Box of the weather expected at the hour of the form
 *
 * @param {Function} h  Builder of the elements
 * @param {object} W  Weather data
 * @param {number} plotId  Id of the plot
 * @returns {{box: HTMLElement, refresh: Function}|null}  refresh(values of the form, with warnings)
 */
export function formWeather(h, W, plotId) {
	const st = weatherOf(W, plotId);
	if (!st) {
		return null;
	}
	const box = h('div.note.info.weather-form', { hidden: true });
	const refresh = (values, withWarnings) => {
		const from = values.date ? new Date(values.date).getTime() / 1000 : NaN;
		const to = values.date_end ? new Date(values.date_end).getTime() / 1000 : 0;
		const c = Number.isFinite(from) ? conditions(st, W.thresholds, from, Number.isFinite(to) ? to : 0) : null;
		if (!c) {
			box.hidden = true;
			return;
		}
		const temp = Math.round(c.temp_max - c.temp_min) >= 1 ? n(c.temp_min) + '–' + n(c.temp_max) + ' °C' : n(c.temp_min) + ' °C';
		const parts = [temp];
		if (c.wind_max !== null) {
			parts.push(t('WeatherWindUpTo', n(c.wind_max)));
		}
		parts.push(t('WeatherRain', n(c.precip || 0)) + (c.precip_after !== null ? ' ' + t('WeatherRainAfter', n(c.precip_after), c.after_hours) : ''));
		const warnings = withWarnings ? conditionWarnings(c, W.thresholds) : [];
		box.className = 'note weather-form ' + (warnings.length ? 'warn' : 'info');
		box.replaceChildren(
			h('strong', null, c.forecast ? t('WeatherExpected') : t('WeatherAtHour')),
			' ', parts.join(', '),
			warnings.length ? h('ul.warnings', null, warnings.map((w) => h('li', null, w))) : '',
			h('div.item-sub', null, sourceLine(st) + ' ' + t('WeatherRegisterNote')),
		);
		box.hidden = false;
	};
	return { box, refresh };
}
