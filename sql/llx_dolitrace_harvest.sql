
-- ------------------------------------------------------------------------------
-- 5. RACCOLTI (HARVESTS)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_dolitrace_harvest (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    
    -- Identificativi
    ref             VARCHAR(128),
    uuid            VARCHAR(36),                    -- GID: Fondamentale per il Passaporto Digitale
    label           VARCHAR(255),
    entity          INTEGER DEFAULT 1,

    fk_cropplan     INTEGER NOT NULL,
    date_harvest    DATE NOT NULL,
    
    fk_product_out  INTEGER,
    qty_harvested   DOUBLE(24,8) DEFAULT 0,
    qty_unit        VARCHAR(30),
    
    -- Tracciabilità
    batch_number    VARCHAR(128),
    cost_material   DECIMAL(24,8) DEFAULT 0,
    cost_manpower   DECIMAL(24,8) DEFAULT 0,
    cost_energy     DECIMAL(24,8) DEFAULT 0,
    model_pdf       VARCHAR(255),
    last_main_doc   VARCHAR(255),
    note_private    TEXT,
    note_public     TEXT,
    
    status          INTEGER DEFAULT 1,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    datec           DATETIME,
    date_valid      DATETIME,
    fk_user_creat   INTEGER,
    fk_user_modif   INTEGER,
    import_key varchar(14),

    
    UNIQUE INDEX uk_dolitrace_harvest_uuid (uuid),
    CONSTRAINT fk_dolitrace_harv_plan FOREIGN KEY (fk_cropplan) REFERENCES llx_dolitrace_cropplan(rowid)
) ENGINE=InnoDB;