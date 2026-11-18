-- Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
-- along with this program.  If not, see <https://www.gnu.org/licenses/>.

-- Drafts of operations and harvests without ref (before doliTrace 1.5.3): (PROVid), the name of the directory of their
-- documents. Idempotent: only the drafts still without ref.
UPDATE llx_dolitrace_operation SET ref = CONCAT('(PROV', rowid, ')') WHERE (ref IS NULL OR ref = '') AND (status IS NULL OR status = 0);
UPDATE llx_dolitrace_harvest SET ref = CONCAT('(PROV', rowid, ')') WHERE (ref IS NULL OR ref = '') AND (status IS NULL OR status = 0);

-- Index of the documents of records validated before doliTrace 1.5.3: the directory was renamed to the final ref,
-- the index stayed on (PROVid). Idempotent: only the paths still on (PROVid) of a record with its final ref.
UPDATE llx_ecm_files SET filepath = (SELECT CONCAT('dolitrace/dolitraceoperation/', o.ref) FROM llx_dolitrace_operation o WHERE o.rowid = llx_ecm_files.src_object_id) WHERE src_object_type = 'dolitrace_operation' AND filepath = CONCAT('dolitrace/dolitraceoperation/(PROV', src_object_id, ')') AND EXISTS (SELECT 1 FROM llx_dolitrace_operation o WHERE o.rowid = llx_ecm_files.src_object_id AND o.ref NOT LIKE '(PROV%' AND o.ref <> '');
UPDATE llx_ecm_files SET filepath = (SELECT CONCAT('dolitrace/dolitraceharvest/', o.ref) FROM llx_dolitrace_harvest o WHERE o.rowid = llx_ecm_files.src_object_id) WHERE src_object_type = 'dolitrace_harvest' AND filepath = CONCAT('dolitrace/dolitraceharvest/(PROV', src_object_id, ')') AND EXISTS (SELECT 1 FROM llx_dolitrace_harvest o WHERE o.rowid = llx_ecm_files.src_object_id AND o.ref NOT LIKE '(PROV%' AND o.ref <> '');
UPDATE llx_ecm_files SET filepath = (SELECT CONCAT('dolitrace/dolitracetreatment/', o.ref) FROM llx_dolitrace_treatment o WHERE o.rowid = llx_ecm_files.src_object_id) WHERE src_object_type = 'dolitrace_treatment' AND filepath = CONCAT('dolitrace/dolitracetreatment/(PROV', src_object_id, ')') AND EXISTS (SELECT 1 FROM llx_dolitrace_treatment o WHERE o.rowid = llx_ecm_files.src_object_id AND o.ref NOT LIKE '(PROV%' AND o.ref <> '');
UPDATE llx_ecm_files SET filepath = (SELECT CONCAT('dolitrace/dolitracecropplan/', o.ref) FROM llx_dolitrace_cropplan o WHERE o.rowid = llx_ecm_files.src_object_id) WHERE src_object_type = 'dolitrace_cropplan' AND filepath = CONCAT('dolitrace/dolitracecropplan/(PROV', src_object_id, ')') AND EXISTS (SELECT 1 FROM llx_dolitrace_cropplan o WHERE o.rowid = llx_ecm_files.src_object_id AND o.ref NOT LIKE '(PROV%' AND o.ref <> '');
