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
 * Texts of the app in Italian, English and French. The language is the one of the Dolibarr user, else the one of
 * the phone. Keys in English, %1 %2 replaced by the arguments.
 */

const TEXTS = {
	it: {
		AppTitle: 'App di campo',
		Plots: 'Appezzamenti',
		SearchPlot: 'Cerca appezzamento, azienda o coltura',
		ScanQr: 'Leggi QR',
		NearMe: 'Vicino a me',
		Refresh: 'Aggiorna',
		Back: 'Indietro',
		Online: 'In rete',
		Offline: 'Senza rete',
		SyncedAt: 'Dati del %1',
		NeverSynced: 'Nessun dato sul telefono: accedi con la rete per scaricarli.',
		Syncing: 'Aggiornamento...',
		SyncDone: 'Dati aggiornati',
		SyncFailed: 'Aggiornamento non riuscito: %1',
		OldData: 'Dati di %1 giorni fa: apri l\'app con la rete per aggiornarli. Su iPhone i dati delle app non aperte per circa 7 giorni possono essere cancellati.',
		LoginNeeded: 'Accesso a Dolibarr scaduto o mancante: i dati sul telefono restano utilizzabili.',
		Login: 'Accedi a Dolibarr',
		Forbidden: 'Il tuo utente non ha il permesso di usare l\'app di campo.',
		ApiNewer: 'Il server usa una versione più recente: ricarica l\'app.',
		NoPlots: 'Nessun appezzamento.',
		NoMatch: 'Nessun appezzamento trovato.',
		Farm: 'Azienda',
		Place: 'Località',
		Area: 'Superficie',
		OpenInDolibarr: 'Apri in Dolibarr',
		Navigate: 'Indicazioni',
		ProductionPlans: 'Piani di produzione aperti',
		NoPlan: 'Nessun piano di produzione aperto.',
		Season: 'Stagione %1',
		Draft: 'Bozza',
		Validated: 'Validato',
		SafetyUntil: 'Carenza fino al %1',
		SafetyLine: '%1 (trattamento %2 del %3, %4 giorni)',
		NoSafety: 'Nessun tempo di carenza in corso.',
		LastOperations: 'Ultime operazioni',
		LastHarvests: 'Ultime raccolte',
		NoRecords: 'Nessuna registrazione.',
		PlotNotFound: 'Appezzamento %1 non trovato nei dati del telefono. Aggiorna i dati con la rete.',
		ScanTitle: 'Inquadra il QR del cartello',
		ScanNotAvailable: 'Fotocamera non disponibile in questo browser: scrivi il riferimento.',
		CameraDenied: 'Fotocamera non disponibile: %1',
		TypeRef: 'Riferimento dell\'appezzamento',
		Open: 'Apri',
		Locating: 'Ricerca della posizione...',
		GpsDenied: 'Posizione non disponibile: %1',
		Accuracy: 'precisione %1 m',
		Distance: '%1 da qui',
		NoCoordinates: 'Appezzamenti senza coordinate: %1',
		Nearest: 'Il più vicino',
		ClearData: 'Cancella i dati da questo telefono',
		ClearConfirm: 'Cancellare i dati scaricati da questo telefono?',
		Cleared: 'Dati cancellati',
		Version: 'Versione %1',
		User: 'Utente: %1',
		UpdateReady: 'Nuova versione dell\'app pronta.',
		Reload: 'Ricarica',
	},
	en: {
		AppTitle: 'Field app',
		Plots: 'Plots',
		SearchPlot: 'Search plot, farm or crop',
		ScanQr: 'Scan QR',
		NearMe: 'Near me',
		Refresh: 'Refresh',
		Back: 'Back',
		Online: 'Online',
		Offline: 'Offline',
		SyncedAt: 'Data of %1',
		NeverSynced: 'No data on the phone: log in with the network to download them.',
		Syncing: 'Updating...',
		SyncDone: 'Data updated',
		SyncFailed: 'Update failed: %1',
		OldData: 'Data of %1 days ago: open the app with the network to update them. On iPhone the data of the apps not opened for about 7 days can be deleted.',
		LoginNeeded: 'Login to Dolibarr expired or missing: the data on the phone can still be used.',
		Login: 'Log in to Dolibarr',
		Forbidden: 'Your user is not allowed to use the field app.',
		ApiNewer: 'The server uses a newer version: reload the app.',
		NoPlots: 'No plot.',
		NoMatch: 'No plot found.',
		Farm: 'Farm',
		Place: 'Place',
		Area: 'Area',
		OpenInDolibarr: 'Open in Dolibarr',
		Navigate: 'Directions',
		ProductionPlans: 'Open production plans',
		NoPlan: 'No open production plan.',
		Season: 'Season %1',
		Draft: 'Draft',
		Validated: 'Validated',
		SafetyUntil: 'Pre-harvest interval until %1',
		SafetyLine: '%1 (treatment %2 of %3, %4 days)',
		NoSafety: 'No pre-harvest interval in progress.',
		LastOperations: 'Last operations',
		LastHarvests: 'Last harvests',
		NoRecords: 'No record.',
		PlotNotFound: 'Plot %1 not found in the data of the phone. Update the data with the network.',
		ScanTitle: 'Frame the QR code of the sign',
		ScanNotAvailable: 'Camera not available in this browser: type the reference.',
		CameraDenied: 'Camera not available: %1',
		TypeRef: 'Reference of the plot',
		Open: 'Open',
		Locating: 'Finding the position...',
		GpsDenied: 'Position not available: %1',
		Accuracy: 'accuracy %1 m',
		Distance: '%1 from here',
		NoCoordinates: 'Plots without coordinates: %1',
		Nearest: 'Nearest',
		ClearData: 'Delete the data from this phone',
		ClearConfirm: 'Delete the data downloaded on this phone?',
		Cleared: 'Data deleted',
		Version: 'Version %1',
		User: 'User: %1',
		UpdateReady: 'New version of the app ready.',
		Reload: 'Reload',
	},
	fr: {
		AppTitle: 'Application de terrain',
		Plots: 'Parcelles',
		SearchPlot: 'Chercher une parcelle, une exploitation ou une culture',
		ScanQr: 'Lire le QR',
		NearMe: 'Près de moi',
		Refresh: 'Actualiser',
		Back: 'Retour',
		Online: 'En ligne',
		Offline: 'Hors ligne',
		SyncedAt: 'Données du %1',
		NeverSynced: 'Aucune donnée sur le téléphone : connectez-vous avec le réseau pour les télécharger.',
		Syncing: 'Mise à jour...',
		SyncDone: 'Données mises à jour',
		SyncFailed: 'Mise à jour échouée : %1',
		OldData: 'Données d\'il y a %1 jours : ouvrez l\'application avec le réseau pour les mettre à jour. Sur iPhone, les données des applications non ouvertes depuis environ 7 jours peuvent être effacées.',
		LoginNeeded: 'Connexion à Dolibarr expirée ou absente : les données du téléphone restent utilisables.',
		Login: 'Se connecter à Dolibarr',
		Forbidden: 'Votre utilisateur n\'est pas autorisé à utiliser l\'application de terrain.',
		ApiNewer: 'Le serveur utilise une version plus récente : rechargez l\'application.',
		NoPlots: 'Aucune parcelle.',
		NoMatch: 'Aucune parcelle trouvée.',
		Farm: 'Exploitation',
		Place: 'Lieu',
		Area: 'Surface',
		OpenInDolibarr: 'Ouvrir dans Dolibarr',
		Navigate: 'Itinéraire',
		ProductionPlans: 'Plans de production ouverts',
		NoPlan: 'Aucun plan de production ouvert.',
		Season: 'Saison %1',
		Draft: 'Brouillon',
		Validated: 'Validé',
		SafetyUntil: 'Délai avant récolte jusqu\'au %1',
		SafetyLine: '%1 (traitement %2 du %3, %4 jours)',
		NoSafety: 'Aucun délai avant récolte en cours.',
		LastOperations: 'Dernières opérations',
		LastHarvests: 'Dernières récoltes',
		NoRecords: 'Aucun enregistrement.',
		PlotNotFound: 'Parcelle %1 introuvable dans les données du téléphone. Mettez à jour les données avec le réseau.',
		ScanTitle: 'Cadrez le QR code du panneau',
		ScanNotAvailable: 'Appareil photo non disponible dans ce navigateur : saisissez la référence.',
		CameraDenied: 'Appareil photo non disponible : %1',
		TypeRef: 'Référence de la parcelle',
		Open: 'Ouvrir',
		Locating: 'Recherche de la position...',
		GpsDenied: 'Position non disponible : %1',
		Accuracy: 'précision %1 m',
		Distance: 'à %1 d\'ici',
		NoCoordinates: 'Parcelles sans coordonnées : %1',
		Nearest: 'La plus proche',
		ClearData: 'Effacer les données de ce téléphone',
		ClearConfirm: 'Effacer les données téléchargées sur ce téléphone ?',
		Cleared: 'Données effacées',
		Version: 'Version %1',
		User: 'Utilisateur : %1',
		UpdateReady: 'Nouvelle version de l\'application prête.',
		Reload: 'Recharger',
	},
};

let lang = 'it';

/**
 * Choose the language: code of Dolibarr (it_IT) or of the browser (it-IT)
 *
 * @param {string} [code]
 * @returns {string} Language used
 */
export function setLang(code) {
	const short = String(code || navigator.language || 'it').slice(0, 2).toLowerCase();
	lang = (TEXTS[short] ? short : 'en');
	document.documentElement.lang = lang;
	return lang;
}

/**
 * Text in the current language
 *
 * @param {string} key Key
 * @param {...any} args Values of %1, %2, ...
 * @returns {string}
 */
export function t(key, ...args) {
	const text = (TEXTS[lang] && TEXTS[lang][key]) || TEXTS.en[key] || key;
	return text.replace(/%(\d)/g, (m, n) => (args[n - 1] !== undefined ? String(args[n - 1]) : ''));
}

/**
 * Date of the server (2026-11-18 or 2026-11-18T08:30) as text, without changing the time zone
 *
 * @param {string|null} value
 * @returns {string}
 */
export function fmtDate(value) {
	if (!value) {
		return '';
	}
	const m = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/.exec(value);
	if (!m) {
		return String(value);
	}
	const day = (lang === 'en' ? m[2] + '/' + m[3] + '/' + m[1] : m[3] + '/' + m[2] + '/' + m[1]);
	return m[4] ? day + ' ' + m[4] + ':' + m[5] : day;
}

/**
 * Number with the decimal separator of the language
 *
 * @param {number} value
 * @param {number} [decimals]
 * @returns {string}
 */
export function fmtNumber(value, decimals = 2) {
	return Number(value).toLocaleString(lang === 'en' ? 'en-GB' : lang, { maximumFractionDigits: decimals });
}
