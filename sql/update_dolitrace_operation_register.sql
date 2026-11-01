-- Crop operations as register of the crop events and of the irrigations (sections of the AGEA QDCA),
-- entity for multicompany, identifier of the area in the graphic crop plan (PCG) on the crop plans.
-- Dolibarr ignores "column already exists", so the script can run at every activation.

ALTER TABLE llx_dolitrace_operation ADD COLUMN entity integer DEFAULT 1 NOT NULL;
ALTER TABLE llx_dolitrace_operation ADD COLUMN irrigation_method varchar(16);
ALTER TABLE llx_dolitrace_operation ADD COLUMN water_volume double(24,8);
ALTER TABLE llx_dolitrace_operation ADD COLUMN irrigated_area double(24,8);
ALTER TABLE llx_dolitrace_operation ADD COLUMN fertigation smallint DEFAULT 0;
ALTER TABLE llx_dolitrace_operation ADD COLUMN fk_treatment integer;
ALTER TABLE llx_dolitrace_cropplan ADD COLUMN pcg_ref varchar(64);

-- Indexes on these columns: here and not in the .key.sql, which runs before this script
ALTER TABLE llx_dolitrace_operation ADD INDEX idx_dolitrace_operation_entity (entity);
ALTER TABLE llx_dolitrace_operation ADD INDEX idx_dolitrace_operation_fk_treatment (fk_treatment);
