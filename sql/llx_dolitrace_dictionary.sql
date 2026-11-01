-- Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
-- along with this program.  If not, see https://www.gnu.org/licenses/.

-- Dictionaries of the register of the treatments and fertilizations.
-- Run at every activation: tables created only when missing, rows added only when their code is missing
-- (a row changed or disabled by the user stays as it is).

-- Adversities: EPPO code (Contains EPPO Codes, www.eppo.int), label, category
CREATE TABLE IF NOT EXISTS llx_dolitrace_c_adversity (
	rowid		integer AUTO_INCREMENT PRIMARY KEY,
	code		varchar(10) NOT NULL,
	label		varchar(255) NOT NULL,
	category	varchar(16) NOT NULL DEFAULT 'OTHER',
	active		tinyint DEFAULT 1 NOT NULL,
	position	integer DEFAULT 0,
	UNIQUE INDEX uk_dolitrace_c_adversity_code (code)
) ENGINE=innodb;

INSERT IGNORE INTO llx_dolitrace_c_adversity (code, label, category, active, position) VALUES
('PLASVI', 'Peronospora della vite', 'FUNGUS', 1, 1010),
('UNCINE', 'Oidio della vite', 'FUNGUS', 1, 1020),
('BOTRCI', 'Muffa grigia (botrite)', 'FUNGUS', 1, 1030),
('GUIGBI', 'Marciume nero della vite (black rot)', 'FUNGUS', 1, 1040),
('ELSIAM', 'Antracnosi della vite', 'FUNGUS', 1, 1050),
('PHYTIN', 'Peronospora della patata e del pomodoro', 'FUNGUS', 1, 1060),
('ALTESO', 'Alternariosi della patata e del pomodoro', 'FUNGUS', 1, 1070),
('VENTIN', 'Ticchiolatura del melo', 'FUNGUS', 1, 1080),
('VENTPI', 'Ticchiolatura del pero', 'FUNGUS', 1, 1090),
('PODOLE', 'Oidio del melo', 'FUNGUS', 1, 1100),
('TAPHDE', 'Bolla del pesco', 'FUNGUS', 1, 1110),
('MONILA', 'Monilia delle drupacee (Monilinia laxa)', 'FUNGUS', 1, 1120),
('MONIFG', 'Monilia delle pomacee (Monilinia fructigena)', 'FUNGUS', 1, 1130),
('MONIFC', 'Monilia (Monilinia fructicola)', 'FUNGUS', 1, 1140),
('STIGCA', 'Corineo delle drupacee', 'FUNGUS', 1, 1150),
('SPHRPA', 'Oidio del pesco e della rosa', 'FUNGUS', 1, 1160),
('PLEOAL', 'Maculatura bruna del pero', 'FUNGUS', 1, 1170),
('CYCLOL', 'Occhio di pavone dell''olivo', 'FUNGUS', 1, 1180),
('PSPECU', 'Peronospora delle cucurbitacee', 'FUNGUS', 1, 1190),
('SPHRFU', 'Oidio delle cucurbitacee', 'FUNGUS', 1, 1200),
('BREMLA', 'Peronospora della lattuga', 'FUNGUS', 1, 1210),
('PERODE', 'Peronospora della cipolla', 'FUNGUS', 1, 1220),
('LEVETA', 'Oidio delle solanacee (Leveillula taurica)', 'FUNGUS', 1, 1230),
('ERYSGR', 'Oidio dei cereali', 'FUNGUS', 1, 1240),
('PUCCRT', 'Ruggine bruna del frumento', 'FUNGUS', 1, 1250),
('PUCCST', 'Ruggine gialla del frumento', 'FUNGUS', 1, 1260),
('SEPTTR', 'Septoriosi del frumento', 'FUNGUS', 1, 1270),
('GIBBZE', 'Fusariosi della spiga (Fusarium graminearum)', 'FUNGUS', 1, 1280),
('FUSACU', 'Fusariosi (Fusarium culmorum)', 'FUNGUS', 1, 1290),
('PYRNTE', 'Elmintosporiosi dell''orzo', 'FUNGUS', 1, 1300),
('PYRIOR', 'Brusone del riso', 'FUNGUS', 1, 1310),
('CERCBE', 'Cercosporiosi della barbabietola', 'FUNGUS', 1, 1320),
('SCLESC', 'Sclerotinia', 'FUNGUS', 1, 1330),
('RHIZSO', 'Rizoctonia', 'FUNGUS', 1, 1340),
('PHYTCC', 'Marciume del colletto (Phytophthora cactorum)', 'FUNGUS', 1, 1350),
('COLLAC', 'Antracnosi (Colletotrichum acutatum)', 'FUNGUS', 1, 1360),
('ERWIAM', 'Colpo di fuoco batterico', 'BACTERIUM', 1, 2010),
('PSDMSA', 'Rogna dell''olivo', 'BACTERIUM', 1, 2020),
('PSDMSY', 'Cancro batterico (Pseudomonas syringae)', 'BACTERIUM', 1, 2030),
('XYLEFA', 'Xylella fastidiosa', 'BACTERIUM', 1, 2040),
('PHYP64', 'Flavescenza dorata della vite', 'PHYTOPLASMA', 1, 3010),
('TSWV00', 'Avvizzimento maculato del pomodoro (TSWV)', 'VIRUS', 1, 4010),
('PVY000', 'Virus Y della patata (PVY)', 'VIRUS', 1, 4020),
('POLYBO', 'Tignoletta della vite', 'INSECT', 1, 5010),
('CLYSAM', 'Tignola della vite', 'INSECT', 1, 5020),
('SCAPLI', 'Scafoideo, vettore della flavescenza dorata', 'INSECT', 1, 5030),
('PLANFI', 'Cocciniglia farinosa della vite', 'INSECT', 1, 5040),
('EMPOFL', 'Cicalina verde della vite', 'INSECT', 1, 5050),
('VITEVI', 'Fillossera della vite', 'INSECT', 1, 5060),
('CARPPO', 'Carpocapsa del melo e del pero', 'INSECT', 1, 5070),
('LASPMO', 'Cidia del pesco (tignola orientale)', 'INSECT', 1, 5080),
('ANARLI', 'Anarsia del pesco', 'INSECT', 1, 5090),
('LASPFU', 'Cidia del susino', 'INSECT', 1, 5100),
('DYSAPL', 'Afide grigio del melo', 'INSECT', 1, 5110),
('APHIPO', 'Afide verde del melo', 'INSECT', 1, 5120),
('ERISLA', 'Afide lanigero del melo', 'INSECT', 1, 5130),
('MYZUPE', 'Afide verde del pesco', 'INSECT', 1, 5140),
('HYALPR', 'Afide farinoso del susino', 'INSECT', 1, 5150),
('MYZUCE', 'Afide nero del ciliegio', 'INSECT', 1, 5160),
('PSYLPI', 'Psilla del pero', 'INSECT', 1, 5170),
('QUADPE', 'Cocciniglia di San José', 'INSECT', 1, 5180),
('HALYHA', 'Cimice asiatica', 'INSECT', 1, 5190),
('DROSSU', 'Moscerino dei piccoli frutti (Drosophila suzukii)', 'INSECT', 1, 5200),
('RHAGCE', 'Mosca delle ciliegie', 'INSECT', 1, 5210),
('CERTCA', 'Mosca mediterranea della frutta', 'INSECT', 1, 5220),
('DACUOL', 'Mosca dell''olivo', 'INSECT', 1, 5230),
('PRAYOL', 'Tignola dell''olivo', 'INSECT', 1, 5240),
('PSECCI', 'Cotonello degli agrumi', 'INSECT', 1, 5250),
('PHYNCI', 'Minatrice serpentina degli agrumi', 'INSECT', 1, 5260),
('POPIJA', 'Popillia japonica', 'INSECT', 1, 5270),
('GNORAB', 'Tignola del pomodoro (Tuta absoluta)', 'INSECT', 1, 5280),
('LPTNDE', 'Dorifora della patata', 'INSECT', 1, 5290),
('BEMITA', 'Mosca bianca del tabacco (Bemisia tabaci)', 'INSECT', 1, 5300),
('TRIAVA', 'Mosca bianca delle serre', 'INSECT', 1, 5310),
('FRANOC', 'Tripide occidentale dei fiori', 'INSECT', 1, 5320),
('THRITB', 'Tripide della cipolla', 'INSECT', 1, 5330),
('APHIGO', 'Afide del cotone e delle cucurbitacee', 'INSECT', 1, 5340),
('APHIFA', 'Afide nero della fava', 'INSECT', 1, 5350),
('BRVCBR', 'Afide ceroso del cavolo', 'INSECT', 1, 5360),
('PIERBR', 'Cavolaia', 'INSECT', 1, 5370),
('BARABR', 'Nottua del cavolo', 'INSECT', 1, 5380),
('HYLERA', 'Mosca del cavolo', 'INSECT', 1, 5390),
('HELIAR', 'Nottua del pomodoro (Helicoverpa armigera)', 'INSECT', 1, 5400),
('SPODLI', 'Spodoptera littoralis', 'INSECT', 1, 5410),
('PYTOGA', 'Nottua gamma', 'INSECT', 1, 5420),
('AGROYP', 'Nottua dei seminati (Agrotis ipsilon)', 'INSECT', 1, 5430),
('LIRITR', 'Minatrice fogliare (Liriomyza trifolii)', 'INSECT', 1, 5440),
('PYRUNU', 'Piralide del mais', 'INSECT', 1, 5450),
('DIABVI', 'Diabrotica del mais', 'INSECT', 1, 5460),
('AGRISP', 'Elateridi (ferretti)', 'INSECT', 1, 5470),
('MACSAV', 'Afide della spiga del frumento', 'INSECT', 1, 5480),
('RHOPPA', 'Afide dell''avena (Rhopalosiphum padi)', 'INSECT', 1, 5490),
('LEMAME', 'Lema dei cereali', 'INSECT', 1, 5500),
('TETRUR', 'Ragnetto rosso comune (Tetranychus urticae)', 'MITE', 1, 6010),
('METTUL', 'Ragnetto rosso dei fruttiferi (Panonychus ulmi)', 'MITE', 1, 6020),
('VASASD', 'Eriofide rugginoso del melo', 'MITE', 1, 6030),
('ERPHVI', 'Erinosi della vite', 'MITE', 1, 6040),
('MELGIN', 'Nematode galligeno (Meloidogyne incognita)', 'NEMATODE', 1, 7010),
('HETDRO', 'Nematode a cisti della patata', 'NEMATODE', 1, 7020),
('HETDSC', 'Nematode a cisti della barbabietola', 'NEMATODE', 1, 7030),
('DEROSP', 'Limacce', 'MOLLUSC', 1, 8010),
('HELXSP', 'Chiocciole', 'MOLLUSC', 1, 8020),
('ECHCG', 'Giavone comune', 'WEED', 1, 9010),
('AMARE', 'Amaranto comune', 'WEED', 1, 9020),
('CHEAL', 'Farinello comune', 'WEED', 1, 9030),
('SORHA', 'Sorghetta', 'WEED', 1, 9040),
('CIRAR', 'Cardo campestre (stoppione)', 'WEED', 1, 9050),
('CONAR', 'Vilucchio comune', 'WEED', 1, 9060),
('CAGSE', 'Vilucchio bianco', 'WEED', 1, 9070),
('SOLNI', 'Erba morella', 'WEED', 1, 9080),
('ABUTH', 'Cencio molle', 'WEED', 1, 9090),
('AVEFA', 'Avena selvatica', 'WEED', 1, 9100),
('LOLMU', 'Loglio maggiore (loiessa)', 'WEED', 1, 9110),
('ALOMY', 'Coda di volpe', 'WEED', 1, 9120),
('PHAMI', 'Scagliola minore', 'WEED', 1, 9130),
('PAPRH', 'Papavero (rosolaccio)', 'WEED', 1, 9140),
('SINAR', 'Senape selvatica', 'WEED', 1, 9150),
('RAPRA', 'Ravanello selvatico', 'WEED', 1, 9160),
('GALAP', 'Attaccamani', 'WEED', 1, 9170),
('STEME', 'Centocchio comune', 'WEED', 1, 9180),
('VERPE', 'Veronica comune', 'WEED', 1, 9190),
('MATCH', 'Camomilla comune', 'WEED', 1, 9200),
('POLCO', 'Poligono convolvolo', 'WEED', 1, 9210),
('POLAV', 'Correggiola', 'WEED', 1, 9220),
('POROL', 'Porcellana comune', 'WEED', 1, 9230),
('DIGSA', 'Sanguinella', 'WEED', 1, 9240),
('SETVI', 'Panicastrella (setaria verde)', 'WEED', 1, 9250),
('CYPRO', 'Zigolo infestante', 'WEED', 1, 9260),
('XANST', 'Nappola', 'WEED', 1, 9270),
('AMBEL', 'Ambrosia', 'WEED', 1, 9280),
('DATST', 'Stramonio', 'WEED', 1, 9290),
('ERICA', 'Saeppola canadese', 'WEED', 1, 9300),
('CAPBP', 'Borsa del pastore', 'WEED', 1, 9310),
('CYNDA', 'Gramigna', 'WEED', 1, 9320),
('EQUAR', 'Equiseto dei campi', 'WEED', 1, 9330),
('RUMOB', 'Romice comune', 'WEED', 1, 9340),
('SENVU', 'Senecione comune', 'WEED', 1, 9350),
('FUMOF', 'Fumaria', 'WEED', 1, 9360),
('BIDTR', 'Forbicina comune', 'WEED', 1, 9370),
('HEOEU', 'Eliotropio', 'WEED', 1, 9380);

-- Units of dose: the total quantity is dose x treated quantity (in the unit "basis") x factor, in the unit qty_unit.
-- kind: 0 both, 1 plant protection, 2 fertilization; technical_type: 0 any, 1 surfaces, 2 closed spaces, 3 seeds
CREATE TABLE IF NOT EXISTS llx_dolitrace_c_dose_unit (
	rowid			integer AUTO_INCREMENT PRIMARY KEY,
	code			varchar(16) NOT NULL,
	label			varchar(64) NOT NULL,
	kind			smallint DEFAULT 0 NOT NULL,
	technical_type	smallint DEFAULT 0 NOT NULL,
	basis			varchar(8) NOT NULL,
	qty_unit		varchar(8) NOT NULL,
	factor			double(24,8) DEFAULT 1 NOT NULL,
	active			tinyint DEFAULT 1 NOT NULL,
	position		integer DEFAULT 0,
	UNIQUE INDEX uk_dolitrace_c_dose_unit_code (code)
) ENGINE=innodb;

INSERT IGNORE INTO llx_dolitrace_c_dose_unit (code, label, kind, technical_type, basis, qty_unit, factor, active, position) VALUES
('KG_HA', 'kg/ha', 0, 1, 'HA', 'kg', 1, 1, 10),
('L_HA', 'l/ha', 0, 1, 'HA', 'l', 1, 1, 20),
('G_HA', 'g/ha', 1, 1, 'HA', 'kg', 0.001, 1, 30),
('ML_HA', 'ml/ha', 1, 1, 'HA', 'l', 0.001, 1, 40),
('KG_HL', 'kg/hl', 1, 1, 'HL', 'kg', 1, 1, 50),
('L_HL', 'l/hl', 1, 1, 'HL', 'l', 1, 1, 60),
('G_HL', 'g/hl', 1, 1, 'HL', 'kg', 0.001, 1, 70),
('ML_HL', 'ml/hl', 1, 1, 'HL', 'l', 0.001, 1, 80),
('Q_HA', 'q/ha', 2, 1, 'HA', 't', 0.1, 1, 90),
('T_HA', 't/ha', 2, 1, 'HA', 't', 1, 1, 100),
('M3_HA', 'm³/ha', 2, 1, 'HA', 'm3', 1, 1, 110),
('G_M2', 'g/m²', 0, 1, 'M2', 'kg', 0.001, 1, 120),
('ML_M2', 'ml/m²', 0, 1, 'M2', 'l', 0.001, 1, 130),
('G_M3', 'g/m³', 1, 2, 'M3', 'kg', 0.001, 1, 140),
('ML_M3', 'ml/m³', 1, 2, 'M3', 'l', 0.001, 1, 150),
('G_T', 'g/t', 1, 2, 'T', 'kg', 0.001, 1, 160),
('ML_T', 'ml/t', 1, 2, 'T', 'l', 0.001, 1, 170),
('G_KGSEED', 'g/kg di seme', 1, 3, 'KG', 'kg', 0.001, 1, 180),
('ML_KGSEED', 'ml/kg di seme', 1, 3, 'KG', 'l', 0.001, 1, 190),
('G_QSEED', 'g/q di seme', 1, 3, 'KG', 'kg', 0.00001, 1, 200),
('ML_QSEED', 'ml/q di seme', 1, 3, 'KG', 'l', 0.00001, 1, 210);

-- Irrigation methods (register of the irrigations, section "registro irrigazioni" of the AGEA QDCA)
CREATE TABLE IF NOT EXISTS llx_dolitrace_c_irrigation_method (
	rowid			integer AUTO_INCREMENT PRIMARY KEY,
	code			varchar(16) NOT NULL,
	label			varchar(64) NOT NULL,
	agea_value		varchar(128),
	active			tinyint DEFAULT 1 NOT NULL,
	position		integer DEFAULT 0,
	UNIQUE INDEX uk_dolitrace_c_irrigation_method_code (code)
) ENGINE=innodb;

INSERT IGNORE INTO llx_dolitrace_c_irrigation_method (code, label, active, position) VALUES
('SPRINKLER', 'Aspersione', 1, 10),
('DRIP', 'Goccia', 1, 20),
('MICRO', 'Microirrigazione / Spruzzo', 1, 30),
('SUBSURFACE', 'Subirrigazione', 1, 40),
('FURROW', 'Scorrimento / Infiltrazione laterale', 1, 50),
('FLOOD', 'Sommersione', 1, 60),
('OTHER', 'Altro', 1, 900);

-- Methods of application or distribution of the records (AGEA field notebook: sheet 3 plant protection, 6 organic and 7 mineral fertilization).
-- kind: 1 plant protection, 2 fertilization; fert_group: ORG organic, MIN mineral; agea_value: item of the AGEA list (no official codes yet)
CREATE TABLE IF NOT EXISTS llx_dolitrace_c_application_method (
	rowid			integer AUTO_INCREMENT PRIMARY KEY,
	code			varchar(32) NOT NULL,
	label			varchar(128) NOT NULL,
	kind			smallint DEFAULT 1 NOT NULL,
	fert_group		varchar(8),
	agea_value		varchar(128),
	active			tinyint DEFAULT 1 NOT NULL,
	position		integer DEFAULT 0,
	UNIQUE INDEX uk_dolitrace_c_application_method_code (code)
) ENGINE=innodb;

INSERT IGNORE INTO llx_dolitrace_c_application_method (code, label, kind, fert_group, agea_value, active, position) VALUES
('PPP_ATOMIZER', 'Atomizzatore', 1, NULL, 'Atomizzatori', 1, 10),
('PPP_SPRAYER', 'Irroratrice', 1, NULL, 'Irroratrici', 1, 20),
('PPP_DUSTER', 'Impolveratrice', 1, NULL, 'Impolveratrici', 1, 30),
('PPP_MICROGRAN', 'Microgranulatore', 1, NULL, 'Microgranulatore', 1, 40),
('PPP_GREENHOUSE', 'Impianto fisso per serra', 1, NULL, 'Impianti fissi per serra', 1, 50),
('PPP_HOSE', 'Manichetta', 1, NULL, 'Manichetta', 1, 60),
('ORG_SPLASH_PLATE', 'Piatto deviatore', 2, 'ORG', 'piatto deviatore', 1, 110),
('ORG_BOOM', 'Barra di distribuzione', 2, 'ORG', 'barra di distribuzione', 1, 120),
('ORG_REEL', 'Rotolone a bassa pressione', 2, 'ORG', 'rotolone a bassa pressione', 1, 130),
('ORG_BAND', 'Rasoterra in banda', 2, 'ORG', 'rasoterra in banda', 1, 140),
('ORG_BAND_SHOE', 'Rasoterra in banda con deflettore', 2, 'ORG', 'rasoterra in banda con deflettore', 1, 150),
('ORG_DISC_OPEN', 'Sotto-superficiale a dischi (solco aperto)', 2, 'ORG', 'sotto-superficiale a dischi (solco aperto)', 1, 160),
('ORG_RIPPER_UMB', 'Sotto-superficiale ripper e ombelicale', 2, 'ORG', 'sotto-superficiale ripper e ombelicale', 1, 170),
('ORG_RIPPER_CLOSED', 'Sotto-superficiale ripper (solco chiuso)', 2, 'ORG', 'sotto-superficiale ripper (solco chiuso)', 1, 180),
('ORG_INJ_SELFPROP', 'Sotto-superficiale assolcatore (solco chiuso), operatrice semovente', 2, 'ORG', 'sotto-superficiale assolcatore (solco chiuso) operatrice semovente', 1, 190),
('ORG_INJ_TRAILED', 'Sotto-superficiale assolcatore (solco chiuso), botte trainata', 2, 'ORG', 'sotto-superficiale assolcatore (solco chiuso) botte trainata', 1, 200),
('ORG_DEEP_INJ', 'Iniezione profonda', 2, 'ORG', 'iniezione profonda', 1, 210),
('ORG_FERTIG_PIVOT', 'Fertirrigazione superficiale (ranger o pivot)', 2, 'ORG', 'Fertirrigazione superficiale (ranger o pivot)', 1, 220),
('ORG_FERTIG_HOSE', 'Fertirrigazione superficiale (manichette)', 2, 'ORG', 'Fertirrigazione superficiale (con manichette)', 1, 230),
('ORG_FERTIG_SUB', 'Fertirrigazione sotto-superficiale (subirrigazione)', 2, 'ORG', 'Fertirrigazione sotto-superficiale (subirrigazione)', 1, 240),
('MIN_HOSE', 'In manichetta', 2, 'MIN', 'In manichetta', 1, 310),
('MIN_RAIN', 'A pioggia', 2, 'MIN', 'a pioggia', 1, 320),
('MIN_SOIL', 'Al terreno', 2, 'MIN', 'al terreno', 1, 330),
('MIN_FERTIG', 'Fertirrigazione', 2, 'MIN', 'fertirrigazione', 1, 340),
('MIN_FOLIAR', 'Fogliare', 2, 'MIN', 'fogliare', 1, 350);

-- Seeds counted by number (AGEA field notebook, sheet 5): treated quantity in thousands of seeds
INSERT IGNORE INTO llx_dolitrace_c_dose_unit (code, label, kind, technical_type, basis, qty_unit, factor, active, position) VALUES
('G_KSEED', 'g/1000 semi', 1, 3, 'KSEED', 'kg', 0.001, 1, 220),
('ML_KSEED', 'ml/1000 semi', 1, 3, 'KSEED', 'l', 0.001, 1, 230);

-- Organic production: type of the document justifying the need of a plant protection product
-- (Reg. EU 2018/848, annex II part I point 1.10.2; DM 229771/2022 art. 4 c. 8)
CREATE TABLE IF NOT EXISTS llx_dolitrace_c_justification (
	rowid			integer AUTO_INCREMENT PRIMARY KEY,
	code			varchar(32) NOT NULL,
	label			varchar(128) NOT NULL,
	active			tinyint DEFAULT 1 NOT NULL,
	position		integer DEFAULT 0,
	UNIQUE INDEX uk_dolitrace_c_justification_code (code)
) ENGINE=innodb;

INSERT IGNORE INTO llx_dolitrace_c_justification (code, label, active, position) VALUES
('AGRONOMIC_REPORT', 'Relazione tecnico agronomica', 1, 10),
('PHYTO_REPORT', 'Relazione fitopatologica', 1, 20),
('BULLETIN', 'Bollettino fitosanitario', 1, 30),
('FORECAST_MODEL', 'Modello previsionale', 1, 40),
('TRAP_CATCHES', 'Catture in trappola', 1, 50),
('OTHER', 'Altro', 1, 900);
