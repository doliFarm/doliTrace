-- ==============================================================================
-- DATI DEMO DOLITRACE (Corretto e Robusto)
-- ==============================================================================

-- 1. Recupero ID (Fix: Tabella Crops corretta è llx_dolifarm_crops)
SET @id_plot = (SELECT rowid FROM llx_dolifarm_plot LIMIT 1);
SET @id_soc = (SELECT fk_soc FROM llx_dolifarm_plot WHERE rowid = @id_plot LIMIT 1);
SET @id_crop = (SELECT rowid FROM llx_dolifarm_crops WHERE ref = 'CROP_WHEAT_DURUM' LIMIT 1);

-- 2. Recupero Prodotti (Assumiamo esistano, altrimenti NULL)
SET @id_prod_rame = (SELECT rowid FROM llx_product WHERE ref = 'AGRO-FUNG-01' LIMIT 1); 
SET @id_prod_urea = (SELECT rowid FROM llx_product WHERE ref = 'AGRO-FERT-01' LIMIT 1); 

SET @ref_plan = 'CP-2026-GRANO-001';

-- 3. Inserimento CropPlan
INSERT IGNORE INTO llx_dolitrace_cropplan 
(ref, label, uuid, fk_soc, fk_plot, fk_crop, season, date_start, date_end, estimated_yield, status, note_public, entity)
VALUES 
(
    @ref_plan, 
    'Grano Duro Senatore Cappelli - Campo Nord', 
    UUID(), 
    IFNULL(@id_soc, 1),  
    IFNULL(@id_plot, 1), 
    IFNULL(@id_crop, 1), 
    2026, 
    '2025-10-15', 
    '2026-06-30', 
    3500.00, 
    1, 
    'Ciclo sperimentale biologico (Dati Mockup)', 
    1
);

SET @id_plan = (SELECT rowid FROM llx_dolitrace_cropplan WHERE ref = @ref_plan LIMIT 1);

-- 4. Pulizia preventiva operazioni per idempotenza
DELETE FROM llx_dolitrace_operation WHERE fk_cropplan = @id_plan;

-- 5. Inserimento Operazioni
-- Fix: Usata tabella corretta llx_dolitrace_operation
INSERT INTO llx_dolitrace_operation (fk_cropplan, date_op, fk_operation_type, label, duration_min, depth_cm, cost_energy, cost_manpower)
VALUES (@id_plan, '2025-10-20 08:00:00', 'OP_SOIL_PLOW', 'Aratura profonda', 180, 35.0, 150.00, 50.00);

INSERT INTO llx_dolitrace_operation (fk_cropplan, date_op, fk_operation_type, label, duration_min, depth_cm, cost_energy, cost_manpower)
VALUES (@id_plan, '2025-11-05 09:00:00', 'OP_SOIL_HARROW_ROT', 'Affinamento terreno', 120, 15.0, 80.00, 40.00);

INSERT INTO llx_dolitrace_operation (fk_cropplan, date_op, fk_operation_type, label, fk_product, qty_used, cost_material, cost_manpower)
VALUES (@id_plan, '2025-11-10 07:30:00', 'OP_SOWING_PRECISION', 'Semina varietà Cappelli', NULL, 220.00, 180.00, 60.00); 

INSERT INTO llx_dolitrace_operation (fk_cropplan, date_op, fk_operation_type, label, fk_product, qty_used, cost_material, cost_manpower)
SELECT @id_plan, '2026-01-20 10:00:00', 'OP_FERT_GRANULAR', 'Copertura Azotata', @id_prod_urea, 150.00, 120.00, 30.00
FROM DUAL WHERE @id_prod_urea IS NOT NULL;

INSERT INTO llx_dolitrace_operation (fk_cropplan, date_op, fk_operation_type, label, fk_product, qty_used, cost_material, cost_energy)
SELECT @id_plan, '2026-04-15 17:00:00', 'OP_TREAT_BIO', 'Trattamento preventivo Rame', @id_prod_rame, 4.5, 45.00, 25.00
FROM DUAL WHERE @id_prod_rame IS NOT NULL;

-- Fix: Corretto nome colonna da 'note' a 'note_public'
INSERT INTO llx_dolitrace_operation (fk_cropplan, date_op, fk_operation_type, label, note_public, cost_manpower)
VALUES (@id_plan, '2026-05-10 09:00:00', 'OP_MONITOR_PEST', 'Ispezione visiva Fusariosi', 'Nessun sintomo rilevato.', 20.00);


-- 6. Inserimento Harvest
SET @ref_harvest = 'HARV-2026-001';

INSERT IGNORE INTO llx_dolitrace_harvest 
(ref, uuid, label, fk_cropplan, date_harvest, fk_product_out, qty_harvested, qty_unit, batch_number, cost_energy, cost_manpower, status)
VALUES 
(
    @ref_harvest, 
    UUID(), 
    'Trebbiatura Lotto A', 
    @id_plan, 
    '2026-06-28', 
    NULL, 
    3850.00, 
    'kg', 
    'BATCH-26-WHEAT-A', 
    400.00, 
    100.00, 
    1
);

-- 7. Update finali
UPDATE llx_dolitrace_cropplan 
SET 
    actual_cost = (SELECT IFNULL(SUM(cost_material + cost_manpower + cost_energy), 0) FROM llx_dolitrace_operation WHERE fk_cropplan = @id_plan) + 500,
    status = 2
WHERE rowid = @id_plan;