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
-- along with this program.  If not, see <https://www.gnu.org/licenses/>.

-- Budget of a production plan: planned operations with their costs per hectare
CREATE TABLE IF NOT EXISTS llx_dolitrace_cropplan_budget(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_cropplan integer NOT NULL,
	rang integer DEFAULT 0,
	fk_operation_type varchar(32),
	label varchar(255),
	passes double(24,8) DEFAULT 1,
	area_ha double(24,8),
	cost_manpower_ha double(24,8),
	cost_machine_ha double(24,8),
	cost_service_ha double(24,8),
	cost_material_ha double(24,8),
	date_creation datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer
) ENGINE=innodb;
