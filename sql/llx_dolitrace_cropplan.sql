
-- ------------------------------------------------------------------------------
-- 3. PIANO COLTURALE (CROP PLAN)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_dolitrace_cropplan (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    
    -- Identificativi
    ref             VARCHAR(128) NOT NULL,
    uuid            VARCHAR(36),                    -- GID: Identificativo Univoco Globale per QR/API
    label           VARCHAR(255),
    
    -- Relazioni
    fk_soc          INTEGER NOT NULL,
    fk_plot         INTEGER NOT NULL,
    fk_crop         INTEGER NOT NULL,
    fk_output_product INTEGER DEFAULT NULL,
    fk_project      INTEGER,
    fk_dossier      INTEGER DEFAULT NULL,
    batch_output VARCHAR(128) DEFAULT NULL,
    -- Dati
    season          INTEGER NOT NULL,
    date_start      DATE,
    date_end        DATE,
    estimated_yield DECIMAL(24,8),
    
    -- Costi
    cost_material   DECIMAL(24,8) DEFAULT 0,
    cost_manpower   DECIMAL(24,8) DEFAULT 0,
    cost_energy     DECIMAL(24,8) DEFAULT 0,
    actual_cost     DECIMAL(24,8) DEFAULT 0,
    
    status          INTEGER DEFAULT 1,
    note_public     TEXT,
    entity          INTEGER DEFAULT 1,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    datec           DATETIME,
    date_valid      DATETIME,
    fk_user_creat   INTEGER,
    fk_user_modif   INTEGER,
    import_key      VARCHAR(14),
    model_pdf       VARCHAR(255),
    last_main_doc   VARCHAR(255),

    UNIQUE INDEX uk_dolitrace_cropplan_ref (ref, entity),
    UNIQUE INDEX uk_dolitrace_cropplan_uuid (uuid), -- UUID deve essere univoco
    INDEX idx_dolitrace_cropplan_plot (fk_plot),
    
    CONSTRAINT fk_dolitrace_plan_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid),
    CONSTRAINT fk_dolitrace_plan_plot FOREIGN KEY (fk_plot) REFERENCES llx_dolifarm_plot(rowid), 
    CONSTRAINT fk_dolitrace_plan_crop FOREIGN KEY (fk_crop) REFERENCES llx_dolifarm_crops(rowid)
) ENGINE=InnoDB;
