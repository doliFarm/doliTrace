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
import { weatherCard, formWeather } from './weather.js';

export const APP_VERSION = '1.7.0';

/** Days without update after which the app warns (Safari deletes the data of the apps not used for about 7 days) */
const OLD_DATA_DAYS = 5;
/** Days after which a record waiting to be sent is shown as old */
const OLD_QUEUE_DAYS = 2;
/** Records of each kind shown in the card of a plan, the others with "See all" */
const SHOWN_RECORDS = 5;

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
	tab: 'plots',	// list of the home page: plots or plans
	planIndex: {},	// plan shown in the card of each plot (id of the plot => position in its plans)
	syncError: '',	// reason of the last update not done (shown with the icon of the status bar)
	formDirty: false,	// form being filled in with changes not saved
	installPrompt: null,	// event of Chrome to install the app
};

/** Pages visited in the app: "Back" goes to the previous one only when it is a page of the app */
const visited = [];

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

/** Plots of the list of the home page, with the search typed there */
function filterPlots(search) {
	const q = String(search || '').trim().toLowerCase();
	return plots().filter((p) => !q || [p.ref, p.label, p.farm, p.comune, cropOf(p)].join(' ').toLowerCase().includes(q));
}

/** Plots to scroll from the card of a plot: the list of the home page, or all the plots when the plot is not in it */
function plotSequence(id) {
	const found = filterPlots(state.search);
	return found.some((p) => p.id === id) ? found : plots();
}

/**
 * Horizontal swipe on an element: handler(1) when the finger goes to the left (next), handler(-1) to the right
 * (previous). Vertical moves scroll the page as usual; a swipe handled by an inner element does not reach the outer ones.
 */
function onSwipe(el, handler) {
	let x0 = null;
	let y0 = 0;
	el.addEventListener('touchstart', (e) => {
		if (e.touches.length !== 1 || e.target.closest('input, select, textarea, video')) {
			x0 = null;
			return;
		}
		x0 = e.touches[0].clientX;
		y0 = e.touches[0].clientY;
	}, { passive: true });
	el.addEventListener('touchend', (e) => {
		if (x0 === null) {
			return;
		}
		const dx = e.changedTouches[0].clientX - x0;
		const dy = e.changedTouches[0].clientY - y0;
		x0 = null;
		if (Math.abs(dx) >= 60 && Math.abs(dx) > 2 * Math.abs(dy)) {
			e.stopPropagation();
			handler(dx < 0 ? 1 : -1);
		}
	});
}

/** Buttons ‹ text › to go to the previous and the next element (disabled at the ends) */
function pager(text, index, count, go, prevLabel, nextLabel) {
	return h('div.pager', null,
		h('button.btn.small.pager-btn', { type: 'button', 'aria-label': prevLabel, title: prevLabel, disabled: index <= 0, onclick: () => go(-1) }, '‹'),
		h('span.pager-text', { 'aria-live': 'polite' }, text),
		h('button.btn.small.pager-btn', { type: 'button', 'aria-label': nextLabel, title: nextLabel, disabled: index >= count - 1, onclick: () => go(1) }, '›'),
	);
}

function findPlotByRef(ref) {
	const key = String(ref || '').trim().toUpperCase();
	return plots().find((p) => String(p.ref).toUpperCase() === key) || null;
}

/**
 * Plot read from a QR code: address of the card of the plot (sign: ?ref=..., card and PDF sheet: ?id=...) or the
 * reference itself. Returns the reference (empty when the plot of an ?id= is not in the data of the phone) and the
 * address, when the QR code is one, to open the card online.
 */
function refFromQr(text) {
	const value = String(text || '').trim();
	let url = null;
	try {
		url = new URL(value);
	} catch (e) {
		return { ref: value, url: '' };
	}
	if (!/^https?:$/.test(url.protocol)) {
		return { ref: value, url: '' };
	}
	const ref = url.searchParams.get('ref');
	if (ref) {
		return { ref: ref, url: value };
	}
	const id = parseInt(url.searchParams.get('id') || '', 10);
	if (id > 0 && /dolifarmplot_card\.php$/.test(url.pathname)) {
		const plot = plots().find((p) => Number(p.id) === id);
		return { ref: plot ? String(plot.ref) : '', url: value };
	}
	return { ref: '', url: value };
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
	setLang();
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
		state.problem = (session.api > API_VERSION ? 'api' : '');
		state.syncError = '';
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
			state.syncError = t('LoginNeeded');
		} else if (e instanceof ApiError && e.code === 'forbidden') {
			state.problem = 'forbidden';
			state.syncError = t('Forbidden');
		} else if (e instanceof OfflineError) {
			// Network announced but server not reachable (weak signal): icon of the status bar
			state.syncError = navigator.onLine ? t('SyncFailed', t('Offline')) : '';
			if (manual) {
				toast(t('SyncFailed', t('Offline')));
			}
		} else {
			state.problem = String(e.message || e);
			state.syncError = t('SyncFailed', state.problem);
		}
	} finally {
		state.syncing = false;
		renderStatus();
		// Do not redraw a form being filled in, the camera or the GPS
		if (!/^#\/(new|edit|draft|scan|near)/.test(location.hash)) {
			route();
		}
	}
}

/* ---------- Status bar and warnings ---------- */

function renderStatus() {
	const online = navigator.onLine;
	const errors = state.queue.filter((r) => r.status === 'error').length + state.photos.filter((p) => p.status === 'error').length;
	const line = h('div.status-line', null,
		h('span.dot.' + (online ? 'on' : 'off'), { title: online ? t('Online') : t('Offline') }),
		h('span.status-text', null, state.syncing ? t('Syncing') : (state.syncedAt ? t('SyncedAt', fmtDate(isoLocal(state.syncedAt))) : t('NeverSynced'))),
		// Last update not done: small icon, the reason with a tap
		(state.syncError && !state.syncing) ? h('button.status-icon.warn', { type: 'button', title: t('SyncProblem'), 'aria-label': t('SyncProblem'), onclick: () => toast(state.syncError) }, '⚠') : null,
		// Records or photos not sent yet
		waiting() ? h('a.status-icon' + (errors ? '.err' : '.pending'), { href: '#/queue', title: t('NotSynced'), 'aria-label': t('QueueCount', waiting()) }, '⟳ ' + waiting()) : null,
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

/** One farm only (setup of doliFarm): the name of the farm is not repeated on every line */
const singleFarm = () => Boolean(state.session && state.session.single_farm);
const farmOf = (name) => (singleFarm() ? '' : name);

/** Records waiting on the phone for the plans of a plot, or for a plan: icon ⟳ (red when refused) */
function waitingIcon(planIds) {
	const ids = new Set(planIds.map(Number));
	const list = state.queue.filter((r) => ids.has(Number(r.data && r.data.cropplan_id)));
	if (!list.length) {
		return null;
	}
	const refused = list.some((r) => r.status === 'error');
	return h('span.sync-icon' + (refused ? '.err' : ''), { title: refused ? t('Error') : t('NotSynced'), 'aria-label': t('QueueCount', list.length) }, '⟳');
}

function plotItem(plot, extra) {
	return h('a.item', { href: '#/plot/' + plot.id },
		h('div.item-title', null, plot.label || plot.ref, waitingIcon(plansOf(plot.id).map((p) => p.id))),
		h('div.item-sub', null, [plot.ref, farmOf(plot.farm), cropOf(plot)].filter(Boolean).join(' · ')),
		extra || null,
	);
}

/** Open plans of the list of the home page, with the search typed there */
function filterPlans(search) {
	const q = String(search || '').trim().toLowerCase();
	return ((state.data && state.data.cropplans) || []).filter((p) => {
		const plot = plotById(p.plot_id);
		return !q || [p.ref, p.crop, p.variety, p.season, plot ? plot.label : '', plot ? plot.ref : '', plot ? plot.farm : ''].join(' ').toLowerCase().includes(q);
	});
}

function planItem(plan) {
	const plot = plotById(plan.plot_id);
	return h('a.item', {
		href: '#/plot/' + plan.plot_id,
		// The card of the plot opens on this plan
		onclick: () => {
			state.planIndex[plan.plot_id] = Math.max(0, plansOf(plan.plot_id).findIndex((p) => p.id === plan.id));
		},
	},
	h('div.item-title', null, [plan.crop, plan.variety].filter(Boolean).join(' - ') || plan.label || plan.ref, waitingIcon([plan.id])),
	h('div.item-sub', null, [plot ? (plot.label || plot.ref) : '', farmOf(plot ? plot.farm : ''), plan.season ? t('Season', plan.season) : '', plan.ref].filter(Boolean).join(' · ')),
	);
}

/** Installation of the app: button of Chrome when it offers it, else the steps of the browser */
function installBanner() {
	const standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
	if (standalone || state.installDismissed) {
		return null;
	}
	const ios = /iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	const android = /Android/i.test(navigator.userAgent);
	if (!ios && !android) {
		return null;
	}
	const close = () => {
		state.installDismissed = true;
		kvSet('installDismissed', new Date().toISOString()).catch(() => {});
		box.remove();
	};
	let text = ios ? t('InstallIos') : (state.installPrompt ? t('InstallAndroid') : t('InstallAndroidMenu'));
	// Samsung Internet installs the app as a package that Play Protect flags: the installation is to be done with Chrome
	if (/SamsungBrowser/i.test(navigator.userAgent)) {
		text = t('InstallWithChrome');
	}
	const box = h('div.note.info.install', null,
		h('strong', null, t('InstallTitle')), h('br'), text,
		h('div.buttons', null,
			state.installPrompt ? h('button.btn.small.primary', {
				type: 'button',
				onclick: async () => {
					const prompt = state.installPrompt;
					state.installPrompt = null;
					prompt.prompt();
					try {
						await prompt.userChoice;
					} catch (e) {
						// closed
					}
					box.remove();
				},
			}, t('InstallButton')) : null,
			h('button.btn.small', { type: 'button', onclick: close }, t('InstallLater')),
		),
	);
	return box;
}

function viewHome() {
	document.title = t('AppTitle');
	const list = h('div.list');
	const fill = () => {
		if (state.tab === 'plans') {
			const found = filterPlans(state.search);
			list.replaceChildren(...(found.length ? found.map((p) => planItem(p)) : [h('p.empty', null, ((state.data && state.data.cropplans) || []).length ? t('NoMatch') : t('NoPlans'))]));
		} else {
			const found = filterPlots(state.search);
			list.replaceChildren(...(found.length ? found.map((p) => plotItem(p)) : [h('p.empty', null, plots().length ? t('NoMatch') : t('NoPlots'))]));
		}
	};
	const search = h('input.search', {
		type: 'search', placeholder: state.tab === 'plans' ? t('SearchPlan') : t('SearchPlot'), value: state.search, autocomplete: 'off', 'aria-label': t('SearchPlot'),
		oninput: (e) => {
			state.search = e.target.value;
			fill();
		},
	});
	const tab = (name, label) => h('button.tab' + (state.tab === name ? '.active' : ''), {
		type: 'button', role: 'tab', 'aria-selected': state.tab === name ? 'true' : 'false',
		onclick: () => {
			if (state.tab !== name) {
				state.tab = name;
				kvSet('tab', name).catch(() => {});
				viewHome();
			}
		},
	}, label);
	fill();
	show(
		installBanner(),
		h('div.actions', null,
			h('a.btn.big', { href: '#/scan' }, h('span.icon.qr', { 'aria-hidden': 'true' }), t('ScanQr')),
			h('a.btn.big', { href: '#/near' }, h('span.icon.pin', { 'aria-hidden': 'true' }), t('NearMe')),
		),
		h('div.tabs', { role: 'tablist' }, tab('plots', t('TabPlots')), tab('plans', t('TabPlans'))),
		search,
		list,
		footer(),
	);
}

/** Delete the data downloaded on this phone (not while records or photos wait to be sent) */
async function clearData() {
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
}

function footer() {
	const user = state.session && state.session.user ? (state.session.user.name || state.session.user.login) : '';
	return h('div.footer', null,
		user ? h('div', null, t('User', user)) : null,
		h('div', null, t('Version', APP_VERSION)),
		h('button.link', { type: 'button', onclick: clearData }, t('ClearData')),
	);
}

/*
 * Personal settings of the app, shown in the info page. None for now: each entry will be
 * { label, control: () => element } with its value saved on the phone (kvSet).
 */
const SETTINGS = [];

/** Info page (button on the top right): user, privacy, doliFarm and its contacts, personal settings */
function viewInfo() {
	document.title = t('InfoTitle');
	const session = state.session || {};
	const user = session.user || null;
	const logoutUrl = session.logout_url ? new URL(session.logout_url, location.href).href : '';
	show(
		backLink(),
		h('h1', null, t('InfoTitle')),
		h('div.card', null,
			h('div.card-title', null, t('InfoUser')),
			user
				? [
					field(t('InfoName'), user.name),
					field(t('InfoLogin'), user.login),
					field(t('InfoCompany'), session.company),
					field(t('InfoServer'), location.host),
					field(t('InfoSynced'), state.syncedAt ? fmtDate(isoLocal(state.syncedAt)) : t('InfoNever')),
				]
				: h('p.empty', null, t('InfoNotLogged')),
			h('div.buttons', null,
				user && logoutUrl ? h('a.btn', { href: logoutUrl }, t('InfoLogout')) : null,
				h('button.btn', { type: 'button', onclick: clearData }, t('ClearData')),
			),
			user && logoutUrl ? h('p.item-sub', null, t('InfoLogoutHelp')) : null,
		),
		h('div.card', null,
			h('div.card-title', null, t('InfoPrivacy')),
			h('ul.info-list', null,
				['PrivacyData', 'PrivacyServer', 'PrivacyPosition', 'PrivacyCamera', 'PrivacyMaps', 'PrivacyOtherUser'].map((key) => h('li', null, t(key))),
			),
		),
		h('div.card', null,
			h('div.card-title', null, t('OfflineTitle')),
			h('ul.info-list', null,
				['OfflineData', 'OfflineSync', 'OfflineSignal', 'OfflineBattery', 'OfflineChanges', 'OfflineIphone'].map((key) => h('li', null, t(key))),
			),
		),
		h('div.card', null,
			h('div.card-title', null, t('InfoSettings')),
			SETTINGS.length
				? SETTINGS.map((s) => formRow(s.label, s.control()))
				: h('p.empty', null, t('InfoNoSettings')),
		),
		h('div.card.info-about', null,
			h('img', { src: 'assets/img/dolifarm-logo.jpg', alt: 'doliFarm', width: 72, height: 72 }),
			h('div', null,
				h('div.card-title', null, t('InfoAbout')),
				h('p', null, t('InfoAboutText')),
				field(t('InfoWebsite'), h('a', { href: 'https://dolifarm.com', target: '_blank', rel: 'noopener' }, 'dolifarm.com')),
				field(t('InfoEmail'), h('a', { href: 'mailto:info@dolifarm.com' }, 'info@dolifarm.com')),
				field(t('InfoVersion'), APP_VERSION + ' (API ' + API_VERSION + ')'),
				field(t('InfoLicense'), 'GNU AGPL v3'),
			),
		),
	);
}

function field(label, value) {
	return value ? h('div.field', null, h('span.label', null, label), h('span.value', null, value)) : null;
}

/**
 * Page above the current one: form → plot, plot → list, the other pages → list
 *
 * @returns {string} Hash
 */
function parentHash() {
	const hash = location.hash || '#/';
	const form = /^#\/(new\/\w+|records)\/(\d+)$/.exec(hash);
	if (form) {
		const plan = planById(form[2]);
		return plan ? '#/plot/' + plan.plot_id : '#/';
	}
	const edit = /^#\/(edit|draft)\/(.+)$/.exec(hash);
	if (edit) {
		const r = state.queue.find((x) => x.client_id === decodeURIComponent(edit[2])) || draftByClientId(decodeURIComponent(edit[2]));
		const plan = r ? planById(r.data ? r.data.cropplan_id : r.cropplan_id) : null;
		return plan ? '#/plot/' + plan.plot_id : '#/queue';
	}
	return '#/';
}

/**
 * Go back: to the previous page of the app when there is one (same as the Back button of the phone), else to the
 * page above
 */
function goBack() {
	if (visited.length > 1) {
		history.back();
	} else {
		replaceHash(parentHash());
	}
}

function backLink() {
	return h('a.back', {
		href: parentHash(),
		onclick: (e) => {
			e.preventDefault();
			goBack();
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
	// Previous and next plot of the list: buttons ‹ › and horizontal swipe on the page (replace: Back goes to the list)
	const sequence = plotSequence(plot.id);
	const index = sequence.findIndex((p) => p.id === plot.id);
	const goPlot = (dir) => {
		const next = sequence[index + dir];
		if (next) {
			replaceHash('#/plot/' + next.id);
		}
	};
	const page = h('div.swipe-page', null,
		h('div.title-row', null,
			backLink(),
			sequence.length > 1 ? pager(t('PlotOf', index + 1, sequence.length), index, sequence.length, goPlot, t('PrevPlot'), t('NextPlot')) : null,
		),
		h('h1', null, plot.label || plot.ref),
		h('div.card', null,
			field('Ref.', plot.ref),
			field(t('Farm'), farmOf(plot.farm)),
			field(t('Place'), place),
			field(t('Area'), plot.area_ha ? fmtNumber(plot.area_ha, 4) + ' ha' : ''),
			h('div.buttons', null, links),
		),
		weatherCard(h, state.data && state.data.weather, plot.id),
		h('h2', null, t('ProductionPlans')),
		plans.length ? planCarousel(plot.id, plans) : h('p.empty', null, t('NoPlan')),
	);
	if (sequence.length > 1) {
		onSwipe(page, goPlot);
	}
	show(page);
}

/** Plans of a plot one at a time: buttons ‹ › and horizontal swipe on the plan (it does not change the plot) */
function planCarousel(plotId, plans) {
	if (plans.length === 1) {
		return planCard(plans[0]);
	}
	const box = h('div.plans');
	const render = (dir) => {
		const i = Math.min(Math.max(state.planIndex[plotId] || 0, 0), plans.length - 1);
		state.planIndex[plotId] = i;
		const card = planCard(plans[i]);
		if (dir) {
			card.classList.add(dir > 0 ? 'slide-next' : 'slide-prev');
		}
		box.replaceChildren(pager(t('PlanOf', i + 1, plans.length), i, plans.length, go, t('PrevPlan'), t('NextPlan')), card);
	};
	const go = (dir) => {
		const i = (state.planIndex[plotId] || 0) + dir;
		if (i >= 0 && i < plans.length) {
			state.planIndex[plotId] = i;
			render(dir);
		}
	};
	render(0);
	onSwipe(box, go);
	return box;
}

/** Drafts sent from the app by this user, still drafts (API 2): they can be changed or deleted */
const draftByClientId = (id) => ((state.data && state.data.drafts) || []).find((d) => d.client_id === id) || null;
const draftOfRecord = (kind, id) => ((state.data && state.data.drafts) || []).find((d) => d.kind === kind && d.id === id) || null;

/** Lines of the operations, treatments and harvests of a plan; a draft of the user opens its form */
function recordLines(plan, limit) {
	const badge = (status) => (status === 0 ? h('span.badge', null, t('Draft')) : null);
	const line = (kind, r, text) => {
		const draft = draftOfRecord(kind, r.id);
		const queued = draft && state.queue.find((q) => q.client_id === draft.client_id);
		const content = [h('span.date', null, fmtDate(r.date)), ' ', text, badge(r.status), queued ? h('span.sync-icon', { title: t('NotSynced') }, '⟳') : null];
		return h('li', null, draft ? h('a', { href: '#/draft/' + encodeURIComponent(draft.client_id), title: t('EditDraft') }, content, ' ✎') : content);
	};
	const section = (title, list, render) => [
		h('h3', null, title),
		list.length ? h('ul.records', null, list.slice(0, limit || list.length).map(render)) : h('p.empty', null, t('NoRecords')),
	];
	const ops = byPlan('operations', plan.id);
	const treatments = byPlan('treatments', plan.id);
	const harvests = byPlan('harvests', plan.id);
	const kindLabel = (kind) => (kind === 2 ? t('Fertilization') : t('Treatment'));
	return {
		count: ops.length + treatments.length + harvests.length,
		more: limit ? Math.max(ops.length, treatments.length, harvests.length) > limit : false,
		nodes: [
			section(t('LastOperations'), ops, (o) => line('operation', o, o.type_label + (o.label ? ' - ' + o.label : ''))),
			canTreat() || treatments.length ? section(t('LastTreatments'), treatments, (r) => line('treatment', r, kindLabel(r.kind) + ' · ' + r.ref)) : null,
			section(t('LastHarvests'), harvests, (r) => line('harvest', r, (r.qty !== null ? fmtNumber(r.qty) + ' ' + (r.unit || '') : '') + (r.batch ? ' · ' + r.batch : ''))),
		],
	};
}

function planCard(plan) {
	const safety = safetyOf(plan.id);
	const rights = (state.session && state.session.rights) || {};
	const waiting = state.queue.filter((r) => Number(r.data.cropplan_id) === plan.id);
	const records = recordLines(plan, SHOWN_RECORDS);
	return h('div.card', null,
		h('div.card-title', null, [plan.crop, plan.variety].filter(Boolean).join(' - ') || plan.label || plan.ref),
		h('div.item-sub', null, [plan.ref, plan.season ? t('Season', plan.season) : '', plan.status === 0 ? t('Draft') : t('Validated')].filter(Boolean).join(' · ')),
		(rights.operation || rights.harvest || canTreat()) ? h('div.buttons', null,
			rights.operation ? h('a.btn.primary', { href: '#/new/operation/' + plan.id }, '+ ' + t('NewOperation')) : null,
			rights.harvest ? h('a.btn.primary', { href: '#/new/harvest/' + plan.id }, '+ ' + t('NewHarvest')) : null,
			canTreat() ? h('a.btn.primary', { href: '#/new/treatment/' + plan.id }, '+ ' + t('NewTreatment')) : null,
		) : null,
		waiting.length ? h('div.note.info', null, h('strong', null, h('span.sync-icon', { 'aria-hidden': 'true' }, '⟳'), ' ', t('WaitingHere', waiting.length)), h('ul.records', null, waiting.map((r) => h('li', null, h('a', { href: '#/edit/' + encodeURIComponent(r.client_id) }, recordSummary(r)), r.status === 'error' ? h('span.badge.err', null, t('Error')) : null)))) : null,
		safety.length
			? safety.map((s) => h('div.note.error', null, h('strong', null, t('SafetyUntil', fmtDate(s.until))), ' ', t('SafetyLine', s.product, s.ref, fmtDate(s.date_end), s.safety_days)))
			: h('div.note.ok', null, t('NoSafety')),
		records.nodes,
		records.more ? h('div.buttons', null, h('a.btn', { href: '#/records/' + plan.id }, t('SeeAll', records.count))) : null,
	);
}

/** All the records of a plan downloaded on the phone (the last 100 of each kind) */
function viewRecords(planId) {
	const plan = planById(planId);
	if (!plan) {
		show(backLink(), h('p.empty', null, t('PlanNotFound')));
		return;
	}
	const plot = plotById(plan.plot_id);
	document.title = t('PlanRecords');
	show(
		backLink(),
		h('h1', null, t('PlanRecords')),
		h('div.item-sub', null, [plot ? (plot.label || plot.ref) : '', [plan.crop, plan.variety].filter(Boolean).join(' - '), plan.ref].filter(Boolean).join(' · ')),
		h('div.card', null, recordLines(plan, 0).nodes),
	);
}

function openRef(ref) {
	const plot = findPlotByRef(ref);
	if (plot) {
		replaceHash('#/plot/' + plot.id);
		return true;
	}
	return false;
}

function viewScan() {
	document.title = t('ScanQr');
	const message = h('p.empty');
	const video = h('video.scanner', { playsinline: true, muted: true, autoplay: true });
	const input = h('input.search', { type: 'text', placeholder: t('TypeRef'), autocomplete: 'off', autocapitalize: 'characters', 'aria-label': t('TypeRef') });
	// Plot of a QR code or of the typed reference: the reference goes in the field, the card opens when the plot is
	// in the data of the phone, else the message says so with the link to the card online (when the QR is an address)
	const openRead = (text) => {
		const read = refFromQr(text);
		if (read.ref) {
			input.value = read.ref;
		}
		if (read.ref && openRef(read.ref)) {
			return true;
		}
		message.replaceChildren(
			t('PlotNotFound', read.ref || read.url || String(text || '').trim()),
			read.url ? h('br') : null,
			read.url ? h('a', { href: read.url, target: '_blank', rel: 'noopener' }, t('OpenCardOnline')) : null
		);
		return false;
	};
	const form = h('form.inline', {
		onsubmit: (e) => {
			e.preventDefault();
			if (input.value.trim()) {
				openRead(input.value);
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
		let lastText = '';
		const tick = async () => {
			if (stopped) {
				return;
			}
			try {
				if (video.readyState >= 2) {
					const text = await read();
					// the same QR code stays in front of the camera: read it once
					if (text && text !== lastText) {
						lastText = text;
						if (navigator.vibrate) {
							navigator.vibrate(80);
						}
						if (openRead(text)) {
							return;
						}
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
	// Change or deletion of a draft sent before
	const op = r.op === 'update' ? t('QueueUpdate', r.ref || '') : (r.op === 'delete' ? t('QueueDelete', r.ref || '') : '');
	return [op, fmtDate(d.date), what, where].filter(Boolean).join(' · ');
}

/** Period of a plan as limits of the fields date and time of a form: {min, max} */
function planLimits(plan, withTime) {
	const now = isoLocal(new Date(Date.now() + 86400000).toISOString());
	let max = now;
	if (plan.date_end && plan.date_end + 'T23:59' < max) {
		max = plan.date_end + 'T23:59';
	}
	return {
		min: plan.date_start ? plan.date_start + (withTime ? 'T00:00' : '') : null,
		max: withTime ? max : max.slice(0, 10),
	};
}

/** Error when the record is dated outside the period of its plan (the server checks it again) */
function checkPlanPeriod(plan, d) {
	if (!plan || !d.date) {
		return '';
	}
	const first = String(d.date).slice(0, 10);
	const last = String(d.date_end || d.date).slice(0, 10);
	if ((plan.date_start && first < plan.date_start) || (plan.date_end && last > plan.date_end)) {
		return t('OutsidePlan', plan.date_start ? fmtDate(plan.date_start) : '…', plan.date_end ? fmtDate(plan.date_end) : '…');
	}
	return '';
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

/**
 * Number typed in a field of the weather observed (comma or point, sign for the temperature)
 *
 * @param {string} text Text
 * @return {?number} null when empty, NaN when it is not a number
 */
function obsNumber(text) {
	const value = String(text ?? '').trim().replace(/\s/g, '');
	if (value === '') {
		return null;
	}
	return (/^-?\d+([.,]\d+)?$/.test(value) ? Number(value.replace(',', '.')) : NaN);
}

/**
 * Check of the weather observed in the field: values in their range, reading near the record and not in the future
 *
 * @param {object} d Data of the record
 * @return {string} Problem, '' if none
 */
function checkWeatherObs(d) {
	const o = d.weather_obs;
	if (!o) {
		return '';
	}
	const ranges = { temp: [-30, 55, 'WeatherObsTemp'], wind: [0, 60, 'WeatherObsWind'], rh: [0, 100, 'WeatherObsRh'] };
	for (const [key, [min, max, label]] of Object.entries(ranges)) {
		if (o[key] !== undefined && !(o[key] >= min && o[key] <= max)) {
			return t('InvalidValue', t(label));
		}
	}
	if (o.date && d.date) {
		const at = new Date(o.date).getTime();
		const start = new Date(d.date).getTime();
		const end = new Date(d.date_end || d.date).getTime();
		if (at < start - 12 * 3600000 || at > end + 12 * 3600000 || at > Date.now() + 3600000) {
			return t('WeatherObsDateFar');
		}
	}
	return '';
}

/**
 * Box of the weather observed in the field: temperature, wind, humidity and time of the reading (all optional);
 * warnings over the thresholds of doliField for the treatments of plant protection
 *
 * @param {object} d Data of the record
 * @return {{box: HTMLElement, refresh: function(object, boolean): void}}
 */
function weatherObsBox(d) {
	const o = d.weather_obs || {};
	const fmt = (v) => fmtNumber(v, 1);
	const input = (name, value) => h('input.search', { type: 'text', inputmode: 'decimal', autocomplete: 'off', name, value: value ?? '' });
	const dateInput = h('input.search', { type: 'datetime-local', name: 'obs_date', value: o.date || '' });
	const warn = h('div.note.warn', { hidden: true });
	const box = h('div.form-row.weather-obs', null,
		h('span.form-label', null, t('WeatherObs')),
		h('div.item-sub', null, t('WeatherObsHelp')),
		h('div.obs-grid', null,
			formRow(t('WeatherObsTemp'), input('obs_temp', o.temp)),
			formRow(t('WeatherObsWind'), input('obs_wind', o.wind)),
			formRow(t('WeatherObsRh'), input('obs_rh', o.rh)),
		),
		formRow(t('WeatherObsTime'), h('div.obs-time', null, dateInput,
			h('button.btn', {
				type: 'button',
				onclick: () => {
					dateInput.value = isoLocal(new Date().toISOString());
					dateInput.dispatchEvent(new Event('input', { bubbles: true }));
				},
			}, t('WeatherObsNow')))),
		warn,
	);
	const refresh = (values, plantProtection) => {
		const limit = (state.data && state.data.weather && state.data.weather.thresholds) || { wind: 5, temp: 30 };
		const wind = obsNumber(values.obs_wind);
		const temp = obsNumber(values.obs_temp);
		const messages = [];
		if (plantProtection && wind !== null && wind > limit.wind) {
			messages.push(t('WarnObsWind', fmt(wind), fmt(limit.wind)));
		}
		if (plantProtection && temp !== null && temp > limit.temp) {
			messages.push(t('WarnObsTemp', fmt(temp), fmt(limit.temp)));
		}
		warn.textContent = messages.join(' ');
		warn.hidden = !messages.length;
	};
	return { box, refresh };
}

function checkRecord(kind, d, plan) {
	const limit = isoLocal(new Date(Date.now() + 86400000).toISOString());
	const period = checkPlanPeriod(plan, d);
	const observed = (kind !== 'harvest' ? checkWeatherObs(d) : '');
	if (kind === 'treatment') {
		return checkTreatment(d, limit) || period || observed;
	}
	if (period && d.date) {
		return period;
	}
	if (observed) {
		return observed;
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

/** Date proposed for a new record: now, or the nearest end of the period of the plan when now is outside it */
function defaultDate(plan, withTime) {
	const now = nowLocal();
	const day = now.slice(0, 10);
	let value = now;
	if (plan.date_start && day < plan.date_start) {
		value = plan.date_start + 'T08:00';
	} else if (plan.date_end && day > plan.date_end) {
		value = plan.date_end + 'T08:00';
	}
	return withTime ? value : value.slice(0, 10);
}

/**
 * Field of the growth stage: list of the BBCH dictionary downloaded (doliTrace 1.5.9 or later), else the code typed
 *
 * @param {string} name Name of the field
 * @param {string} value Code
 * @param {number} maxlength Digits of the code (2 for the operations, 3 for the treatments)
 * @returns {HTMLElement}
 */
function bbchInput(name, value, maxlength) {
	const codes = ((state.data && state.data.bbch) || []).filter((b) => String(b.code).length <= maxlength);
	if (!codes.length) {
		return h('input.search', { type: 'text', name, maxlength, inputmode: 'numeric', pattern: '[0-9]{1,' + maxlength + '}', value: value || '' });
	}
	const options = codes.map((b) => ({ value: b.code, label: b.code + ' - ' + b.label }));
	if (value && !codes.some((b) => String(b.code) === String(value))) {
		options.push({ value, label: String(value) });
	}
	return select(name, options, value || '', t('None'));
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
			machines: ((state.data && state.data.machines) || []).filter((m) => m.farm_id === plan.farm_id),
			organic: (T.organic_plots || []).includes(plan.plot_id),
			userId: state.session.user.id,
			bbch: bbchInput,
			defaultDate: defaultDate(plan, true),
		}, d);
		fields = treatment.fields;
	} else if (kind === 'operation') {
		// Machines of the farm of the plan only
		const machines = ((state.data && state.data.machines) || []).filter((m) => m.farm_id === plan.farm_id);
		fields = [
			formRow(t('OperationType'), select('type', ((state.data && state.data.operation_types) || []).map((x) => ({ value: x.code, label: x.label })), d.type, t('Choose')), true),
			formRow(t('DateStart'), h('input.search', { type: 'datetime-local', name: 'date', value: d.date || defaultDate(plan, true), required: true }), true),
			formRow(t('DateEnd'), h('input.search', { type: 'datetime-local', name: 'date_end', value: d.date_end || '' })),
			formRow(t('Label'), h('input.search', { type: 'text', name: 'label', maxlength: 255, value: d.label || '' })),
			formRow(t('DurationMin'), h('div', null, h('input.search', { type: 'number', name: 'duration_min', min: 0, step: 1, inputmode: 'numeric', value: d.duration_min || '' }), h('div.item-sub.duration-hint'))),
			formRow(t('AreaHa'), h('input.search', { type: 'text', name: 'area_ha', inputmode: 'decimal', autocomplete: 'off', value: d.area_ha || (plot && plot.area_ha ? plot.area_ha : '') })),
			machines.length ? formRow(t('Machine'), select('machine_id', machines.map((m) => ({ value: m.id, label: [m.label, m.ref].filter(Boolean).join(' - ') })), d.machine_id, t('None'))) : null,
			formRow(t('Bbch'), bbchInput('bbch', d.bbch, 2)),
			formRow(t('Note'), h('textarea.search', { name: 'note', rows: 3, maxlength: 2000 }, d.note || '')),
		];
	} else {
		const units = (state.data && state.data.units) || [];
		fields = [
			formRow(t('HarvestDate'), h('input.search', { type: 'date', name: 'date', value: d.date || defaultDate(plan, false), required: true }), true),
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
		const obs = {};
		for (const [k, v] of Object.entries(values)) {
			// Weather observed in the field: sent only with at least one value
			if (k.startsWith('obs_')) {
				if (v !== '') {
					obs[k.slice(4)] = (k === 'obs_date' ? v : obsNumber(v));
				}
				continue;
			}
			if (v !== '') {
				data[k] = (['duration_min', 'machine_id', 'operator_id', 'kind'].includes(k) ? parseInt(v, 10) : (['qty', 'area_ha', 'water_volume'].includes(k) ? decimalValue(v) : v));
			}
		}
		if (['temp', 'wind', 'rh'].some((k) => obs[k] !== undefined)) {
			data.weather_obs = obs;
		}
		return data;
	};

	// Weather expected at the hour of the record (operations and treatments; warnings for plant protection)
	const weather = (kind !== 'harvest' && plot) ? formWeather(h, state.data && state.data.weather, plot.id) : null;
	// Weather observed in the field (thermometer, anemometer), saved with the record
	const observed = (kind !== 'harvest') ? weatherObsBox(d) : null;

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
			const problem = checkRecord(kind, data, plan);
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
			// Change of a draft sent before: a new identifier for each change (applied once by the server)
			if (saved.op === 'update') {
				saved.revision = newClientId();
			}
			saved.summary = recordSummary(saved);
			await saveRecord(saved);
			await savePhotos(saved, photos);
			await reloadQueue();
			toast(navigator.onLine ? t('SavedSending') : t('SavedOnPhone'));
			state.formDirty = false;
			// Back to the page that opened the form: the form does not stay in the history
			if (visited.length > 1) {
				history.back();
			} else {
				replaceHash(plot ? '#/plot/' + plot.id : '#/queue');
			}
			if (navigator.onLine) {
				sync(Boolean(record));
			}
		},
	},
	error,
	fields,
	weather ? weather.box : null,
	observed ? observed.box : null,
	photoBox,
	h('div.buttons', null,
		h('button.btn.primary', { type: 'submit' }, t('Save')),
		// Draft already sent: deleted also in the office; record not sent yet: deleted from the phone
		record && record.op === 'update' ? h('button.btn.danger', { type: 'button', onclick: () => deleteDraft(record) }, t('Delete')) : null,
		record && record.op !== 'update' && record.op !== 'delete' ? h('button.btn.danger', { type: 'button', onclick: () => deleteRecord(record) }, t('Delete')) : null,
	));
	// Limits of the dates: period of the plan, not in the future; the end not before the start
	const dateInput = form.querySelector('[name=date]');
	const endInput = form.querySelector('[name=date_end]');
	const limits = planLimits(plan, kind !== 'harvest');
	if (dateInput) {
		if (limits.min) {
			dateInput.min = limits.min;
		}
		dateInput.max = limits.max;
	}
	const durationInput = form.querySelector('[name=duration_min]');
	const durationHint = form.querySelector('.duration-hint');
	let durationTyped = Boolean(durationInput && durationInput.value);
	const syncDates = (e) => {
		if (endInput && dateInput) {
			endInput.min = dateInput.value || limits.min || '';
			endInput.max = limits.max;
		}
		if (!durationInput) {
			return;
		}
		if (e && e.target === durationInput) {
			durationTyped = durationInput.value !== '';
			// Start and duration: the end is proposed
			if (durationTyped && dateInput.value && endInput && !endInput.value) {
				const end = new Date(new Date(dateInput.value).getTime() + parseInt(durationInput.value, 10) * 60000);
				if (!Number.isNaN(end.getTime())) {
					endInput.value = isoLocal(end.toISOString());
				}
			}
			durationHint.textContent = '';
			return;
		}
		// Start and end: the duration is computed (until the user types it)
		if (!durationTyped && dateInput.value && endInput && endInput.value) {
			const minutes = Math.round((new Date(endInput.value).getTime() - new Date(dateInput.value).getTime()) / 60000);
			if (minutes > 0) {
				durationInput.value = String(minutes);
				durationHint.textContent = t('DurationAuto');
			}
		}
	};
	form.addEventListener('input', (e) => {
		state.formDirty = true;
		syncDates(e);
	});
	form.addEventListener('change', (e) => {
		state.formDirty = true;
		syncDates(e);
	});
	syncDates(null);
	if (observed) {
		const refreshObserved = () => {
			const values = {};
			new FormData(form).forEach((v, k) => {
				values[k] = String(v).trim();
			});
			observed.refresh(values, kind === 'treatment' && values.kind === '1');
		};
		form.addEventListener('input', refreshObserved);
		form.addEventListener('change', refreshObserved);
		refreshObserved();
	}
	if (weather) {
		const refreshWeather = () => {
			const values = {};
			new FormData(form).forEach((v, k) => {
				values[k] = String(v).trim();
			});
			weather.refresh(values, kind === 'treatment' && values.kind === '1');
		};
		form.addEventListener('input', refreshWeather);
		form.addEventListener('change', refreshWeather);
		refreshWeather();
	}
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
		h('h1', null, record && record.op === 'update' ? t('EditDraft') + (record.ref ? ' ' + record.ref : '') : formTitle(kind)),
		h('div.item-sub', null, [plot ? (plot.label || plot.ref) : '', [plan.crop, plan.variety].filter(Boolean).join(' - '), plan.ref].filter(Boolean).join(' · ')),
		h('p.empty', null, t('DraftOnly')),
		form,
	);
	state.formDirty = false;
	cleanup = () => {
		urls.splice(0).forEach((u) => URL.revokeObjectURL(u));
		state.formDirty = false;
	};
}

async function deleteRecord(record) {
	if (!window.confirm(t('DeleteConfirm', recordSummary(record)))) {
		return;
	}
	await removeRecord(record.client_id);
	await deletePhotosOf(record.client_id);
	await reloadQueue();
	renderStatus();
	state.formDirty = false;
	replaceHash('#/queue');
}

/**
 * Record of the queue to change a draft sent before (API 2): same identifier, data of the last sending
 *
 * @param {Object} draft Draft of data.drafts
 * @returns {Object}
 */
function recordOfDraft(draft) {
	const user = state.session.user;
	return {
		client_id: draft.client_id, kind: draft.kind, op: 'update', base: draft.base, ref: draft.ref, draft_id: draft.id,
		user_id: user.id, user_name: user.name || user.login, created_at: draft.created_at || nowLocal(),
		data: Object.assign({}, draft.data, { cropplan_id: draft.cropplan_id }),
	};
}

/** Delete a draft sent before: in the queue as a deletion, done by the server at the next sending */
async function deleteDraft(record) {
	if (!window.confirm(t('DeleteDraftConfirm', record.ref || '', recordSummary(Object.assign({}, record, { op: '' }))))) {
		return;
	}
	const saved = Object.assign({}, record, { op: 'delete', saved_at: new Date().toISOString() });
	saved.summary = recordSummary(saved);
	await saveRecord(saved);
	// Photos not sent of the draft: no longer useful
	await deletePhotosOf(record.client_id);
	await reloadQueue();
	toast(navigator.onLine ? t('SavedSending') : t('DraftDeleteQueued'));
	state.formDirty = false;
	replaceHash(parentHash());
	if (navigator.onLine) {
		sync(true);
	}
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
				h('div.list', null, state.queue.map((r) => h(r.op === 'delete' ? 'div.item' : 'a.item', r.op === 'delete' ? null : { href: '#/edit/' + encodeURIComponent(r.client_id) },
					h('div.item-title', null, h('span.sync-icon' + (r.status === 'error' ? '.err' : ''), { 'aria-hidden': 'true' }, '⟳'), ' ', recordSummary(r)),
					h('div.item-sub', null, t('SavedAt', fmtDate(isoLocal(r.saved_at || r.created_at))), r.status === 'error' ? '' : ' · ' + t('Pending'),
						photosOf(r.client_id).length ? ' · ' + t('PhotosCount', photosOf(r.client_id).length) : ''),
					r.status === 'error' ? h('div.note.error', null, t('ErrorLabel', r.message || r.error)) : null,
					// Deletion of a draft not sent yet: it can be cancelled
					r.op === 'delete' ? h('div.buttons', null, h('button.btn.small', {
						type: 'button',
						onclick: async () => {
							await removeRecord(r.client_id);
							await reloadQueue();
							renderStatus();
							viewQueue();
						},
					}, '× ' + t('Remove'))) : null,
				))),
			]
			: (state.photos.length ? null : h('p.empty', null, t('QueueEmpty'))),
		photosOfSent(online),
		state.sent.length ? [
			h('h2', null, t('RecentSent')),
			h('ul.records', null, state.sent.map((s) => h('li', null,
				h('span.date', null, fmtDate(isoLocal(s.sent_at))), ' ',
				s.kind === 'harvest' ? t('Harvest') : (s.kind === 'treatment' ? t('Treatment') : t('Operation')), ' ',
				s.url ? h('a', { href: s.url, target: '_blank', rel: 'noopener' }, s.ref || ('#' + s.id)) : (s.ref || ''),
				s.op === 'delete' ? h('span.badge', null, t('Deleted')) : null,
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

let replacing = false;
let restoring = false;

/** Go to a page replacing the current one in the history (Back does not come back to it) */
function replaceHash(hash) {
	if ((location.hash || '#/') === hash) {
		route();
		return;
	}
	replacing = true;
	location.replace(hash);
}

/**
 * Change of page: list of the pages visited (for Back), confirmation before leaving a form with changes not saved
 * (also with the Back button of the phone), button "i" of the top bar
 */
function onHashChange(e) {
	const hash = location.hash || '#/';
	if (restoring) {
		// Back on the form after "stay": the form is still on the screen
		restoring = false;
		return;
	}
	const oldHash = e && e.oldURL ? (new URL(e.oldURL).hash || '#/') : '';
	const back = visited.length > 1 && visited[visited.length - 2] === hash;
	if (!replacing && state.formDirty && /^#\/(new|edit|draft)\//.test(oldHash) && !window.confirm(t('UnsavedConfirm'))) {
		restoring = true;
		if (back) {
			history.forward();
		} else {
			history.back();
		}
		return;
	}
	state.formDirty = false;
	if (replacing) {
		replacing = false;
		visited[Math.max(0, visited.length - 1)] = hash;
	} else if (back) {
		visited.pop();
	} else {
		visited.push(hash);
	}
	route();
}

/** Button "i" of the top bar: on the info page it becomes X and closes it */
function updateInfoButton() {
	const button = document.getElementById('topbar-info');
	if (!button) {
		return;
	}
	const open = (location.hash === '#/info');
	button.classList.toggle('open', open);
	button.setAttribute('aria-label', open ? t('Close') : t('InfoTitle'));
	button.setAttribute('title', open ? t('Close') : t('InfoTitle'));
}

function route() {
	const hash = location.hash || '#/';
	const m = /^#\/plot\/(\d+)$/.exec(hash);
	const n = /^#\/new\/(operation|harvest|treatment)\/(\d+)$/.exec(hash);
	const e = /^#\/edit\/(.+)$/.exec(hash);
	const dr = /^#\/draft\/(.+)$/.exec(hash);
	const rec = /^#\/records\/(\d+)$/.exec(hash);
	updateInfoButton();
	if (m) {
		viewPlot(Number(m[1]));
	} else if (n) {
		viewForm(n[1], Number(n[2]), null);
	} else if (e) {
		const record = state.queue.find((r) => r.client_id === decodeURIComponent(e[1]));
		if (record && record.op !== 'delete') {
			viewForm(record.kind, null, record);
		} else {
			replaceHash('#/queue');
		}
	} else if (dr) {
		// Draft sent before: its change waiting in the queue, else the data of the last sending
		const id = decodeURIComponent(dr[1]);
		const queued = state.queue.find((r) => r.client_id === id);
		const draft = draftByClientId(id);
		if (queued && queued.op !== 'delete') {
			viewForm(queued.kind, null, queued);
		} else if (draft && !queued && state.session) {
			viewForm(draft.kind, null, recordOfDraft(draft));
		} else {
			replaceHash(parentHash());
		}
	} else if (rec) {
		viewRecords(Number(rec[1]));
	} else if (hash === '#/queue') {
		viewQueue();
	} else if (hash === '#/scan') {
		viewScan();
	} else if (hash === '#/near') {
		viewNear();
	} else if (hash === '#/info') {
		viewInfo();
	} else {
		viewHome();
	}
}

/* ---------- Start ---------- */

function registerServiceWorker() {
	if (!('serviceWorker' in navigator)) {
		return;
	}
	let readyNote = null;
	const ready = (worker) => {
		if (readyNote) {
			readyNote.remove();
		}
		readyNote = h('div.note.ok', null, t('UpdateReady'), ' ', h('button.btn.small', { type: 'button', onclick: () => worker.postMessage('skipWaiting') }, t('Reload')));
		statusBar.appendChild(readyNote);
	};
	navigator.serviceWorker.register('service-worker.js').then((reg) => {
		// Back to the app left open in the background: look for a new version (at most every 10 minutes)
		let checkedAt = Date.now();
		document.addEventListener('visibilitychange', () => {
			if (document.visibilityState === 'visible' && navigator.onLine && Date.now() - checkedAt > 600000) {
				checkedAt = Date.now();
				reg.update().catch(() => {});
			}
		});
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

/**
 * Opening page with the logo of doliFarm: at least 1.5 s, then it fades (a tap closes it before)
 *
 * @param {number} shownAt Time the page was shown
 */
function hideSplash(shownAt) {
	const splash = document.getElementById('splash');
	if (!splash) {
		return;
	}
	const close = () => {
		splash.classList.add('hide');
		setTimeout(() => splash.remove(), 500);
	};
	splash.addEventListener('click', close, { once: true });
	setTimeout(close, Math.max(0, 1500 - (Date.now() - shownAt)));
}

async function start() {
	const shownAt = Date.now();
	const splashTitle = document.querySelector('.splash-title');
	if (splashTitle) {
		splashTitle.textContent = t('SplashTitle');
		document.getElementById('splash-version').textContent = t('Version', APP_VERSION);
	}
	const infoButton = document.getElementById('topbar-info');
	if (infoButton) {
		// Second tap on "i" (now X): the info page closes
		infoButton.addEventListener('click', (e) => {
			if (location.hash === '#/info') {
				e.preventDefault();
				goBack();
			}
		});
	}
	// Installation offered by Chrome: the button of the banner of the home page uses it
	window.addEventListener('beforeinstallprompt', (e) => {
		e.preventDefault();
		state.installPrompt = e;
		if (!location.hash || location.hash === '#/') {
			viewHome();
		}
	});
	window.addEventListener('appinstalled', () => {
		state.installPrompt = null;
		state.installDismissed = true;
	});
	await loadLocal();
	try {
		state.tab = (await kvGet('tab')) === 'plans' ? 'plans' : 'plots';
		// Banner of the installation closed: shown again after 30 days
		state.installDismissed = (daysSince(await kvGet('installDismissed')) ?? 99) < 30;
	} catch (e) {
		// storage not available: defaults
	}
	renderStatus();
	// Opened from the address of a sign (?ref=...)
	const ref = new URLSearchParams(location.search).get('ref');
	if (ref && !location.hash) {
		const plot = findPlotByRef(ref);
		if (plot) {
			history.replaceState(null, '', location.pathname + '#/plot/' + plot.id);
		}
	}
	visited.push(location.hash || '#/');
	route();
	hideSplash(shownAt);
	window.addEventListener('hashchange', onHashChange);
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
