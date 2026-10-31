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

ALTER TABLE llx_dolitrace_treatment_product ADD INDEX idx_dolitrace_treatment_product_fk_treatment (fk_treatment);
ALTER TABLE llx_dolitrace_treatment_product ADD INDEX idx_dolitrace_treatment_product_fk_agrodrug (fk_agrodrug);
ALTER TABLE llx_dolitrace_treatment_product ADD INDEX idx_dolitrace_treatment_product_fk_fertilizer (fk_fertilizer);
ALTER TABLE llx_dolitrace_treatment_product ADD CONSTRAINT fk_dolitrace_treatment_product_fk_treatment FOREIGN KEY (fk_treatment) REFERENCES llx_dolitrace_treatment(rowid);
