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
 * Calls to the endpoint of doliTrace (ajax/pwa.php) with the session of Dolibarr
 */

/** Version of the exchange known by this app (api of ajax/pwa.php) */
export const API_VERSION = 1;

const ENDPOINT = new URL('../ajax/pwa.php', self.location.href).href;

/** No network, or server not reachable */
export class OfflineError extends Error {}
/** No session: Dolibarr answered with its login page */
export class LoginError extends Error {}
/** Error of the endpoint (code in .code) */
export class ApiError extends Error {
	constructor(code, message, status) {
		super(message || code);
		this.code = code;
		this.status = status;
	}
}

/**
 * Call the endpoint
 *
 * @param {string} action session, data, send or photo
 * @param {{token?: string, body?: any, form?: FormData}} [options]
 * @returns {Promise<any>} Answer
 */
async function call(action, options = {}) {
	let url = ENDPOINT + '?action=' + encodeURIComponent(action);
	if (options.token) {
		url += '&token=' + encodeURIComponent(options.token);
	}
	const init = { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } };
	if (options.form !== undefined) {
		// multipart: the browser sets the Content-Type with the boundary
		init.method = 'POST';
		init.body = options.form;
	} else if (options.body !== undefined) {
		init.method = 'POST';
		init.headers['Content-Type'] = 'application/json';
		init.body = JSON.stringify(options.body);
	}
	let response;
	try {
		response = await fetch(url, init);
	} catch (e) {
		throw new OfflineError(String(e));
	}
	const type = response.headers.get('content-type') || '';
	if (!type.includes('application/json')) {
		// Login page of Dolibarr (session expired), or a page of a proxy
		if (response.ok) {
			throw new LoginError('login');
		}
		throw new OfflineError('HTTP ' + response.status);
	}
	const answer = await response.json();
	if (!answer.ok) {
		throw new ApiError(answer.error, answer.message, response.status);
	}
	return answer;
}

export const getSession = () => call('session');
export const getData = () => call('data');
export const sendRecords = (records, token) => call('send', { token, body: { records } });

/**
 * Send a photo of a record already sent
 *
 * @param {Object} photo {photo_id, record_id, created_at, blob}
 * @param {string} token
 * @returns {Promise<any>} Answer, with .result
 */
export function sendPhoto(photo, token) {
	const form = new FormData();
	form.append('record', photo.record_id);
	form.append('photo_id', photo.photo_id);
	form.append('created_at', photo.created_at || '');
	form.append('photo', photo.blob, 'photo.jpg');
	return call('photo', { token, form });
}

/** Page that asks the login of Dolibarr and comes back to the app */
export const LOGIN_URL = new URL('login.php', self.location.href).href;
