-- Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU Affero General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU Affero General Public License for more details.
--
-- You should have received a copy of the GNU Affero General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

-- Units of the harvests: Dolibarr dictionary "Units" (llx_c_units), completed with the units of agriculture.
-- Run at every activation: inserts only the missing codes, the administrator can change or disable them.
-- scale: power of 10 against the base unit of the type (kg for the weights, m3 for the volumes)
INSERT INTO llx_c_units (code, sortorder, scale, label, short_label, unit_type, active)
SELECT 'Q', 0, 2, 'Quintale', 'q', 'weight', 1 FROM DUAL WHERE NOT EXISTS (SELECT rowid FROM llx_c_units WHERE code = 'Q');
INSERT INTO llx_c_units (code, sortorder, scale, label, short_label, unit_type, active)
SELECT 'HL', 0, -1, 'Ettolitro', 'hl', 'volume', 1 FROM DUAL WHERE NOT EXISTS (SELECT rowid FROM llx_c_units WHERE code = 'HL');
INSERT INTO llx_c_units (code, sortorder, scale, label, short_label, unit_type, active)
SELECT 'CAS', 0, 0, 'Cassetta', 'cass.', 'qty', 1 FROM DUAL WHERE NOT EXISTS (SELECT rowid FROM llx_c_units WHERE code = 'CAS');
INSERT INTO llx_c_units (code, sortorder, scale, label, short_label, unit_type, active)
SELECT 'BIN', 0, 0, 'Bin (cassone)', 'bin', 'qty', 1 FROM DUAL WHERE NOT EXISTS (SELECT rowid FROM llx_c_units WHERE code = 'BIN');

-- Units of the harvests written by hand before the list: the short label of the unit
UPDATE llx_dolitrace_harvest SET qty_unit = 'kg' WHERE LOWER(TRIM(qty_unit)) IN ('kg', 'kgs', 'kg.', 'kilo', 'chili', 'chilogrammi', 'kilogrammi', 'chilogrammo') AND qty_unit <> 'kg';
UPDATE llx_dolitrace_harvest SET qty_unit = 'q' WHERE LOWER(TRIM(qty_unit)) IN ('q', 'q.', 'ql', 'qli', 'quintale', 'quintali') AND qty_unit <> 'q';
UPDATE llx_dolitrace_harvest SET qty_unit = 'T' WHERE LOWER(TRIM(qty_unit)) IN ('t', 't.', 'ton', 'tonnellata', 'tonnellate') AND qty_unit <> 'T';
UPDATE llx_dolitrace_harvest SET qty_unit = 'L' WHERE LOWER(TRIM(qty_unit)) IN ('l', 'l.', 'lt', 'lt.', 'litro', 'litri') AND qty_unit <> 'L';
UPDATE llx_dolitrace_harvest SET qty_unit = 'hl' WHERE LOWER(TRIM(qty_unit)) IN ('hl', 'hl.', 'ettolitro', 'ettolitri') AND qty_unit <> 'hl';
UPDATE llx_dolitrace_harvest SET qty_unit = 'cass.' WHERE LOWER(TRIM(qty_unit)) IN ('cass', 'cassetta', 'cassette') AND qty_unit <> 'cass.';
UPDATE llx_dolitrace_harvest SET qty_unit = 'p' WHERE LOWER(TRIM(qty_unit)) IN ('pz', 'pz.', 'pezzi', 'pezzo', 'n', 'n.', 'nr') AND qty_unit <> 'p';
