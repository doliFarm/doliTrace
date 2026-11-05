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

-- Closures and reopenings of the inspections, with the reason of the reopening
CREATE TABLE IF NOT EXISTS llx_dolitrace_bioinspection_log(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_inspection integer NOT NULL,
	action varchar(16) NOT NULL,				-- CLOSE, REOPEN
	closure integer,
	reason text,
	fk_user integer,
	datec datetime
) ENGINE=innodb;
