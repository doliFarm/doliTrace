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

-- Areas of a treatment or fertilization: plots (with their crop plan), buildings or seed lots.
CREATE TABLE IF NOT EXISTS llx_dolitrace_treatment_target(
	rowid			integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_treatment	integer NOT NULL,
	rang			integer DEFAULT 0,
	target_type		smallint DEFAULT 1 NOT NULL,		-- 1 plot, 2 building, 3 seed lot
	fk_plot			integer,
	fk_building		integer,
	fk_cropplan		integer,
	location_ref	varchar(128),						-- copy: IACS parcel or cadastral reference
	zone_zvn		smallint DEFAULT 0,					-- copy: nitrate vulnerable zone
	fk_crop			integer,
	crop_eppo		varchar(10),						-- copy
	crop_label		varchar(255),						-- copy
	bbch			varchar(3),
	treated_qty		double(24,8),
	treated_unit	varchar(8),							-- HA, M2, M3, KG, T
	seed_batch		varchar(128)
) ENGINE=innodb;
