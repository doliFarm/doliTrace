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
-- along with this program. If not, see https://www.gnu.org/licenses/.

-- Inspections of the organic control body. A closed inspection freezes the registers of the farm up to its date.
CREATE TABLE IF NOT EXISTS llx_dolitrace_bioinspection(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	ref varchar(128) NOT NULL,
	fk_soc integer NOT NULL,
	date_inspection date NOT NULL,
	fk_soc_body integer,						-- control body (third party)
	inspector varchar(255),
	inspection_type varchar(16),				-- ANNUAL, ADDITIONAL, UNANNOUNCED
	samples smallint DEFAULT 0 NOT NULL,
	outcome varchar(16),						-- COMPLIANT, NONCONFORM, PENDING
	report_ref varchar(128),
	note_public text,
	note_private text,
	status smallint DEFAULT 0 NOT NULL,			-- 0 draft, 1 closed (registers frozen)
	date_creation datetime,
	date_close datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer,
	fk_user_close integer,
	last_main_doc varchar(255),
	model_pdf varchar(255),
	import_key varchar(14)
) ENGINE=innodb;
