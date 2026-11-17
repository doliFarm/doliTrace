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
 * Field app of doliTrace, phase P1: plots and their production plans offline, QR code of the signs, nearest plot.
 * No framework and no CDN: the app must start without network.
 */

import { kvGet, kvSet, kvClear } from './store.js';
import { API_VERSION, LOGIN_URL, OfflineError, LoginError, ApiError, getSession, getData } from './api.js';
import { t, setLang, fmtDate, fmtNumber } from './i18n.js';

export const APP_VERSION = '1.0.1';

/** Days without update after which the app warns (Safari deletes the data of the apps not used for about 7 days) */
const OLD_DATA_DAYS = 5;

const state = {
	session: null,	// answer of action=session
	data: null,		// answer of action=data
	syncedAt: null,	// ISO date of the last update
	syncing: false,
	problem: '',	// login, forbidden, api, or a message
	toast: '',
	search: '',
};

let cleanup = null;
const view = document.getElementById('view');
const statusBar = document.getElementById('status');

/* ---------- Small DOM helpers: text is always set as text, never as HTML ---------- */

/**
 * Create an element
 *
 * @param {string} tag Tag with optional classes (div.card.wide)
 * @param {Object|null} [attrs] Attributes, on* for events
 * @param {...any} children Strings, numbers, elements, arrays, null
 * @returns {HTMLElement}
 */
function h(tag, attrs, ...children) {
	const [name, ...classes] = tag.split('.');
	const el = document.createElement(name);
	if (classes.length) {
		el.className = classes.join(' ');
	}
	for (const [key, value] of Object.entries(attrs || {})) {
		if (value === null || value === undefined || value === false) {
			continue;
		}
		if (key.startsWith('on') && typeof value === 'function') {
			el.addEventListener(key.slice(2), value);
		} else if (key === 'value') {
			el.value = value;
		} else {
			el.setAttribute(key, value === true ? '' : String(value));
		}
	}
	const add = (child) => {
		if (child === null || child === undefined || child === false) {
			return;
		}
		if (Array.isArray(child)) {
			child.forEach(add);
		} else if (child instanceof Node) {
			el.appendChild(child);
		} else {
			el.appendChild(document.createTextNode(String(child)));
		}
	};
	children.forEach(add);
	return el;
}

/**
 * Show a view
 *
 * @param {...any} nodes
 */
function show(...nodes) {
	if (cleanup) {
		cleanup();
		cleanup = null;
	}
	view.replaceChildren(...nodes.flat().filter((n) => n));
	window.scrollTo(0, 0);
}

function toast(text) {
	state.toast = text;
	renderStatus();
	setTimeout(() => {
		if (state.toast === text) {
			state.toast = '';
			renderStatus();
		}
	}, 3500);
}

/* ---------- Data ---------- */

const plots = () => (state.data && state.data.plots) || [];
const plansOf = (plotId) => ((state.data && state.data.cropplans) || []).filter((p) => p.plot_id === plotId);
const byPlan = (list, planId) => ((state.data && state.data[list]) || []).filter((r) => r.cropplan_id === planId);

/** Current crop of a plot: the open plan that started last */
function cropOf(plot) {
	const plans = plansOf(plot.id);
	if (!plans.length) {
		return '';
	}
	return [plans[0].crop, plans[0].variety].filter(Boolean).join(' - ');
}

/** Today as 2026-11-18, in the time zone of the phone */
function today() {
	return isoLocal(new Date().toISOString()).slice(0, 10);
}

/** Pre-harvest intervals of a plan still running today */
function safetyOf(planId) {
	const day = today();
	return ((state.data && state.data.safety) || []).filter((s) => s.cropplan_id === planId && s.until >= day);
}

function findPlotByRef(ref) {
	const key = String(ref || '').trim().toUpperCase();
	return plots().find((p) => String(p.ref).toUpperCase() === key) || null;
}

/** Reference of a plot read from a QR code: address of the card (?ref=...) or the reference itself */
function refFromQr(text) {
	const value = String(text || '').trim();
	try {
		const ref = new URL(value).searchParams.get('ref');
		if (ref) {
			return ref;
		}
	} catch (e) {
		// not an address
	}
	return value;
}

function daysSince(iso) {
	return iso ? Math.floor((Date.now() - new Date(iso).getTime()) / 86400000) : null;
}

/** ISO date (UTC) as local date and time 2026-11-18T08:30 */
function isoLocal(iso) {
	const d = new Date(iso);
	const p = (n) => String(n).padStart(2, '0');
	return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
}

async function loadLocal() {
	try {
		state.session = (await kvGet('session')) || null;
		state.data = (await kvGet('data')) || null;
		state.syncedAt = (await kvGet('syncedAt')) || null;
	} catch (e) {
		state.problem = String(e.message || e);
	}
	setLang(state.session && state.session.user ? state.session.user.lang : undefined);
}

/**
 * Update the data from the server. Without network or without session the data of the phone stay as they are.
 *
 * @param {boolean} [manual] Asked by the user
 */
async function sync(manual = false) {
	if (state.syncing) {
		return;
	}
	state.syncing = true;
	renderStatus();
	try {
		const session = await getSession();
		// Another user on the same phone: the data of the previous one are deleted
		if (state.session && state.session.user && state.session.user.id !== session.user.id) {
			await kvClear();
			state.data = null;
			state.syncedAt = null;
		}
		const data = await getData();
		state.session = session;
		state.data = data;
		state.syncedAt = new Date().toISOString();
		await kvSet('session', session);
		await kvSet('data', data);
		await kvSet('syncedAt', state.syncedAt);
		setLang(session.user.lang);
		state.problem = (session.api > API_VERSION ? 'api' : '');
		// Ask the browser to keep the data (Android; Safari decides by itself)
		if (navigator.storage && navigator.storage.persist) {
			navigator.storage.persist().catch(() => {});
		}
		if (manual) {
			toast(t('SyncDone'));
		}
	} catch (e) {
		if (e instanceof LoginError) {
			state.problem = 'login';
		} else if (e instanceof ApiError && e.code === 'forbidden') {
			state.problem = 'forbidden';
		} else if (e instanceof OfflineError) {
			if (manual) {
				toast(t('SyncFailed', t('Offline')));
			}
		} else {
			state.problem = String(e.message || e);
		}
	} finally {
		state.syncing = false;
		renderStatus();
		route();
	}
}

/* ---------- Status bar and warnings ---------- */

function renderStatus() {
	const online = navigator.onLine;
	const line = h('div.status-line', null,
		h('span.dot.' + (online ? 'on' : 'off'), { title: online ? t('Online') : t('Offline') }),
		h('span.status-text', null, state.syncing ? t('Syncing') : (state.syncedAt ? t('SyncedAt', fmtDate(isoLocal(state.syncedAt))) : t('NeverSynced'))),
		h('button.btn.small', { type: 'button', onclick: () => sync(true), disabled: state.syncing || !online }, t('Refresh')),
	);
	const notes = [];
	const days = daysSince(state.syncedAt);
	if (days !== null && days >= OLD_DATA_DAYS) {
		notes.push(h('div.note.warn', null, t('OldData', days)));
	}
	if (state.problem === 'login') {
		notes.push(h('div.note.warn', null, t('LoginNeeded'), ' ', h('a.btn.small', { href: LOGIN_URL }, t('Login'))));
	} else if (state.problem === 'forbidden') {
		notes.push(h('div.note.error', null, t('Forbidden')));
	} else if (state.problem === 'api') {
		notes.push(h('div.note.warn', null, t('ApiNewer')));
	} else if (state.problem) {
		notes.push(h('div.note.error', null, t('SyncFailed', state.problem)));
	}
	if (state.toast) {
		notes.push(h('div.note.ok', null, state.toast));
	}
	statusBar.replaceChildren(line, ...notes);
}

/* ---------- Views ---------- */

function plotItem(plot, extra) {
	return h('a.item', { href: '#/plot/' + plot.id },
		h('div.item-title', null, plot.label || plot.ref),
		h('div.item-sub', null, [plot.ref, plot.farm, cropOf(plot)].filter(Boolean).join(' · ')),
		extra || null,
	);
}

function viewHome() {
	document.title = t('AppTitle');
	const list = h('div.list');
	const fill = () => {
		const q = state.search.trim().toLowerCase();
		const found = plots().filter((p) => !q || [p.ref, p.label, p.farm, p.comune, cropOf(p)].join(' ').toLowerCase().includes(q));
		list.replaceChildren(...(found.length ? found.map((p) => plotItem(p)) : [h('p.empty', null, plots().length ? t('NoMatch') : t('NoPlots'))]));
	};
	const search = h('input.search', {
		type: 'search', placeholder: t('SearchPlot'), value: state.search, autocomplete: 'off', 'aria-label': t('SearchPlot'),
		oninput: (e) => {
			state.search = e.target.value;
			fill();
		},
	});
	fill();
	show(
		h('div.actions', null,
			h('a.btn.big', { href: '#/scan' }, h('span.icon.qr', { 'aria-hidden': 'true' }), t('ScanQr')),
			h('a.btn.big', { href: '#/near' }, h('span.icon.pin', { 'aria-hidden': 'true' }), t('NearMe')),
		),
		h('h2', null, t('Plots')),
		search,
		list,
		footer(),
	);
}

function footer() {
	const user = state.session && state.session.user ? (state.session.user.name || state.session.user.login) : '';
	return h('div.footer', null,
		user ? h('div', null, t('User', user)) : null,
		h('div', null, t('Version', APP_VERSION)),
		h('button.link', {
			type: 'button',
			onclick: async () => {
				if (window.confirm(t('ClearConfirm'))) {
					await kvClear();
					state.session = null;
					state.data = null;
					state.syncedAt = null;
					toast(t('Cleared'));
					route();
				}
			},
		}, t('ClearData')),
	);
}

function field(label, value) {
	return value ? h('div.field', null, h('span.label', null, label), h('span.value', null, value)) : null;
}

function backLink() {
	return h('a.back', {
		href: '#/',
		onclick: (e) => {
			if (history.length > 1) {
				e.preventDefault();
				history.back();
			}
		},
	}, '‹ ' + t('Back'));
}

function viewPlot(id) {
	const plot = plots().find((p) => p.id === id);
	if (!plot) {
		show(backLink(), h('p.empty', null, t('PlotNotFound', '#' + id)));
		return;
	}
	document.title = plot.label || plot.ref;
	const place = [plot.comune, plot.sheet ? 'Fg. ' + plot.sheet : '', plot.parcel ? 'Part. ' + plot.parcel : ''].filter(Boolean).join(' ');
	const links = [
		h('a.btn', { href: new URL('../../dolifarm/dolifarmplot_card.php?ref=' + encodeURIComponent(plot.ref), location.href).href, target: '_blank', rel: 'noopener' }, t('OpenInDolibarr')),
	];
	if (plot.lat !== null && plot.lon !== null) {
		links.push(h('a.btn', { href: 'https://www.google.com/maps/dir/?api=1&destination=' + plot.lat + ',' + plot.lon, target: '_blank', rel: 'noopener noreferrer' }, t('Navigate')));
	}
	const plans = plansOf(plot.id);
	show(
		backLink(),
		h('h1', null, plot.label || plot.ref),
		h('div.card', null,
			field('Ref.', plot.ref),
			field(t('Farm'), plot.farm),
			field(t('Place'), place),
			field(t('Area'), plot.area_ha ? fmtNumber(plot.area_ha, 4) + ' ha' : ''),
			h('div.buttons', null, links),
		),
		h('h2', null, t('ProductionPlans')),
		plans.length ? plans.map(planCard) : h('p.empty', null, t('NoPlan')),
	);
}

function planCard(plan) {
	const safety = safetyOf(plan.id);
	const ops = byPlan('operations', plan.id);
	const harvests = byPlan('harvests', plan.id);
	const draft = (status) => (status === 0 ? h('span.badge', null, t('Draft')) : null);
	return h('div.card', null,
		h('div.card-title', null, [plan.crop, plan.variety].filter(Boolean).join(' - ') || plan.label || plan.ref),
		h('div.item-sub', null, [plan.ref, plan.season ? t('Season', plan.season) : '', plan.status === 0 ? t('Draft') : t('Validated')].filter(Boolean).join(' · ')),
		safety.length
			? safety.map((s) => h('div.note.error', null, h('strong', null, t('SafetyUntil', fmtDate(s.until))), ' ', t('SafetyLine', s.product, s.ref, fmtDate(s.date_end), s.safety_days)))
			: h('div.note.ok', null, t('NoSafety')),
		h('h3', null, t('LastOperations')),
		ops.length
			? h('ul.records', null, ops.map((o) => h('li', null, h('span.date', null, fmtDate(o.date)), ' ', o.type_label, o.label ? ' - ' + o.label : '', draft(o.status))))
			: h('p.empty', null, t('NoRecords')),
		h('h3', null, t('LastHarvests')),
		harvests.length
			? h('ul.records', null, harvests.map((r) => h('li', null, h('span.date', null, fmtDate(r.date)), ' ', r.qty !== null ? fmtNumber(r.qty) + ' ' + (r.unit || '') : '', r.batch ? ' · ' + r.batch : '', draft(r.status))))
			: h('p.empty', null, t('NoRecords')),
	);
}

function openRef(ref) {
	const plot = findPlotByRef(ref);
	if (plot) {
		location.hash = '#/plot/' + plot.id;
		return true;
	}
	return false;
}

function viewScan() {
	document.title = t('ScanQr');
	const message = h('p.empty');
	const video = h('video.scanner', { playsinline: true, muted: true, autoplay: true });
	const input = h('input.search', { type: 'text', placeholder: t('TypeRef'), autocomplete: 'off', autocapitalize: 'characters', 'aria-label': t('TypeRef') });
	const form = h('form.inline', {
		onsubmit: (e) => {
			e.preventDefault();
			const ref = refFromQr(input.value);
			if (ref && !openRef(ref)) {
				message.textContent = t('PlotNotFound', ref);
			}
		},
	}, input, h('button.btn', { type: 'submit' }, t('Open')));
	show(backLink(), h('h1', null, t('ScanTitle')), video, message, form);

	let stream = null;
	let stopped = false;
	cleanup = () => {
		stopped = true;
		if (stream) {
			stream.getTracks().forEach((track) => track.stop());
		}
	};
	if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
		video.remove();
		message.textContent = t('ScanNotAvailable');
		return;
	}
	const reader = qrReader(video);
	Promise.all([reader, navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })]).then(([read, s]) => {
		stream = s;
		if (stopped) {
			cleanup();
			return;
		}
		video.srcObject = s;
		const tick = async () => {
			if (stopped) {
				return;
			}
			try {
				if (video.readyState >= 2) {
					const text = await read();
					if (text) {
						const ref = refFromQr(text);
						if (navigator.vibrate) {
							navigator.vibrate(80);
						}
						if (openRef(ref)) {
							return;
						}
						message.textContent = t('PlotNotFound', ref);
					}
				}
			} catch (e) {
				// frame not readable: try the next one
			}
			setTimeout(tick, 250);
		};
		tick();
	}).catch((e) => {
		video.remove();
		message.textContent = (e && e.name === 'ScanNotAvailable' ? t('ScanNotAvailable') : t('CameraDenied', (e && (e.message || e.name)) || '-'));
	});
}

let jsQrLoading = null;

/** Load the QR decoder jsQR (assets/vendor/jsqr, Apache 2.0) once, for the browsers without BarcodeDetector */
function loadJsQr() {
	if (!jsQrLoading) {
		jsQrLoading = new Promise((resolve, reject) => {
			if (window.jsQR) {
				resolve(window.jsQR);
				return;
			}
			const script = document.createElement('script');
			script.src = new URL('../vendor/jsqr/jsQR.js', import.meta.url).href;
			script.onload = () => (window.jsQR ? resolve(window.jsQR) : reject(Object.assign(new Error('jsQR'), { name: 'ScanNotAvailable' })));
			script.onerror = () => {
				jsQrLoading = null;
				reject(Object.assign(new Error('jsQR'), { name: 'ScanNotAvailable' }));
			};
			document.head.appendChild(script);
		});
	}
	return jsQrLoading;
}

/**
 * Function that reads the QR code in the current frame of the video: BarcodeDetector of the browser (Chrome on
 * Android), else jsQR on a reduced copy of the frame (Safari on iPhone)
 *
 * @param {HTMLVideoElement} video
 * @returns {Promise<() => Promise<string>>} Reader, '' when no code is found
 */
async function qrReader(video) {
	if ('BarcodeDetector' in window) {
		try {
			const formats = await window.BarcodeDetector.getSupportedFormats();
			if (formats.includes('qr_code')) {
				const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
				return async () => {
					const codes = await detector.detect(video);
					return codes.length ? codes[0].rawValue : '';
				};
			}
		} catch (e) {
			// fall back to jsQR
		}
	}
	const decode = await loadJsQr();
	const canvas = document.createElement('canvas');
	const context = canvas.getContext('2d', { willReadFrequently: true });
	return async () => {
		const scale = Math.min(1, 640 / Math.max(video.videoWidth, video.videoHeight));
		canvas.width = Math.round(video.videoWidth * scale);
		canvas.height = Math.round(video.videoHeight * scale);
		if (!canvas.width || !canvas.height) {
			return '';
		}
		context.drawImage(video, 0, 0, canvas.width, canvas.height);
		const image = context.getImageData(0, 0, canvas.width, canvas.height);
		const code = decode(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });
		return code ? code.data : '';
	};
}

/** Distance in metres between two points */
function distance(lat1, lon1, lat2, lon2) {
	const rad = Math.PI / 180;
	const a = Math.sin((lat2 - lat1) * rad / 2) ** 2 + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin((lon2 - lon1) * rad / 2) ** 2;
	return 2 * 6371000 * Math.asin(Math.sqrt(a));
}

function fmtDistance(m) {
	return m < 1000 ? Math.round(m) + ' m' : fmtNumber(m / 1000, 1) + ' km';
}

function viewNear() {
	document.title = t('NearMe');
	const message = h('p.empty', null, t('Locating'));
	const list = h('div.list');
	show(backLink(), h('h1', null, t('NearMe')), message, list);
	if (!navigator.geolocation) {
		message.textContent = t('GpsDenied', '-');
		return;
	}
	let watch = navigator.geolocation.watchPosition((pos) => {
		const { latitude, longitude, accuracy } = pos.coords;
		const withCoords = plots().filter((p) => p.lat !== null && p.lon !== null);
		const sorted = withCoords.map((p) => ({ p, d: distance(latitude, longitude, p.lat, p.lon) })).sort((a, b) => a.d - b.d);
		message.textContent = t('Accuracy', Math.round(accuracy));
		const without = plots().length - withCoords.length;
		list.replaceChildren(
			...sorted.map((x, i) => plotItem(x.p, h('div.item-sub', null, i === 0 ? h('strong', null, t('Nearest') + ' · ') : null, t('Distance', fmtDistance(x.d))))),
			...(without ? [h('p.empty', null, t('NoCoordinates', without))] : []),
		);
	}, (e) => {
		message.textContent = t('GpsDenied', e.message || e.code);
	}, { enableHighAccuracy: true, maximumAge: 10000, timeout: 30000 });
	cleanup = () => {
		if (watch !== null) {
			navigator.geolocation.clearWatch(watch);
			watch = null;
		}
	};
}

/* ---------- Router ---------- */

function route() {
	const hash = location.hash || '#/';
	const m = /^#\/plot\/(\d+)$/.exec(hash);
	if (m) {
		viewPlot(Number(m[1]));
	} else if (hash === '#/scan') {
		viewScan();
	} else if (hash === '#/near') {
		viewNear();
	} else {
		viewHome();
	}
}

/* ---------- Start ---------- */

function registerServiceWorker() {
	if (!('serviceWorker' in navigator)) {
		return;
	}
	navigator.serviceWorker.register('service-worker.js').then((reg) => {
		reg.addEventListener('updatefound', () => {
			const worker = reg.installing;
			if (!worker) {
				return;
			}
			worker.addEventListener('statechange', () => {
				if (worker.state === 'installed' && navigator.serviceWorker.controller) {
					statusBar.appendChild(h('div.note.ok', null, t('UpdateReady'), ' ', h('button.btn.small', { type: 'button', onclick: () => worker.postMessage('skipWaiting') }, t('Reload'))));
				}
			});
		});
	}).catch(() => {});
	let reloading = false;
	navigator.serviceWorker.addEventListener('controllerchange', () => {
		if (!reloading) {
			reloading = true;
			location.reload();
		}
	});
}

async function start() {
	await loadLocal();
	renderStatus();
	// Opened from the address of a sign (?ref=...)
	const ref = new URLSearchParams(location.search).get('ref');
	if (ref && !location.hash) {
		const plot = findPlotByRef(ref);
		if (plot) {
			history.replaceState(null, '', location.pathname + '#/plot/' + plot.id);
		}
	}
	route();
	window.addEventListener('hashchange', route);
	window.addEventListener('online', () => {
		renderStatus();
		sync();
	});
	window.addEventListener('offline', renderStatus);
	registerServiceWorker();
	if (navigator.onLine) {
		sync();
	}
}

start();
