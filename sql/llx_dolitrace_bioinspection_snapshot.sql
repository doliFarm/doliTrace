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

-- Read only copy of the organic registers made when an inspection is closed: one row per closure, register and year
CREATE TABLE IF NOT EXISTS llx_dolitrace_bioinspection_snapshot(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_inspection integer NOT NULL,
	closure integer DEFAULT 1 NOT NULL,			-- number of the closure (a reopened inspection closed again makes a new copy)
	tab varchar(32) NOT NULL,
	year integer NOT NULL,
	header text,								-- JSON: heading of the register
	content mediumtext,							-- JSON: lines of the register
	hash varchar(64),							-- SHA-256 of header and content
	datec datetime
) ENGINE=innodb;
