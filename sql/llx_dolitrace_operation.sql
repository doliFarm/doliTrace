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


CREATE TABLE IF NOT EXISTS llx_dolitrace_operation(
	-- BEGIN MODULEBUILDER FIELDS
	rowid int AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128),
	entity integer DEFAULT 1 NOT NULL,
	fk_cropplan int NOT NULL,
	date_op datetime NOT NULL,
	date_op_end datetime, 
	fk_operation_type varchar(32) NOT NULL, 
	label varchar(255), 
	weather_cond varchar(128), 
	fk_pheno_stage varchar(64), 
	fk_adversity varchar(64), 
	fk_machine int, 
	duration_min int, 
	depth_cm decimal(5,2), 
	fk_product integer, 
	batch_input varchar(128), 
	qty_used decimal(24,8), 
	water_vol_ha decimal(24,8),
	irrigation_method varchar(16),						-- llx_dolitrace_c_irrigation_method.code
	water_volume double(24,8),							-- m3 of water of the irrigation
	irrigated_area double(24,8),						-- ha
	fertigation smallint DEFAULT 0,
	fk_treatment integer,								-- fertilization record of the fertigation
	cost_material decimal(24,8), 
	cost_manpower decimal(24,8), 
	cost_energy decimal(24,8), 
	status int, 
	fk_user_operator integer, 
	note_private text, 
	note_public text, 
	model_pdf varchar(255), 
	last_main_doc varchar(255), 
	datec datetime, 
	date_valid datetime, 
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	fk_user_creat integer, 
	fk_user_modif integer, 
	import_key varchar(14)
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
