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
import { newClientId, saveRecord, loadQueue, removeRecord, loadSent, sendQueue } from './queue.js';
import { MAX_PHOTOS, reducePhoto, newPhoto, loadPhotos, removePhoto, savePhotos, deletePhotosOf, sendPhotos } from './photos.js';
import { treatmentFields, checkTreatment, treatmentSummary } from './treatment.js';

export const APP_VERSION = '1.3.0';

/** Days without update after which the app warns (Safari deletes the data of the apps not used for about 7 days) */
const OLD_DATA_DAYS = 5;
/** Days after which a record waiting to be sent is shown as old */
const OLD_QUEUE_DAYS = 2;

const state = {
	session: null,	// answer of action=session
	data: null,		// answer of action=data
	syncedAt: null,	// ISO date of the last update
	queue: [],		// records waiting to be sent
	photos: [],		// photos waiting to be sent
	sent: [],		// records sent recently
	syncing: false,
	problem: '',	// login, forbidden, api, otheruser, or a message
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
		await reloadQueue();
	} catch (e) {
		state.problem = String(e.message || e);
	}
	setLang(state.session && state.session.user ? state.session.user.lang : undefined);
}

async function reloadQueue() {
	state.queue = await loadQueue();
	state.photos = await loadPhotos();
	state.sent = await loadSent();
}

/** Something waits on the phone: records or photos */
const waiting = () => state.queue.length + state.photos.length;
const photosOf = (recordId) => state.photos.filter((p) => p.record_id === recordId);

/**
 * Send the records waiting on the phone, then update the data from the server. Without network or without session
 * the data and the records stay on the phone.
 *
 * @param {boolean} [manual] Asked by the user: the records refused before are sent again too
 */
async function sync(manual = false) {
	if (state.syncing) {
		return;
	}
	state.syncing = true;
	renderStatus();
	try {
		let session = await getSession();
		// Another user on the same phone: the records of the previous one must be sent first (with his login),
		// then his data are deleted
		const others = state.queue.filter((r) => r.user_id !== session.user.id);
		const otherPhotos = state.photos.filter((p) => p.user_id !== session.user.id);
		if (others.length || otherPhotos.length) {
			state.problem = 'otheruser';
			state.otherUser = others.length ? (others[0].user_name || ('#' + others[0].user_id)) : ('#' + otherPhotos[0].user_id);
			state.otherCount = others.length + otherPhotos.length;
			return;
		}
		if (state.session && state.session.user && state.session.user.id !== session.user.id) {
			await kvClear();
			state.data = null;
			state.syncedAt = null;
			state.sent = [];
		}
		if (state.queue.length) {
			const result = await sendQueue(session, manual);
			session = result.session;
			await reloadQueue();
			if (result.sent) {
				toast(t('SentOk', result.sent));
			}
			if (result.warned) {
				toast(t('SentWithWarnings', result.warned));
			}
			if (result.errors) {
				toast(t('SentErrors', result.errors));
			}
		}
		// Photos after their records: the photos of a record still in the queue wait for it
		if (state.photos.length) {
			const result = await sendPhotos(session, new Set(state.queue.map((r) => r.client_id)), manual);
			session = result.session;
			await reloadQueue();
			if (result.sent) {
				toast(t('PhotosSentOk', result.sent));
			}
			if (result.errors) {
				toast(t('PhotosSentErrors', result.errors));
			}
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
		// Do not redraw a form being filled in, the camera or the GPS
		if (!/^#\/(new|edit|scan|near)/.test(location.hash)) {
			route();
		}
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
	} else if (state.problem === 'otheruser') {
		notes.push(h('div.note.error', null, t('OtherUserQueue', state.otherCount, state.otherUser)));
	} else if (state.problem) {
		notes.push(h('div.note.error', null, t('SyncFailed', state.problem)));
	}
	// Records waiting on the phone: always visible, with the age of the oldest one
	if (state.queue.length) {
		const errors = state.queue.filter((r) => r.status === 'error').length;
		const oldest = daysSince(state.queue[0].saved_at || state.queue[0].created_at);
		const cls = errors ? 'div.note.error.queue' : (oldest !== null && oldest >= OLD_QUEUE_DAYS ? 'div.note.warn.queue' : 'div.note.info.queue');
		notes.push(h(cls, null,
			h('a', { href: '#/queue' }, t('QueueCount', state.queue.length)),
			state.photos.length ? ' · ' + t('PhotosCount', state.photos.length) : '',
			oldest ? ' · ' + t('QueueOldest', oldest) : '',
			errors ? ' · ' + t('QueueErrors', errors) : '',
		));
	} else if (state.photos.length) {
		// Records sent, photos still on the phone
		const errors = state.photos.filter((p) => p.status === 'error').length;
		notes.push(h(errors ? 'div.note.error.queue' : 'div.note.info.queue', null,
			h('a', { href: '#/queue' }, t('PhotosWaiting', state.photos.length)),
			errors ? ' · ' + t('QueueErrors', errors) : '',
		));
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
				if (waiting()) {
					toast(t('ClearBlocked', waiting()));
					return;
				}
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
	const rights = (state.session && state.session.rights) || {};
	const waiting = state.queue.filter((r) => Number(r.data.cropplan_id) === plan.id);
	return h('div.card', null,
		h('div.card-title', null, [plan.crop, plan.variety].filter(Boolean).join(' - ') || plan.label || plan.ref),
		h('div.item-sub', null, [plan.ref, plan.season ? t('Season', plan.season) : '', plan.status === 0 ? t('Draft') : t('Validated')].filter(Boolean).join(' · ')),
		(rights.operation || rights.harvest || canTreat()) ? h('div.buttons', null,
			rights.operation ? h('a.btn.primary', { href: '#/new/operation/' + plan.id }, '+ ' + t('NewOperation')) : null,
			rights.harvest ? h('a.btn.primary', { href: '#/new/harvest/' + plan.id }, '+ ' + t('NewHarvest')) : null,
			canTreat() ? h('a.btn.primary', { href: '#/new/treatment/' + plan.id }, '+ ' + t('NewTreatment')) : null,
		) : null,
		waiting.length ? h('div.note.info', null, h('strong', null, t('WaitingHere', waiting.length)), h('ul.records', null, waiting.map((r) => h('li', null, h('a', { href: '#/edit/' + encodeURIComponent(r.client_id) }, recordSummary(r)), r.status === 'error' ? h('span.badge.err', null, t('Error')) : null)))) : null,
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

/* ---------- Records made on the phone (P2) ---------- */

const planById = (id) => ((state.data && state.data.cropplans) || []).find((p) => p.id === Number(id)) || null;
const plotById = (id) => plots().find((p) => p.id === Number(id)) || null;
const typeLabel = (code) => (((state.data && state.data.operation_types) || []).find((x) => x.code === code) || {}).label || code;
/** Drafts of treatments: permission of the server, and its data downloaded (doliTrace 1.5.3 or later) */
const canTreat = () => Boolean(state.session && state.session.rights && state.session.rights.treatment && state.data && state.data.treatment);
const formTitle = (kind) => (kind === 'harvest' ? t('NewHarvest') : (kind === 'treatment' ? t('NewTreatment') : t('NewOperation')));

/** One line describing a record of the queue */
function recordSummary(r) {
	const d = r.data || {};
	const plan = planById(d.cropplan_id);
	const plot = plan ? plotById(plan.plot_id) : null;
	const where = plot ? (plot.label || plot.ref) : (plan ? plan.ref : '');
	const what = (r.kind === 'harvest'
		? t('HarvestOf', fmtNumber(Number(d.qty) || 0) + ' ' + (d.unit || ''))
		: (r.kind === 'treatment' ? treatmentSummary(state.data && state.data.treatment, d) : typeLabel(d.type))) + (d.label ? ' - ' + d.label : '');
	return [fmtDate(d.date), what, where].filter(Boolean).join(' · ');
}

/** Now as 2026-11-18T08:30 in the time zone of the phone */
const nowLocal = () => isoLocal(new Date().toISOString());

/**
 * Check a record before saving it: same rules as the server (ajax/pwa.php)
 *
 * @returns {string} Error, empty when valid
 */
/**
 * Number typed on the phone, with the comma or the dot as decimal separator (a text field: a field of type number of
 * some browsers drops the comma and 12,5 becomes 125)
 *
 * @param {string} text Text
 * @return {number} The number, NaN when it is not a number
 */
function decimalValue(text) {
	const value = String(text).trim().replace(/\s/g, '');
	return (/^\d+([.,]\d+)?$/.test(value) ? Number(value.replace(',', '.')) : NaN);
}

function checkRecord(kind, d) {
	const limit = isoLocal(new Date(Date.now() + 86400000).toISOString());
	if (kind === 'treatment') {
		return checkTreatment(d, limit);
	}
	if (kind === 'operation') {
		if (!d.type) {
			return t('Required', t('OperationType'));
		}
		if (!d.date) {
			return t('Required', t('DateStart'));
		}
		if (d.date > limit) {
			return t('FutureDate');
		}
		if (d.date_end && (d.date_end < d.date || d.date_end > limit)) {
			return t('EndBeforeStart');
		}
		if (d.area_ha !== undefined && !(d.area_ha > 0)) {
			return t('InvalidValue', t('AreaHa'));
		}
		if (d.bbch && !/^\d{1,2}$/.test(d.bbch)) {
			return t('InvalidValue', 'BBCH');
		}
	} else {
		if (!d.date) {
			return t('Required', t('HarvestDate'));
		}
		if (d.date > limit.slice(0, 10)) {
			return t('FutureDate');
		}
		if (!(Number(d.qty) > 0)) {
			return t('InvalidValue', t('Quantity'));
		}
		if (!d.unit) {
			return t('Required', t('Unit'));
		}
	}
	return '';
}

/** Form field with its label */
function formRow(label, input, required) {
	return h('label.form-row', null, h('span.form-label', null, label, required ? ' *' : ''), input);
}

function select(name, options, value, empty) {
	const el = h('select.search', { name },
		empty !== undefined ? h('option', { value: '' }, empty) : null,
		options.map((o) => h('option', { value: o.value, selected: String(o.value) === String(value ?? '') }, o.label)),
	);
	return el;
}

/**
 * Form of a new record, or of a record of the queue to correct
 *
 * @param {string} kind operation or harvest
 * @param {number|null} planId Production plan (new record)
 * @param {Object|null} record Record of the queue
 */
function viewForm(kind, planId, record) {
	const d = record ? record.data : {};
	const plan = planById(record ? d.cropplan_id : planId);
	if (!plan) {
		show(backLink(), h('p.empty', null, t('PlanNotFound')));
		return;
	}
	const plot = plotById(plan.plot_id);
	document.title = formTitle(kind);
	const error = h('div.note.error', { hidden: !(record && record.message) }, record && record.message ? record.message : '');
	let fields;
	let treatment = null;
	if (kind === 'treatment') {
		if (!canTreat()) {
			show(backLink(), h('p.empty', null, t('TreatmentNotAvailable')));
			return;
		}
		const T = state.data.treatment;
		treatment = treatmentFields({ h, select, formRow, decimalValue, nowLocal }, T, {
			plan,
			plot,
			machines: ((state.data && state.data.machines) || []).filter((m) => !m.farm_id || m.farm_id === plan.farm_id),
			organic: (T.organic_plots || []).includes(plan.plot_id),
			userId: state.session.user.id,
		}, d);
		fields = treatment.fields;
	} else if (kind === 'operation') {
		const machines = ((state.data && state.data.machines) || []).filter((m) => !m.farm_id || m.farm_id === plan.farm_id);
		fields = [
			formRow(t('OperationType'), select('type', ((state.data && state.data.operation_types) || []).map((x) => ({ value: x.code, label: x.label })), d.type, t('Choose')), true),
			formRow(t('DateStart'), h('input.search', { type: 'datetime-local', name: 'date', value: d.date || nowLocal(), required: true }), true),
			formRow(t('DateEnd'), h('input.search', { type: 'datetime-local', name: 'date_end', value: d.date_end || '' })),
			formRow(t('Label'), h('input.search', { type: 'text', name: 'label', maxlength: 255, value: d.label || '' })),
			formRow(t('DurationMin'), h('input.search', { type: 'number', name: 'duration_min', min: 0, step: 1, inputmode: 'numeric', value: d.duration_min || '' })),
			formRow(t('AreaHa'), h('input.search', { type: 'text', name: 'area_ha', inputmode: 'decimal', autocomplete: 'off', value: d.area_ha || (plot && plot.area_ha ? plot.area_ha : '') })),
			machines.length ? formRow(t('Machine'), select('machine_id', machines.map((m) => ({ value: m.id, label: [m.label, m.ref].filter(Boolean).join(' - ') })), d.machine_id, t('None'))) : null,
			formRow(t('Bbch'), h('input.search', { type: 'text', name: 'bbch', maxlength: 2, inputmode: 'numeric', pattern: '[0-9]{1,2}', value: d.bbch || '' })),
			formRow(t('Note'), h('textarea.search', { name: 'note', rows: 3, maxlength: 2000 }, d.note || '')),
		];
	} else {
		const units = (state.data && state.data.units) || [];
		fields = [
			formRow(t('HarvestDate'), h('input.search', { type: 'date', name: 'date', value: d.date || nowLocal().slice(0, 10), required: true }), true),
			formRow(t('Quantity'), h('input.search', { type: 'text', name: 'qty', inputmode: 'decimal', autocomplete: 'off', value: d.qty || '', required: true }), true),
			formRow(t('Unit'), select('unit', units.map((u) => ({ value: u.code, label: u.label + (u.label !== u.code ? ' (' + u.code + ')' : '') })), d.unit || (units.some((u) => u.code === 'kg') ? 'kg' : '')), true),
			plan.product ? formRow(t('Product'), h('span.value', null, plan.product)) : null,
			formRow(t('Label'), h('input.search', { type: 'text', name: 'label', maxlength: 255, value: d.label || '' })),
			formRow(t('Note'), h('textarea.search', { name: 'note', rows: 3, maxlength: 2000 }, d.note || '')),
		];
	}
	/** Values of the form as data of the record */
	const readForm = (values) => {
		const data = { cropplan_id: plan.id };
		for (const [k, v] of Object.entries(values)) {
			if (v !== '') {
				data[k] = (['duration_min', 'machine_id', 'operator_id', 'kind'].includes(k) ? parseInt(v, 10) : (['qty', 'area_ha', 'water_volume'].includes(k) ? decimalValue(v) : v));
			}
		}
		return data;
	};

	// Photos: reduced when added, saved with the record, sent after it
	const photos = record ? photosOf(record.client_id).map((p) => Object.assign({}, p)) : [];
	const urls = [];
	const photoList = h('div.photos');
	const photoNote = h('div.item-sub');
	const fileInput = h('input.visually-hidden', { type: 'file', accept: 'image/*', multiple: true });
	const addLabel = h('span', null, t('AddPhoto'));
	const addButton = h('label.btn', null, addLabel, fileInput);
	const drawPhotos = () => {
		urls.splice(0).forEach((u) => URL.revokeObjectURL(u));
		photoList.replaceChildren(...photos.map((p, i) => {
			const url = URL.createObjectURL(p.blob);
			urls.push(url);
			return h('div.photo', null,
				h('img', { src: url, alt: t('Photo') + ' ' + (i + 1) }),
				h('button.photo-remove', {
					type: 'button',
					title: t('RemovePhoto'),
					'aria-label': t('RemovePhoto'),
					onclick: () => {
						photos.splice(i, 1);
						drawPhotos();
					},
				}, '×'),
				p.status === 'error' ? h('div.photo-error', null, p.message || p.error) : null,
			);
		}));
		addButton.hidden = photos.length >= MAX_PHOTOS;
		photoNote.textContent = t('PhotosHelp', photos.length, MAX_PHOTOS);
	};
	fileInput.addEventListener('change', async (e) => {
		const files = Array.from(e.target.files || []);
		e.target.value = '';
		const room = MAX_PHOTOS - photos.length;
		if (files.length > room) {
			toast(t('PhotosMax', MAX_PHOTOS));
		}
		addLabel.textContent = t('PhotoProcessing');
		for (const file of files.slice(0, Math.max(0, room))) {
			try {
				photos.push(Object.assign(newPhoto(await reducePhoto(file)), { created_at: nowLocal() }));
			} catch (err) {
				toast(t('PhotoError'));
			}
		}
		addLabel.textContent = t('AddPhoto');
		drawPhotos();
	});
	drawPhotos();
	const photoBox = h('div.form-row', null, h('span.form-label', null, t('Photos')), photoList, addButton, photoNote);

	const form = h('form.form', {
		onsubmit: async (e) => {
			e.preventDefault();
			const values = {};
			new FormData(form).forEach((v, k) => {
				values[k] = String(v).trim();
			});
			const data = readForm(values);
			if (treatment) {
				data.products = treatment.products();
			}
			const problem = checkRecord(kind, data);
			if (problem) {
				error.textContent = problem;
				error.hidden = false;
				error.scrollIntoView({ block: 'center' });
				return;
			}
			const user = state.session.user;
			const saved = Object.assign({}, record || {}, {
				client_id: record ? record.client_id : newClientId(),
				kind,
				user_id: user.id,
				user_name: user.name || user.login,
				created_at: record ? record.created_at : nowLocal(),
				saved_at: new Date().toISOString(),
				data,
			});
			saved.summary = recordSummary(saved);
			await saveRecord(saved);
			await savePhotos(saved, photos);
			await reloadQueue();
			toast(navigator.onLine ? t('SavedSending') : t('SavedOnPhone'));
			location.hash = plot ? '#/plot/' + plot.id : '#/queue';
			if (navigator.onLine) {
				sync(Boolean(record));
			}
		},
	},
	error,
	fields,
	photoBox,
	h('div.buttons', null,
		h('button.btn.primary', { type: 'submit' }, t('Save')),
		record ? h('button.btn.danger', { type: 'button', onclick: () => deleteRecord(record) }, t('Delete')) : null,
	));
	if (treatment) {
		// Warnings and quantities follow the header (date, surface, water, operator, machine)
		const header = () => {
			const values = {};
			new FormData(form).forEach((v, k) => {
				values[k] = String(v).trim();
			});
			return readForm(values);
		};
		treatment.setReader(header);
		form.addEventListener('input', () => treatment.refresh());
		form.addEventListener('change', () => treatment.refresh());
	}
	show(
		backLink(),
		h('h1', null, formTitle(kind)),
		h('div.item-sub', null, [plot ? (plot.label || plot.ref) : '', [plan.crop, plan.variety].filter(Boolean).join(' - '), plan.ref].filter(Boolean).join(' · ')),
		h('p.empty', null, t('DraftOnly')),
		form,
	);
	cleanup = () => urls.splice(0).forEach((u) => URL.revokeObjectURL(u));
}

async function deleteRecord(record) {
	if (!window.confirm(t('DeleteConfirm', recordSummary(record)))) {
		return;
	}
	await removeRecord(record.client_id);
	await deletePhotosOf(record.client_id);
	await reloadQueue();
	renderStatus();
	location.hash = '#/queue';
}

/**
 * Photos of records already sent, still on the phone: waiting, or refused by the server (they can be deleted)
 *
 * @param {boolean} online
 * @returns {HTMLElement[]|null}
 */
function photosOfSent(online) {
	const inQueue = new Set(state.queue.map((r) => r.client_id));
	const list = state.photos.filter((p) => !inQueue.has(p.record_id));
	if (!list.length) {
		return null;
	}
	const record = (p) => state.sent.find((s) => s.client_id === p.record_id);
	return [
		h('h2', null, t('PhotosWaiting', list.length)),
		state.queue.length ? null : h('div.buttons', null, h('button.btn.primary', { type: 'button', disabled: !online || state.syncing, onclick: () => sync(true) }, state.syncing ? t('Sending') : t('SendNow'))),
		h('div.list', null, list.map((p) => {
			const sent = record(p);
			return h('div.item', null,
				h('div.item-title', null, t('Photo'), sent ? ' · ' + (sent.ref || ('#' + sent.id)) + (sent.label ? ' · ' + sent.label : '') : ''),
				h('div.item-sub', null, fmtDate(p.created_at), p.status === 'error' ? '' : ' · ' + t('Pending')),
				p.status === 'error' ? h('div.note.error', null, t('ErrorLabel', p.message || p.error), ' ',
					h('button.btn.small.danger', {
						type: 'button',
						onclick: async () => {
							if (window.confirm(t('DeletePhotoConfirm'))) {
								await removePhoto(p.photo_id);
								await reloadQueue();
								renderStatus();
								viewQueue();
							}
						},
					}, t('Delete'))) : null,
			);
		})),
	];
}

function viewQueue() {
	document.title = t('Queue');
	const online = navigator.onLine;
	show(
		backLink(),
		h('h1', null, t('Queue')),
		state.queue.length
			? [
				h('div.buttons', null, h('button.btn.primary', { type: 'button', disabled: !online || state.syncing, onclick: () => sync(true) }, state.syncing ? t('Sending') : t('SendNow'))),
				online ? null : h('p.empty', null, t('SendWhenOnline')),
				h('div.list', null, state.queue.map((r) => h('a.item', { href: '#/edit/' + encodeURIComponent(r.client_id) },
					h('div.item-title', null, recordSummary(r)),
					h('div.item-sub', null, t('SavedAt', fmtDate(isoLocal(r.saved_at || r.created_at))), r.status === 'error' ? '' : ' · ' + t('Pending'),
						photosOf(r.client_id).length ? ' · ' + t('PhotosCount', photosOf(r.client_id).length) : ''),
					r.status === 'error' ? h('div.note.error', null, t('ErrorLabel', r.message || r.error)) : null,
				))),
			]
			: (state.photos.length ? null : h('p.empty', null, t('QueueEmpty'))),
		photosOfSent(online),
		state.sent.length ? [
			h('h2', null, t('RecentSent')),
			h('ul.records', null, state.sent.map((s) => h('li', null,
				h('span.date', null, fmtDate(isoLocal(s.sent_at))), ' ',
				s.kind === 'harvest' ? t('Harvest') : (s.kind === 'treatment' ? t('Treatment') : t('Operation')), ' ',
				h('a', { href: s.url, target: '_blank', rel: 'noopener' }, s.ref || ('#' + s.id)),
				s.label ? ' · ' + s.label : '',
				// Warnings of the register for a treatment: to see before the validation in the office
				(s.warnings || []).length ? h('details.warnings', null,
					h('summary', null, h('span.badge.warn', null, t('WarningsCount', s.warnings.length))),
					h('ul', null, s.warnings.map((w) => h('li', null, w))),
				) : null,
			))),
		] : null,
	);
}

/* ---------- Router ---------- */

function route() {
	const hash = location.hash || '#/';
	const m = /^#\/plot\/(\d+)$/.exec(hash);
	const n = /^#\/new\/(operation|harvest|treatment)\/(\d+)$/.exec(hash);
	const e = /^#\/edit\/(.+)$/.exec(hash);
	if (m) {
		viewPlot(Number(m[1]));
	} else if (n) {
		viewForm(n[1], Number(n[2]), null);
	} else if (e) {
		const record = state.queue.find((r) => r.client_id === decodeURIComponent(e[1]));
		if (record) {
			viewForm(record.kind, null, record);
		} else {
			location.replace('#/queue');
		}
	} else if (hash === '#/queue') {
		viewQueue();
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
	const ready = (worker) => statusBar.appendChild(h('div.note.ok', null, t('UpdateReady'), ' ', h('button.btn.small', { type: 'button', onclick: () => worker.postMessage('skipWaiting') }, t('Reload'))));
	navigator.serviceWorker.register('service-worker.js').then((reg) => {
		// New version installed before this opening (another page of the app was open)
		if (reg.waiting && navigator.serviceWorker.controller) {
			ready(reg.waiting);
		}
		reg.addEventListener('updatefound', () => {
			const worker = reg.installing;
			if (!worker) {
				return;
			}
			worker.addEventListener('statechange', () => {
				if (worker.state === 'installed' && navigator.serviceWorker.controller) {
					ready(worker);
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
	// Records waiting: try again every minute while the app is open (the network may come back without the
	// "online" event, e.g. a weak signal in the field)
	setInterval(() => {
		if (navigator.onLine && !state.syncing && (state.queue.some((r) => r.status !== 'error') || state.photos.some((p) => p.status !== 'error'))) {
			sync();
		}
	}, 60000);
	// Back to the app (iPhone suspends it): send and update
	document.addEventListener('visibilitychange', () => {
		if (document.visibilityState === 'visible' && navigator.onLine && waiting()) {
			sync();
		}
	});
	registerServiceWorker();
	if (navigator.onLine) {
		sync();
	}
}

start();
