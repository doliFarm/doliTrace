-- Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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

-- Products of a treatment or fertilization: one row per product, with its adversity (plant protection) and dose.
CREATE TABLE IF NOT EXISTS llx_dolitrace_treatment_product(
	rowid				integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_treatment		integer NOT NULL,
	rang				integer DEFAULT 0,
	fk_agrodrug			integer,							-- plant protection product (llx_dolifarm_agrodrug)
	fk_fertilizer		integer,							-- fertilizer (llx_dolifarm_fertilizer)
	fk_product			integer,							-- product of the stock
	product_label		varchar(255),						-- copy
	reg_number			varchar(64),						-- copy of the authorisation number
	n_content			double(24,8),						-- copy (%)
	p2o5_content		double(24,8),						-- copy (%)
	k2o_content			double(24,8),						-- copy (%)
	cu_content			double(24,8),						-- copy (%)
	efficiency			double(24,8),						-- copy (0-1)
	dry_matter			double(24,8),						-- copy (%)
	density				double(24,8),						-- copy (kg/l), 1 for the livestock effluents without density
	fk_adversity		integer,							-- llx_dolitrace_c_adversity
	adversity_code		varchar(10),						-- copy of the EPPO code
	adversity_label		varchar(255),						-- copy
	dose				double(24,8),
	fk_dose_unit		integer,							-- llx_dolitrace_c_dose_unit
	dose_unit_label		varchar(64),						-- copy
	qty_total			double(24,8),
	qty_unit			varchar(8),
	qty_auto			smallint DEFAULT 1 NOT NULL,		-- 1 computed, 0 entered by the user
	n_applied			double(24,8),						-- kg of nitrogen per hectare
	cu_applied			double(24,8),						-- kg of copper metal per hectare
	batch				varchar(128),
	stock_status		smallint DEFAULT 0 NOT NULL,		-- 0 not needed, 1 moved, 2 to regularize, 3 put back (canceled or replaced)
	stock_error			varchar(255),						-- reason of a movement to regularize
	fk_warehouse		integer,
	fk_stock_movement	integer
) ENGINE=innodb;
