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

-- Records of plant protection: type and reference of the document justifying the need (DM 229771/2022 art. 4 c. 8)
ALTER TABLE llx_dolitrace_treatment ADD COLUMN justification_type varchar(32);
ALTER TABLE llx_dolitrace_treatment ADD COLUMN justification_ref varchar(255);

-- Sowing and planting: organic status of the reproductive material and derogation of the BDSB (Reg. EU 2018/848, annex II part I point 1.8.5; DM 15130/2017)
ALTER TABLE llx_dolitrace_operation ADD COLUMN seed_status varchar(16);
ALTER TABLE llx_dolitrace_operation ADD COLUMN bdsb_ref varchar(64);
ALTER TABLE llx_dolitrace_operation ADD COLUMN bdsb_date date;

-- Harvests: organic status of the harvested product (BIO, INCONV, NONBIO) from the status of the plot at the date, fixed at the validation
ALTER TABLE llx_dolitrace_harvest ADD COLUMN bio_status varchar(16);
