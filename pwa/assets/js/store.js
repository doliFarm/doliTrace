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
 * Data kept on the phone (IndexedDB): "kv" for the session and the data of the server, "queue" for the records
 * waiting to be sent (phase P2), "photos" for their photos (phase P3). The data stay on the phone for days without
 * network.
 */

const DB_NAME = 'dolitrace-field';
const DB_VERSION = 2;

let dbPromise = null;

/**
 * Open the database once
 *
 * @returns {Promise<IDBDatabase>}
 */
function openDb() {
	if (!dbPromise) {
		dbPromise = new Promise((resolve, reject) => {
			const request = indexedDB.open(DB_NAME, DB_VERSION);
			request.onupgradeneeded = () => {
				const db = request.result;
				if (!db.objectStoreNames.contains('kv')) {
					db.createObjectStore('kv');
				}
				if (!db.objectStoreNames.contains('queue')) {
					db.createObjectStore('queue', { keyPath: 'client_id' });
				}
				if (!db.objectStoreNames.contains('photos')) {
					db.createObjectStore('photos', { keyPath: 'photo_id' });
				}
			};
			request.onsuccess = () => resolve(request.result);
			request.onerror = () => reject(request.error);
		});
	}
	return dbPromise;
}

/**
 * Run a request on a store
 *
 * @param {string} store Store
 * @param {IDBTransactionMode} mode Mode
 * @param {(s: IDBObjectStore) => IDBRequest} fn Request
 * @returns {Promise<any>}
 */
async function run(store, mode, fn) {
	const db = await openDb();
	return new Promise((resolve, reject) => {
		const tx = db.transaction(store, mode);
		const request = fn(tx.objectStore(store));
		tx.oncomplete = () => resolve(request.result);
		tx.onerror = () => reject(tx.error);
		tx.onabort = () => reject(tx.error);
	});
}

export const kvGet = (key) => run('kv', 'readonly', (s) => s.get(key));
export const kvSet = (key, value) => run('kv', 'readwrite', (s) => s.put(value, key));
export const kvClear = () => run('kv', 'readwrite', (s) => s.clear());

export const queueAll = () => run('queue', 'readonly', (s) => s.getAll());
export const queuePut = (record) => run('queue', 'readwrite', (s) => s.put(record));
export const queueDelete = (clientId) => run('queue', 'readwrite', (s) => s.delete(clientId));
export const queueCount = () => run('queue', 'readonly', (s) => s.count());

export const photoAll = () => run('photos', 'readonly', (s) => s.getAll());
export const photoPut = (photo) => run('photos', 'readwrite', (s) => s.put(photo));
export const photoDelete = (photoId) => run('photos', 'readwrite', (s) => s.delete(photoId));
