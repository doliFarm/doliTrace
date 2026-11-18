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
 * Queue of the records made on the phone (phase P2): operations and harvests wait in IndexedDB and are sent as
 * drafts when the network and the session are there. Each record has an identifier made by the phone: sent twice,
 * the server returns the draft already created (no duplicates).
 *
 * Record: {client_id, kind: operation|harvest|treatment, user_id, created_at, data, status: pending|error, error, message}
 */

import { queueAll, queuePut, queueDelete, kvGet, kvSet } from './store.js';
import { getSession, sendRecords, ApiError } from './api.js';

/** Records sent in one call (limit of the server: 50) */
const BATCH = 20;
/** Sent records kept in the list "sent recently" */
const KEEP_SENT = 20;

/**
 * New identifier of a record (UUID v4)
 *
 * @returns {string}
 */
export function newClientId() {
	if (self.crypto && typeof self.crypto.randomUUID === 'function') {
		return self.crypto.randomUUID();
	}
	const b = self.crypto.getRandomValues(new Uint8Array(16));
	b[6] = (b[6] & 0x0f) | 0x40;
	b[8] = (b[8] & 0x3f) | 0x80;
	const hex = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('');
	return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
}

/**
 * Save a record in the queue (new, or corrected after an error)
 *
 * @param {Object} record
 * @returns {Promise<void>}
 */
export async function saveRecord(record) {
	await queuePut(Object.assign({}, record, { status: 'pending', error: '', message: '' }));
}

export const loadQueue = async () => (await queueAll()).sort((a, b) => String(a.created_at).localeCompare(String(b.created_at)));
export const removeRecord = (clientId) => queueDelete(clientId);
export const loadSent = async () => (await kvGet('sent')) || [];

/**
 * Send the records of the user
 *
 * @param {Object} session Answer of action=session (token, user)
 * @param {boolean} withErrors Also the records refused before (asked by the user after a correction)
 * @returns {Promise<{session: Object, sent: number, errors: number, warned: number}>} The session may be renewed (new token)
 */
export async function sendQueue(session, withErrors) {
	const all = await loadQueue();
	const todo = all.filter((r) => r.user_id === session.user.id && (withErrors || r.status !== 'error'));
	let sent = 0;
	let errors = 0;
	let warned = 0;
	const done = await loadSent();
	for (let i = 0; i < todo.length; i += BATCH) {
		const batch = todo.slice(i, i + BATCH);
		const payload = batch.map((r) => ({ client_id: r.client_id, kind: r.kind, created_at: r.created_at, data: r.data }));
		let answer;
		try {
			answer = await sendRecords(payload, session.token);
		} catch (e) {
			// Token changed (new login or other page of Dolibarr with a new session): ask it again, once
			if (e instanceof ApiError && e.code === 'token') {
				session = await getSession();
				answer = await sendRecords(payload, session.token);
			} else {
				throw e;
			}
		}
		for (const result of answer.results || []) {
			const record = batch.find((r) => r.client_id === result.client_id);
			if (!record) {
				continue;
			}
			if (result.ok) {
				await queueDelete(record.client_id);
				// Treatments: warnings of the register computed by the server
				const warnings = Array.isArray(result.warnings) ? result.warnings : [];
				done.unshift({ client_id: record.client_id, kind: record.kind, id: result.id, ref: result.ref, url: result.url, label: record.summary || '', warnings, sent_at: new Date().toISOString() });
				sent++;
				warned += (warnings.length ? 1 : 0);
			} else {
				await queuePut(Object.assign({}, record, { status: 'error', error: result.error, message: result.message }));
				errors++;
			}
		}
	}
	await kvSet('sent', done.slice(0, KEEP_SENT));
	return { session, sent, errors, warned };
}
