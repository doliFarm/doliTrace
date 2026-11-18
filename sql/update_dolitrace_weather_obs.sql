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

-- Weather observed in the field with the operations and the treatments (doliTrace 1.5.9): time of the reading,
-- temperature, wind, relative humidity. The errors "duplicate column" of a second run are ignored.
ALTER TABLE llx_dolitrace_operation ADD COLUMN weather_obs_date datetime;
ALTER TABLE llx_dolitrace_operation ADD COLUMN weather_obs_temp double(24,8);
ALTER TABLE llx_dolitrace_operation ADD COLUMN weather_obs_wind double(24,8);
ALTER TABLE llx_dolitrace_operation ADD COLUMN weather_obs_rh double(24,8);
ALTER TABLE llx_dolitrace_treatment ADD COLUMN weather_obs_date datetime;
ALTER TABLE llx_dolitrace_treatment ADD COLUMN weather_obs_temp double(24,8);
ALTER TABLE llx_dolitrace_treatment ADD COLUMN weather_obs_wind double(24,8);
ALTER TABLE llx_dolitrace_treatment ADD COLUMN weather_obs_rh double(24,8);
