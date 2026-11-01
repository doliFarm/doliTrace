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

-- History of a record of the register: creation, validation, correction, replacement, cancellation, stock.
CREATE TABLE IF NOT EXISTS llx_dolitrace_treatment_log(
	rowid			integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_treatment	integer NOT NULL,
	version			integer DEFAULT 1 NOT NULL,
	action			varchar(16) NOT NULL,				-- CREATE, VALIDATE, CORRECT, REPLACE, CANCEL, STOCK
	changes			text,								-- JSON
	reason			text,
	content_hash	varchar(64),
	fk_user			integer,
	datec			datetime NOT NULL
) ENGINE=innodb;
