-- Copyright (C) 2026		SuperAdmin
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


-- BEGIN MODULEBUILDER INDEXES
-- END MODULEBUILDER INDEXES

--ALTER TABLE llx_dolitrace_cropplan ADD UNIQUE INDEX uk_dolitrace_cropplan_fieldxy(fieldx, fieldy);

--ALTER TABLE llx_dolitrace_cropplan ADD CONSTRAINT llx_dolitrace_cropplan_fk_field FOREIGN KEY (fk_field) REFERENCES llx_dolitrace_myotherobject(rowid);
ALTER TABLE llx_dolitrace_cropplan ADD INDEX idx_dolitrace_cropplan_dossier (fk_dossier);
ALTER TABLE llx_dolitrace_cropplan ADD CONSTRAINT fk_dolitrace_plan_dossier FOREIGN KEY (fk_dossier) REFERENCES llx_dolifarm_farmdossier(rowid);
ALTER TABLE llx_dolitrace_cropplan ADD INDEX idx_dolitrace_cropplan_fk_output_product (fk_output_product);