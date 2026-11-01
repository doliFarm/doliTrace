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

-- Register of the treatments and fertilizations (Reg. EU 2023/564): one record per treatment or fertilization.
-- The "copy" columns keep the data of the registries at the validation: the register does not change afterwards.
CREATE TABLE IF NOT EXISTS llx_dolitrace_treatment(
	rowid					integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	ref						varchar(128) NOT NULL,
	entity					integer DEFAULT 1 NOT NULL,
	kind					smallint DEFAULT 1 NOT NULL,		-- 1 plant protection, 2 fertilization
	technical_type			smallint DEFAULT 1 NOT NULL,		-- 1 surfaces, 2 closed spaces, 3 seeds
	fk_soc					integer NOT NULL,					-- farm
	fk_dossier				integer,
	date_start				datetime NOT NULL,
	date_end				datetime,
	reason					varchar(255),
	fk_operator				integer,
	operator_name			varchar(255),						-- copy
	operator_license		varchar(64),						-- copy
	operator_license_end	date,								-- copy
	fk_soc_contractor		integer,
	contractor_mode			smallint,							-- 1 annotation and countersignature, 2 form of the contractor attached
	contractor_signed		smallint DEFAULT 0,
	date_contractor_signed	datetime,
	delegation_ref			varchar(128),
	fk_machine				integer,
	machine_label			varchar(255),						-- copy
	machine_check_end		date,								-- copy
	machine_check_date		date,								-- copy
	machine_cert_ref		varchar(64),						-- copy
	application_method		varchar(32),
	justification_type		varchar(32),						-- organic: llx_dolitrace_c_justification
	justification_ref		varchar(255),						-- organic: reference of the document of the need
	water_volume			double(24,8),						-- l/ha
	weather					varchar(255),
	version					integer DEFAULT 1 NOT NULL,
	fk_previous				integer,
	late					smallint DEFAULT 0,
	content_hash			varchar(64),
	cancel_reason			text,
	note_public				text,
	note_private			text,
	date_creation			datetime NOT NULL,
	date_valid				datetime,
	tms						timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat			integer NOT NULL,
	fk_user_modif			integer,
	fk_user_valid			integer,
	last_main_doc			varchar(255),
	import_key				varchar(14),
	model_pdf				varchar(255),
	status					smallint DEFAULT 0 NOT NULL			-- 0 draft, 1 validated, 8 replaced, 9 canceled
) ENGINE=innodb;
