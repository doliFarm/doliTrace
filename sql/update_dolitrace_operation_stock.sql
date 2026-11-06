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

-- Seed of the sowing and planting operations taken out of the stock (06/11/2026): state of the movement, as for the
-- products of the treatments. Dolibarr ignores "column already exists", so the script can run at every activation.

ALTER TABLE llx_dolitrace_operation ADD COLUMN stock_status smallint DEFAULT 0 NOT NULL;
ALTER TABLE llx_dolitrace_operation ADD COLUMN stock_error varchar(255);
ALTER TABLE llx_dolitrace_operation ADD COLUMN fk_warehouse integer;
ALTER TABLE llx_dolitrace_operation ADD COLUMN fk_stock_movement integer;
