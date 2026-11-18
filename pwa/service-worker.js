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
 * Service worker of the field app: keeps the files of the app so that it starts without network. The data of
 * Dolibarr (ajax/pwa.php) and the login page always go to the network: the app keeps them in IndexedDB.
 * Change VERSION at every change of the files of the app.
 */

const VERSION = 'dolitrace-field-1.6.1';
const FILES = [
	'./',
	'./index.html',
	'./manifest.json',
	'./assets/css/app.css',
	'./assets/js/app.js',
	'./assets/js/api.js',
	'./assets/js/i18n.js',
	'./assets/js/store.js',
	'./assets/js/queue.js',
	'./assets/js/photos.js',
	'./assets/js/treatment.js',
	'./assets/js/weather.js',
	'./assets/vendor/jsqr/jsQR.js',
	'./assets/img/icon-192.png',
	'./assets/img/icon-512.png',
	'./assets/img/icon-maskable-512.png',
	'./assets/img/apple-touch-icon.png',
	'./assets/img/favicon-32.png',
	'./assets/img/dolifarm-logo.jpg',
];
const SCOPE = new URL('./', self.location.href).pathname;

self.addEventListener('install', (event) => {
	event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(FILES.map((f) => new Request(f, { cache: 'reload' })))));
});

self.addEventListener('activate', (event) => {
	event.waitUntil(
		caches.keys()
			.then((keys) => Promise.all(keys.filter((k) => k.startsWith('dolitrace-field-') && k !== VERSION).map((k) => caches.delete(k))))
			.then(() => self.clients.claim()),
	);
});

// The page asks to use the new version
self.addEventListener('message', (event) => {
	if (event.data === 'skipWaiting') {
		self.skipWaiting();
	}
});

self.addEventListener('fetch', (event) => {
	const request = event.request;
	if (request.method !== 'GET') {
		return;
	}
	const url = new URL(request.url);
	// Only the files of the app; PHP pages (login.php, ajax) and other sites go to the network
	if (url.origin !== self.location.origin || !url.pathname.startsWith(SCOPE) || url.pathname.endsWith('.php')) {
		return;
	}
	// Opening of the app, also from the address of a sign (index.html?ref=...)
	if (request.mode === 'navigate') {
		event.respondWith(caches.match('./index.html').then((cached) => cached || fetch(request)));
		return;
	}
	event.respondWith(caches.match(request, { ignoreSearch: true }).then((cached) => cached || fetch(request)));
});
