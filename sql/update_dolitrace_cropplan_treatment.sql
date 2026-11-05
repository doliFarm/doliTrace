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
-- along with this program.  If not, see https://www.gnu.org/licenses/.

-- Treatments recorded from the crop plan (05/11/2026): a record on surfaces belongs to one crop plan.
-- Dolibarr ignores "column already exists" and "duplicate key" errors, so the script can run at every activation.

ALTER TABLE llx_dolitrace_treatment ADD COLUMN fk_cropplan integer;
ALTER TABLE llx_dolitrace_treatment ADD INDEX idx_dolitrace_treatment_fk_cropplan (fk_cropplan);

-- Records created before: the crop plan of their areas, when there is only one
UPDATE llx_dolitrace_treatment SET fk_cropplan = (SELECT MIN(g.fk_cropplan) FROM llx_dolitrace_treatment_target as g WHERE g.fk_treatment = llx_dolitrace_treatment.rowid AND g.fk_cropplan > 0)
WHERE fk_cropplan IS NULL AND technical_type = 1
AND (SELECT COUNT(DISTINCT g2.fk_cropplan) FROM llx_dolitrace_treatment_target as g2 WHERE g2.fk_treatment = llx_dolitrace_treatment.rowid AND g2.fk_cropplan > 0) = 1;
