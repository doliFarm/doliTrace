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
 * Treatments and fertilizations made in the field (phase P3): draft on the plot of a production plan, with one or more
 * products of the same tank. The phone gives the warnings it can compute without network (uses of the label, dose,
 * organic, operator, machine, lot); the server adds those of the whole register after the sending.
 *
 * data: {cropplan_id, kind: 1|2, date, date_end, area_ha, bbch, operator_id, machine_id, water_volume, note,
 * products: [{item_id, adversity, dose, unit_id, qty_total, batch}]}
 */

import { t, fmtNumber, fmtDate } from './i18n.js';

export const KIND_PLANTPROTECTION = 1;
export const KIND_FERTILIZATION = 2;

/** Treated quantity in the basis of a unit of dose (same rules as dolitraceTreatmentQtyInBasis) */
function qtyInBasis(basis, areaHa, water) {
	if (!(areaHa > 0)) {
		return null;
	}
	if (basis === 'HA') {
		return areaHa;
	}
	if (basis === 'M2') {
		return areaHa * 10000;
	}
	if (basis === 'HL') {
		return water > 0 ? water / 100 * areaHa : null;
	}
	return null;
}

/**
 * Total quantity of a line computed from the dose, as the server does when it is not typed
 *
 * @returns {{qty: number, unit: string}|null}
 */
export function computedQty(line, unit, areaHa, water) {
	if (!unit || !(line.dose > 0)) {
		return null;
	}
	const base = qtyInBasis(unit.basis, areaHa, water);
	return base === null ? null : { qty: line.dose * unit.factor * base, unit: unit.qty_unit };
}

/**
 * Warnings computed on the phone. Not blocking: the record is saved anyway.
 *
 * @param {Object} T Data of the treatments (data.treatment)
 * @param {Object} ctx {plan, plot, machines, organic}
 * @param {Object} d Data of the record
 * @returns {string[]}
 */
export function treatmentWarnings(T, ctx, d) {
	const warnings = [];
	const day = (d.date || '').slice(0, 10);
	const kind = Number(d.kind);
	const operator = (T.operators || []).find((o) => o.id === Number(d.operator_id));
	if (kind === KIND_PLANTPROTECTION && operator && operator.license_end && operator.license_end < day) {
		warnings.push(t('TWLicense', fmtDate(operator.license_end)));
	}
	const machine = (ctx.machines || []).find((m) => m.id === Number(d.machine_id));
	if (machine && machine.check_end && machine.check_end < day) {
		warnings.push(t('TWMachine', fmtDate(machine.check_end)));
	}
	if (kind === KIND_PLANTPROTECTION && !operator) {
		warnings.push(t('TWNoOperator'));
	}
	const crop = (ctx.plan.eppo || '').toUpperCase();
	for (const line of d.products || []) {
		const unit = (T.dose_units || []).find((u) => u.id === Number(line.unit_id));
		if (kind === KIND_PLANTPROTECTION) {
			const item = (T.agrodrugs || []).find((a) => a.id === Number(line.item_id));
			if (!item) {
				continue;
			}
			if (item.warning) {
				warnings.push(item.warning);
			}
			if (ctx.organic && item.organic === 'no') {
				warnings.push(t('TWNotOrganic', item.label));
			} else if (ctx.organic && item.organic === 'verify') {
				warnings.push(t('TWOrganicVerify', item.label));
			}
			// Authorised uses of the label on the crop of the plan
			if (item.has_uses && crop) {
				const oncrop = (T.uses || []).filter((u) => u.agrodrug_id === item.id && u.crop === crop);
				if (!oncrop.length) {
					warnings.push(t('TWUseCrop', item.label, ctx.plan.crop || crop));
				} else {
					const adversity = (line.adversity || '').toUpperCase();
					const matching = oncrop.filter((u) => !u.adversity || !adversity || u.adversity === adversity);
					if (!matching.length) {
						warnings.push(t('TWUseAdversity', item.label, adversity));
					} else if (unit && line.dose > 0) {
						// Dose inside the range of a use with a comparable unit
						let compared = false;
						let inside = false;
						const ranges = [];
						for (const use of matching) {
							const useunit = (T.dose_units || []).find((u) => u.code === use.dose_unit);
							if ((use.dose_min === null && use.dose_max === null) || !useunit || useunit.basis !== unit.basis || useunit.qty_unit !== unit.qty_unit) {
								continue;
							}
							compared = true;
							const dose = line.dose * unit.factor;
							const min = use.dose_min !== null ? use.dose_min * useunit.factor : null;
							const max = use.dose_max !== null ? use.dose_max * useunit.factor : null;
							if ((min === null || dose >= min * 0.9999) && (max === null || dose <= max * 1.0001)) {
								inside = true;
								break;
							}
							ranges.push((use.dose_min !== null ? fmtNumber(use.dose_min) : '') + ' - ' + (use.dose_max !== null ? fmtNumber(use.dose_max) : '') + ' ' + useunit.label);
						}
						if (compared && !inside) {
							warnings.push(t('TWUseDose', item.label, fmtNumber(line.dose) + ' ' + unit.label, [...new Set(ranges)].join('; ')));
						}
					}
				}
			}
			// Dose by hectare, compared with the maximum of the product
			const total = line.qty_total > 0 ? { qty: line.qty_total, unit: unit ? unit.qty_unit : '' } : computedQty(line, unit, d.area_ha, d.water_volume);
			if (item.max_dose_ha && total && d.area_ha > 0 && ['kg', 'l', 't'].includes(total.unit)) {
				const doseHa = total.qty * (total.unit === 't' ? 1000 : 1) / d.area_ha;
				if (doseHa > item.max_dose_ha * 1.0001) {
					const u = total.unit === 'l' ? 'l/ha' : 'kg/ha';
					warnings.push(t('TWDoseMax', item.label, fmtNumber(doseHa) + ' ' + u, fmtNumber(item.max_dose_ha) + ' ' + u));
				}
			}
		} else {
			const item = (T.fertilizers || []).find((f) => f.id === Number(line.item_id));
			if (item && ctx.organic && item.organic === 'no') {
				warnings.push(t('TWNotOrganic', item.label));
			}
		}
		// Lot: stock as of the download
		if (line.batch) {
			const item = kind === KIND_PLANTPROTECTION
				? (T.agrodrugs || []).find((a) => a.id === Number(line.item_id))
				: (T.fertilizers || []).find((f) => f.id === Number(line.item_id));
			const lot = item ? (T.lots || []).find((l) => l.farm_id === ctx.plan.farm_id && l.product_id === item.product_id && l.batch === line.batch) : null;
			const total = line.qty_total > 0 ? { qty: line.qty_total } : computedQty(line, unit, d.area_ha, d.water_volume);
			if (lot && total && total.qty > lot.qty * 1.0001) {
				warnings.push(t('TWLotStock', line.batch, fmtNumber(lot.qty), fmtDate(T.stock_date)));
			}
		}
	}
	return [...new Set(warnings)];
}

/**
 * Check before saving: same rules as the server
 *
 * @returns {string} Error, empty when valid
 */
export function checkTreatment(d, limit) {
	if (![KIND_PLANTPROTECTION, KIND_FERTILIZATION].includes(Number(d.kind))) {
		return t('Required', t('TreatmentKind'));
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
		return t('InvalidValue', t('TreatedArea'));
	}
	if (d.water_volume !== undefined && !(d.water_volume >= 0)) {
		return t('InvalidValue', t('WaterVolume'));
	}
	if (d.bbch && !/^\d{1,3}$/.test(d.bbch)) {
		return t('InvalidValue', 'BBCH');
	}
	if (!(d.products || []).length) {
		return t('TreatmentNoProduct');
	}
	for (const [i, line] of d.products.entries()) {
		const n = ' (' + (i + 1) + ')';
		if (!line.item_id) {
			return t('Required', t('Product')) + n;
		}
		if (Number(d.kind) === KIND_PLANTPROTECTION && !line.adversity) {
			return t('Required', t('Adversity')) + n;
		}
		if (!(line.dose > 0)) {
			return t('InvalidValue', t('Dose')) + n;
		}
		if (!line.unit_id) {
			return t('Required', t('DoseUnit')) + n;
		}
		if (line.qty_total !== undefined && !(line.qty_total > 0)) {
			return t('InvalidValue', t('QtyTotal')) + n;
		}
	}
	return '';
}

/** One line describing a treatment of the queue */
export function treatmentSummary(T, d) {
	const kind = Number(d.kind) === KIND_FERTILIZATION ? t('Fertilization') : t('Treatment');
	const list = Number(d.kind) === KIND_FERTILIZATION ? (T && T.fertilizers) : (T && T.agrodrugs);
	const names = (d.products || []).map((p) => ((list || []).find((x) => x.id === Number(p.item_id)) || {}).label).filter(Boolean);
	return kind + (names.length ? ': ' + names.join(', ') : '');
}

/**
 * Fields of the form of a treatment
 *
 * @param {Object} ui {h, select, formRow, decimalValue, nowLocal}
 * @param {Object} T Data of the treatments
 * @param {Object} ctx {plan, plot, machines, organic, userId}
 * @param {Object} d Data of the record (empty for a new one)
 * @returns {{fields: HTMLElement[], products: function(): Object[], refresh: function(): void}}
 */
export function treatmentFields(ui, T, ctx, d) {
	const { h, select, formRow, decimalValue, nowLocal } = ui;
	const { plan, plot } = ctx;
	const crop = (plan.eppo || '').toUpperCase();
	let kind = Number(d.kind) || KIND_PLANTPROTECTION;
	const warningsBox = h('div.note.warn', { hidden: true });
	const lotNote = h('div.item-sub', null, (T.lots || []).some((l) => l.farm_id === plan.farm_id) ? t('LotStockDate', fmtDate(T.stock_date)) : '');
	const lines = [];
	const list = h('div.products');
	let current = () => ({});

	const operators = (T.operators || []).filter((o) => !o.farm_id || o.farm_id === plan.farm_id || o.user_id === ctx.userId);
	const mine = operators.find((o) => o.user_id === ctx.userId);
	const kindSelect = select('kind', [
		{ value: KIND_PLANTPROTECTION, label: t('Treatment') },
		{ value: KIND_FERTILIZATION, label: t('Fertilization') },
	], kind);

	/** Products offered: for plant protection those authorised on the crop first, the others with "Show all" */
	const itemOptions = (showAll) => {
		if (kind === KIND_FERTILIZATION) {
			return (T.fertilizers || []).map((f) => ({ value: f.id, label: f.label + (ctx.organic && f.organic === 'no' ? ' ⚠' : '') }));
		}
		const authorised = new Set((T.uses || []).filter((u) => u.crop === crop).map((u) => u.agrodrug_id));
		const all = (T.agrodrugs || []).map((a) => ({ value: a.id, label: a.label + (a.warning || (ctx.organic && a.organic === 'no') ? ' ⚠' : ''), ok: authorised.has(a.id) || !a.has_uses }));
		const ok = all.filter((a) => a.ok);
		return showAll || !ok.length ? all.map((a) => Object.assign({}, a, { label: a.label + (a.ok ? '' : ' · ' + t('NotOnCrop')) })) : ok;
	};
	const unitsOfKind = () => (T.dose_units || []).filter((u) => u.kind === kind);

	/** Line of a product: product, adversity, dose and unit, total quantity, lot */
	const addLine = (p) => {
		p = p || {};
		const row = { el: null };
		const showAll = h('input', { type: 'checkbox' });
		const item = h('select.search');
		const adversity = h('select.search');
		const otherAdversity = h('select.search', { hidden: true });
		const dose = h('input.search', { type: 'text', inputmode: 'decimal', autocomplete: 'off', value: p.dose !== undefined ? String(p.dose).replace('.', ',') : '' });
		const unit = h('select.search');
		const qty = h('input.search', { type: 'text', inputmode: 'decimal', autocomplete: 'off', value: p.qty_total !== undefined ? String(p.qty_total).replace('.', ',') : '' });
		const qtyHint = h('div.item-sub');
		const lot = h('select.search');
		const fill = (el, options, value, empty) => {
			const nodes = options.map((o) => h('option', { value: o.value, selected: String(o.value) === String(value ?? '') }, o.label));
			if (empty !== undefined) {
				nodes.unshift(h('option', { value: '' }, empty));
			}
			el.replaceChildren(...nodes);
		};
		const itemOf = () => ((kind === KIND_FERTILIZATION ? T.fertilizers : T.agrodrugs) || []).find((x) => x.id === Number(item.value)) || null;
		const drawItem = (value) => {
			let options = itemOptions(showAll.checked);
			// A product not authorised on the crop, chosen before: the whole list
			if (value && !showAll.checked && !options.some((o) => String(o.value) === String(value))) {
				showAll.checked = true;
				options = itemOptions(true);
			}
			fill(item, options, value, t('Choose'));
		};
		const drawAdversity = (value) => {
			const chosen = itemOf();
			const uses = chosen ? (T.uses || []).filter((u) => u.agrodrug_id === chosen.id && u.crop === crop && u.adversity) : [];
			const seen = new Set();
			const options = [];
			for (const u of uses) {
				if (!seen.has(u.adversity)) {
					seen.add(u.adversity);
					options.push({ value: u.adversity, label: u.adversity_label || u.adversity });
				}
			}
			options.push({ value: '*', label: t('OtherAdversity') });
			const inUses = value && seen.has(value);
			// One use: proposed; no use with a pest on the crop: the whole dictionary
			fill(adversity, options, value ? (inUses ? value : '*') : (options.length === 2 ? options[0].value : (options.length === 1 ? '*' : '')), t('Choose'));
			fill(otherAdversity, (T.adversities || []).map((a) => ({ value: a.code, label: a.label + ' (' + a.code + ')' })), inUses ? '' : value, t('Choose'));
			otherAdversity.hidden = adversity.value !== '*';
		};
		const drawLots = (value) => {
			const chosen = itemOf();
			const lots = chosen && chosen.product_id ? (T.lots || []).filter((l) => l.farm_id === plan.farm_id && l.product_id === chosen.product_id) : [];
			const options = lots.map((l) => ({ value: l.batch, label: l.batch + ' · ' + fmtNumber(l.qty) + (l.eatby ? ' · ' + t('EatBy', fmtDate(l.eatby)) : '') }));
			if (value && !lots.some((l) => l.batch === value)) {
				options.push({ value, label: value });
			}
			fill(lot, options, value || (lots.length === 1 ? lots[0].batch : ''), lots.length ? t('LotChoose') : t('LotNone'));
			lot.disabled = !options.length;
		};
		const drawUnits = (value) => {
			const units = unitsOfKind();
			const def = units.find((u) => u.code === (kind === KIND_FERTILIZATION ? 'Q_HA' : 'KG_HA'));
			fill(unit, units.map((u) => ({ value: u.id, label: u.label })), value || (def ? def.id : ''));
		};
		row.read = () => {
			const out = {};
			if (item.value) {
				out.item_id = Number(item.value);
			}
			const adv = adversity.value === '*' ? otherAdversity.value : adversity.value;
			if (kind === KIND_PLANTPROTECTION && adv) {
				out.adversity = adv;
			}
			if (dose.value.trim() !== '') {
				out.dose = decimalValue(dose.value);
			}
			if (unit.value) {
				out.unit_id = Number(unit.value);
			}
			if (qty.value.trim() !== '') {
				out.qty_total = decimalValue(qty.value);
			}
			if (lot.value) {
				out.batch = lot.value;
			}
			return out;
		};
		row.hint = () => {
			const d2 = current();
			const u = (T.dose_units || []).find((x) => x.id === Number(unit.value));
			const c = computedQty(row.read(), u, d2.area_ha, d2.water_volume);
			qtyHint.textContent = c ? t('QtyComputed', fmtNumber(c.qty) + ' ' + c.unit) : '';
		};
		drawItem(p.item_id);
		drawAdversity(p.adversity);
		drawUnits(p.unit_id);
		drawLots(p.batch);
		showAll.addEventListener('change', () => drawItem(item.value));
		item.addEventListener('change', () => {
			drawAdversity('');
			drawLots('');
			refresh();
		});
		adversity.addEventListener('change', () => {
			otherAdversity.hidden = adversity.value !== '*';
			refresh();
		});
		for (const el of [otherAdversity, unit, lot]) {
			el.addEventListener('change', refresh);
		}
		for (const el of [dose, qty]) {
			el.addEventListener('input', refresh);
		}
		row.el = h('div.card.product-line', null,
			h('div.card-title', null, kind === KIND_FERTILIZATION ? t('Fertilizer') : t('Product'),
				h('button.btn.small.danger.right', {
					type: 'button',
					onclick: () => {
						lines.splice(lines.indexOf(row), 1);
						row.el.remove();
						refresh();
					},
				}, t('Remove'))),
			formRow(kind === KIND_FERTILIZATION ? t('Fertilizer') : t('Product'), item, true),
			kind === KIND_PLANTPROTECTION ? h('label.check', null, showAll, ' ', t('ShowAllProducts')) : null,
			kind === KIND_PLANTPROTECTION ? formRow(t('Adversity'), h('div', null, adversity, otherAdversity), true) : null,
			formRow(t('Dose'), dose, true),
			formRow(t('DoseUnit'), unit, true),
			formRow(t('QtyTotal'), h('div', null, qty, qtyHint)),
			formRow(t('Lot'), lot),
		);
		lines.push(row);
		list.append(row.el);
	};

	const products = () => lines.map((r) => r.read());
	const refresh = () => {
		lines.forEach((r) => r.hint());
		const warnings = treatmentWarnings(T, ctx, Object.assign(current(), { products: products() }));
		warningsBox.replaceChildren(h('strong', null, t('WarningsTitle')), h('ul', null, warnings.map((w) => h('li', null, w))));
		warningsBox.hidden = !warnings.length;
	};
	const drawLines = (items) => {
		lines.splice(0);
		list.replaceChildren();
		(items.length ? items : [{}]).forEach(addLine);
		addButton.textContent = '+ ' + (kind === KIND_FERTILIZATION ? t('AddFertilizer') : t('AddProduct'));
		refresh();
	};
	const addButton = h('button.btn', {
		type: 'button',
		onclick: () => {
			addLine({});
			refresh();
		},
	});
	kindSelect.addEventListener('change', () => {
		kind = Number(kindSelect.value);
		drawLines([]);
	});

	const machines = ctx.machines || [];
	const fields = [
		formRow(t('TreatmentKind'), kindSelect, true),
		formRow(t('DateStart'), h('input.search', { type: 'datetime-local', name: 'date', value: d.date || nowLocal(), required: true }), true),
		formRow(t('DateEnd'), h('input.search', { type: 'datetime-local', name: 'date_end', value: d.date_end || '' })),
		formRow(t('TreatedArea'), h('input.search', { type: 'text', name: 'area_ha', inputmode: 'decimal', autocomplete: 'off', value: d.area_ha || (plot && plot.area_ha ? String(plot.area_ha).replace('.', ',') : '') })),
		formRow(t('Bbch'), h('input.search', { type: 'text', name: 'bbch', maxlength: 3, inputmode: 'numeric', pattern: '[0-9]{1,3}', value: d.bbch || '' })),
		operators.length ? formRow(t('Operator'), select('operator_id', operators.map((o) => ({ value: o.id, label: o.label })), d.operator_id || (mine ? mine.id : ''), t('NoOperator'))) : null,
		machines.length ? formRow(t('Machine'), select('machine_id', machines.map((m) => ({ value: m.id, label: [m.label, m.ref].filter(Boolean).join(' - ') })), d.machine_id, t('None'))) : null,
		formRow(t('WaterVolume'), h('input.search', { type: 'text', name: 'water_volume', inputmode: 'decimal', autocomplete: 'off', value: d.water_volume ? String(d.water_volume).replace('.', ',') : '' })),
		h('h2', null, t('Products')),
		list,
		h('div.buttons', null, addButton),
		lotNote,
		formRow(t('Note'), h('textarea.search', { name: 'note', rows: 3, maxlength: 2000 }, d.note || '')),
		warningsBox,
	];
	return {
		fields,
		products,
		refresh,
		/** Data of the header read by the form, for the warnings and the quantities */
		setReader(fn) {
			current = fn;
			drawLines(d.products || []);
		},
	};
}
