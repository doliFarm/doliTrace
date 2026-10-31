-- Copyright (C) 2026      SuperAdmin
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

ALTER TABLE llx_dolitrace_treatment ADD UNIQUE INDEX uk_dolitrace_treatment_ref (ref, entity);
ALTER TABLE llx_dolitrace_treatment ADD INDEX idx_dolitrace_treatment_fk_soc (fk_soc);
ALTER TABLE llx_dolitrace_treatment ADD INDEX idx_dolitrace_treatment_date_start (date_start);
ALTER TABLE llx_dolitrace_treatment ADD INDEX idx_dolitrace_treatment_status (status);
ALTER TABLE llx_dolitrace_treatment ADD INDEX idx_dolitrace_treatment_fk_previous (fk_previous);
ALTER TABLE llx_dolitrace_treatment ADD INDEX idx_dolitrace_treatment_fk_operator (fk_operator);
ALTER TABLE llx_dolitrace_treatment ADD CONSTRAINT fk_dolitrace_treatment_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid);
ALTER TABLE llx_dolitrace_treatment ADD CONSTRAINT fk_dolitrace_treatment_fk_dossier FOREIGN KEY (fk_dossier) REFERENCES llx_dolifarm_farmdossier(rowid);
