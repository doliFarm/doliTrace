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
-- along with this program.  If not, see <https://www.gnu.org/licenses/>.


-- Field app API 2 (doliTrace 1.5.9): data sent by the phone, to change a draft from the app, and identifier of the
-- last change (a change sent twice is applied once). The errors "duplicate column" of a second run are ignored.
ALTER TABLE llx_dolitrace_pwa_receipt ADD COLUMN data_json text;
ALTER TABLE llx_dolitrace_pwa_receipt ADD COLUMN revision varchar(64);
