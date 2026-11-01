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

-- Organic production (01/11/2026), for installations created before these columns existed.
-- Dolibarr ignores "column already exists" errors, so the script can run at every activation.

-- Areas: organic status of the plot at the date of the record (code of llx_dolifarm_c_biostatus), copied at the validation
ALTER TABLE llx_dolitrace_treatment_target ADD COLUMN bio_status varchar(16);

-- Products: kg of copper metal per hectare (limit of 28 kg/ha over 7 years, Reg. EU 2021/1165)
ALTER TABLE llx_dolitrace_treatment_product ADD COLUMN cu_applied double(24,8);
