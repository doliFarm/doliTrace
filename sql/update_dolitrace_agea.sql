-- Data of the AGEA field notebook (circ. AGEA 21371/2024, Allegato 3).
-- Copy of the sprayer check (certificate, date of the last check) and method of application.
-- For installations created before these columns existed. Dolibarr ignores
-- "column already exists" errors, so the script can run at every activation.

ALTER TABLE llx_dolitrace_treatment ADD COLUMN machine_check_date date;
ALTER TABLE llx_dolitrace_treatment ADD COLUMN machine_cert_ref varchar(64);
ALTER TABLE llx_dolitrace_treatment ADD COLUMN application_method varchar(32);

-- Irrigation methods: item of the AGEA list (sheet 8). Only where not set, so the changes made in the dictionary are kept.
ALTER TABLE llx_dolitrace_c_irrigation_method ADD COLUMN agea_value varchar(128);
UPDATE llx_dolitrace_c_irrigation_method SET agea_value = 'a pioggia' WHERE code = 'SPRINKLER' AND agea_value IS NULL;
UPDATE llx_dolitrace_c_irrigation_method SET agea_value = 'in manichetta' WHERE code = 'DRIP' AND agea_value IS NULL;
UPDATE llx_dolitrace_c_irrigation_method SET agea_value = 'microjet' WHERE code = 'MICRO' AND agea_value IS NULL;
UPDATE llx_dolitrace_c_irrigation_method SET agea_value = 'per scorrimento' WHERE code IN ('FURROW', 'FLOOD') AND agea_value IS NULL;
