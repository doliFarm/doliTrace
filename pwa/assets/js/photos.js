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
 * Photos of the records made on the phone (phase P3): reduced on the phone, kept in IndexedDB until they are sent,
 * sent one by one after their record (the server adds them to the documents of the draft). Each photo has its own
 * identifier: sent twice, the server does not add it again.
 *
 * Photo: {photo_id, record_id, user_id, created_at, blob, status: pending|error, error, message}
 */

import { photoAll, photoPut, photoDelete } from './store.js';
import { getSession, sendPhoto, ApiError } from './api.js';
import { newClientId } from './queue.js';

/** Photos of a record (the server accepts the same number) */
export const MAX_PHOTOS = 5;
/** Longest side of a reduced photo, in pixels */
const MAX_SIDE = 1600;
/** Size wanted for a photo */
const MAX_BYTES = 1000000;

/**
 * Image of a file, with the orientation of the camera applied
 *
 * @param {File} file
 * @returns {Promise<ImageBitmap|HTMLImageElement>}
 */
async function loadImage(file) {
	if (self.createImageBitmap) {
		try {
			return await createImageBitmap(file, { imageOrientation: 'from-image' });
		} catch (e) {
			// older Safari: through an <img>
		}
	}
	const url = URL.createObjectURL(file);
	try {
		const img = new Image();
		img.decoding = 'async';
		img.src = url;
		await img.decode();
		return img;
	} finally {
		URL.revokeObjectURL(url);
	}
}

/**
 * Reduce a photo: longest side 1600 px, JPEG, under 1 MB when possible
 *
 * @param {File} file Photo of the camera or of the gallery
 * @returns {Promise<Blob>} JPEG
 */
export async function reducePhoto(file) {
	const img = await loadImage(file);
	const width = img.width;
	const height = img.height;
	const scale = Math.min(1, MAX_SIDE / Math.max(width, height));
	const canvas = document.createElement('canvas');
	canvas.width = Math.max(1, Math.round(width * scale));
	canvas.height = Math.max(1, Math.round(height * scale));
	canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
	if (img.close) {
		img.close();
	}
	let blob = null;
	for (const quality of [0.82, 0.7, 0.6, 0.5]) {
		blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
		if (!blob || blob.size <= MAX_BYTES) {
			break;
		}
	}
	if (!blob) {
		throw new Error('image');
	}
	return blob;
}

/** New photo of a record (not saved yet) */
export const newPhoto = (blob) => ({ photo_id: newClientId(), blob, status: 'pending', error: '', message: '' });

export const loadPhotos = () => photoAll();
export const removePhoto = (photoId) => photoDelete(photoId);

/**
 * Save the photos of a record: the new ones are added, the ones removed from the form are deleted
 *
 * @param {Object} record Record of the queue (client_id, user_id, created_at)
 * @param {Object[]} photos Photos of the form
 * @returns {Promise<void>}
 */
export async function savePhotos(record, photos) {
	const keep = new Set(photos.map((p) => p.photo_id));
	for (const old of (await photoAll()).filter((p) => p.record_id === record.client_id && !keep.has(p.photo_id))) {
		await photoDelete(old.photo_id);
	}
	for (const photo of photos) {
		await photoPut(Object.assign({}, photo, {
			record_id: record.client_id,
			user_id: record.user_id,
			created_at: photo.created_at || record.created_at,
			status: 'pending',
			error: '',
			message: '',
		}));
	}
}

/** Delete the photos of a record (record deleted from the queue) */
export async function deletePhotosOf(recordId) {
	for (const photo of (await photoAll()).filter((p) => p.record_id === recordId)) {
		await photoDelete(photo.photo_id);
	}
}

/**
 * Send the photos of the records already sent
 *
 * @param {Object} session Answer of action=session
 * @param {Set<string>} waiting Identifiers of the records still in the queue: their photos wait
 * @param {boolean} withErrors Also the photos refused before
 * @returns {Promise<{session: Object, sent: number, errors: number}>}
 */
export async function sendPhotos(session, waiting, withErrors) {
	const todo = (await photoAll()).filter((p) => p.user_id === session.user.id && !waiting.has(p.record_id) && (withErrors || p.status !== 'error'));
	let sent = 0;
	let errors = 0;
	for (const photo of todo) {
		let answer;
		try {
			answer = await sendPhoto(photo, session.token);
		} catch (e) {
			if (e instanceof ApiError && e.code === 'token') {
				session = await getSession();
				answer = await sendPhoto(photo, session.token);
			} else if (e instanceof ApiError && e.code === 'action') {
				// Server without photos (doliTrace older than 1.5.3): they wait on the phone
				break;
			} else {
				throw e;
			}
		}
		const result = answer.result || {};
		if (result.ok) {
			await photoDelete(photo.photo_id);
			sent++;
		} else {
			await photoPut(Object.assign({}, photo, { status: 'error', error: result.error, message: result.message }));
			errors++;
		}
	}
	return { session, sent, errors };
}
