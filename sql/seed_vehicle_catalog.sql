-- MecaBuddy — extension catalogue véhicules (idempotent)
UPDATE vehicle_brands SET category = 'car' WHERE category IS NULL OR category = '';

-- Marques AUTO (extension)
INSERT OR IGNORE INTO vehicle_brands (name, country, category) VALUES
-- Françaises
('Alpine','France','car'),
('Bugatti','France','car'),
-- Allemandes
('Porsche','Allemagne','car'),
('Mini','Royaume-Uni','car'),
-- Italiennes
('Alfa Romeo','Italie','car'),
('Lancia','Italie','car'),
('Lamborghini','Italie','car'),
('Ferrari','Italie','car'),
('Maserati','Italie','car'),
-- Américaines
('Chevrolet','États-Unis','car'),
('Dodge','États-Unis','car'),
('Jeep','États-Unis','car'),
('Tesla','États-Unis','car'),
('Cadillac','États-Unis','car'),
('Chrysler','États-Unis','car'),
-- Asiatiques
('Lexus','Japon','car'),
('Suzuki','Japon','car'),
('Mitsubishi','Japon','car'),
('Subaru','Japon','car'),
('Isuzu','Japon','car'),
('Infiniti','Japon','car'),
('Acura','Japon','car'),
('Genesis','Corée du Sud','car'),
('SsangYong','Corée du Sud','car'),
-- Chinoises / récentes
('BYD','Chine','car'),
('MG','Chine','car'),
('Polestar','Suède','car'),
('Cupra','Espagne','car'),
('Lynk & Co','Chine','car'),
-- Utilitaires / autres
('Land Rover','Royaume-Uni','car'),
('Jaguar','Royaume-Uni','car'),
('Bentley','Royaume-Uni','car'),
('Rolls-Royce','Royaume-Uni','car'),
('Aston Martin','Royaume-Uni','car'),
('Ram','États-Unis','car'),
('Lincoln','États-Unis','car'),
('Saab','Suède','car'),
('Lada','Russie','car');

-- Modèles AUTO supplémentaires
-- Renault (compléter)
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='Renault'),'Laguna',1993,2015),
((SELECT id FROM vehicle_brands WHERE name='Renault'),'Espace',1984,NULL),
((SELECT id FROM vehicle_brands WHERE name='Renault'),'Talisman',2015,2023),
((SELECT id FROM vehicle_brands WHERE name='Renault'),'Koleos',2008,NULL),
((SELECT id FROM vehicle_brands WHERE name='Renault'),'Zoe',2012,NULL),
((SELECT id FROM vehicle_brands WHERE name='Renault'),'Express',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Renault'),'Rafale',2023,NULL);

-- Peugeot (compléter)
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='Peugeot'),'206',1998,2012),
((SELECT id FROM vehicle_brands WHERE name='Peugeot'),'207',2006,2014),
((SELECT id FROM vehicle_brands WHERE name='Peugeot'),'306',1993,2002),
((SELECT id FROM vehicle_brands WHERE name='Peugeot'),'406',1995,2004),
((SELECT id FROM vehicle_brands WHERE name='Peugeot'),'407',2004,2011),
((SELECT id FROM vehicle_brands WHERE name='Peugeot'),'607',2000,2010),
((SELECT id FROM vehicle_brands WHERE name='Peugeot'),'4008',2012,NULL),
((SELECT id FROM vehicle_brands WHERE name='Peugeot'),'e-208',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Peugeot'),'e-2008',2019,NULL);

-- Citroën (compléter)
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='Citroën'),'C1',2005,NULL),
((SELECT id FROM vehicle_brands WHERE name='Citroën'),'C2',2003,2010),
((SELECT id FROM vehicle_brands WHERE name='Citroën'),'C5',2001,2017),
((SELECT id FROM vehicle_brands WHERE name='Citroën'),'Xsara',1997,2006),
((SELECT id FROM vehicle_brands WHERE name='Citroën'),'Saxo',1996,2004),
((SELECT id FROM vehicle_brands WHERE name='Citroën'),'Xantia',1993,2003),
((SELECT id FROM vehicle_brands WHERE name='Citroën'),'ZX',1991,1998),
((SELECT id FROM vehicle_brands WHERE name='Citroën'),'C4 Picasso',2006,2018),
((SELECT id FROM vehicle_brands WHERE name='Citroën'),'C5 X',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Citroën'),'ë-C3',2024,NULL);

-- Volkswagen (compléter)
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='Volkswagen'),'Touareg',2002,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volkswagen'),'Touran',2003,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volkswagen'),'Sharan',1995,2022),
((SELECT id FROM vehicle_brands WHERE name='Volkswagen'),'Caddy',1995,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volkswagen'),'Amarok',2010,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volkswagen'),'Arteon',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volkswagen'),'ID.5',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volkswagen'),'ID.7',2023,NULL);

-- BMW (compléter)
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='BMW'),'Série 2',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'Série 4',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'Série 6',2003,2018),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'Série 7',1977,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'Série 8',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'X2',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'X4',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'X5',1999,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'X6',2008,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'X7',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'iX',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'i4',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'M3',1986,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW'),'M5',1984,NULL);

-- Mercedes-Benz (compléter)
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'Classe B',2005,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'Classe M / GLE',1997,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'GLS',2012,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'GLB',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'CLA',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'CLS',2004,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'EQA',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'EQB',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'EQC',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'AMG GT',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'Sprinter',1995,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mercedes-Benz'),'Vito',1996,NULL);

-- Audi (compléter)
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='Audi'),'A2',1999,2005),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'A5',2007,NULL),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'A6',1994,NULL),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'A7',2010,NULL),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'A8',1994,NULL),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'Q2',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'Q4 e-tron',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'Q7',2005,NULL),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'Q8',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'TT',1998,2023),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'R8',2006,2023),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'e-tron GT',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'RS3',2011,NULL),
((SELECT id FROM vehicle_brands WHERE name='Audi'),'RS6',1999,NULL);

-- Toyota (compléter)
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='Toyota'),'Land Cruiser',1951,NULL),
((SELECT id FROM vehicle_brands WHERE name='Toyota'),'Hilux',1968,NULL),
((SELECT id FROM vehicle_brands WHERE name='Toyota'),'Camry',1982,NULL),
((SELECT id FROM vehicle_brands WHERE name='Toyota'),'Prius',1997,NULL),
((SELECT id FROM vehicle_brands WHERE name='Toyota'),'bZ4X',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Toyota'),'Proace',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='Toyota'),'GR Yaris',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Toyota'),'Supra',1978,NULL);

-- Honda
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='Honda'),'Civic',1972,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda'),'CR-V',1995,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda'),'Jazz',2001,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda'),'HR-V',1998,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda'),'Accord',1976,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda'),'e',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda'),'ZR-V',2023,NULL);

-- Ford (compléter)
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='Ford'),'Fiesta',1976,2023),
((SELECT id FROM vehicle_brands WHERE name='Ford'),'Focus',1998,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ford'),'Mondeo',1993,2022),
((SELECT id FROM vehicle_brands WHERE name='Ford'),'Puma',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ford'),'Kuga',2008,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ford'),'Explorer',1990,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ford'),'Mustang',1964,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ford'),'Mustang Mach-E',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ford'),'Transit',1965,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ford'),'Ranger',1983,NULL);

-- Dacia (compléter)
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='Dacia'),'Bigster',2025,NULL),
((SELECT id FROM vehicle_brands WHERE name='Dacia'),'Pick-Up',2007,2012);

-- Modèles pour nouvelles marques
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
-- Porsche
((SELECT id FROM vehicle_brands WHERE name='Porsche'),'911',1963,NULL),
((SELECT id FROM vehicle_brands WHERE name='Porsche'),'Cayenne',2002,NULL),
((SELECT id FROM vehicle_brands WHERE name='Porsche'),'Macan',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='Porsche'),'Panamera',2009,NULL),
((SELECT id FROM vehicle_brands WHERE name='Porsche'),'Taycan',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Porsche'),'Boxster',1996,NULL),
((SELECT id FROM vehicle_brands WHERE name='Porsche'),'Cayman',2005,NULL),
-- Alfa Romeo
((SELECT id FROM vehicle_brands WHERE name='Alfa Romeo'),'147',2000,2010),
((SELECT id FROM vehicle_brands WHERE name='Alfa Romeo'),'156',1997,2007),
((SELECT id FROM vehicle_brands WHERE name='Alfa Romeo'),'159',2005,2011),
((SELECT id FROM vehicle_brands WHERE name='Alfa Romeo'),'Giulia',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='Alfa Romeo'),'Stelvio',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Alfa Romeo'),'Giulietta',2010,2020),
((SELECT id FROM vehicle_brands WHERE name='Alfa Romeo'),'Tonale',2022,NULL),
-- Fiat (compléter)
((SELECT id FROM vehicle_brands WHERE name='Fiat'),'500',2007,NULL),
((SELECT id FROM vehicle_brands WHERE name='Fiat'),'Panda',1980,NULL),
((SELECT id FROM vehicle_brands WHERE name='Fiat'),'Punto',1993,2018),
((SELECT id FROM vehicle_brands WHERE name='Fiat'),'Bravo',2007,2014),
((SELECT id FROM vehicle_brands WHERE name='Fiat'),'Tipo',1988,NULL),
((SELECT id FROM vehicle_brands WHERE name='Fiat'),'500X',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='Fiat'),'500e',2020,NULL),
-- Tesla
((SELECT id FROM vehicle_brands WHERE name='Tesla'),'Model 3',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Tesla'),'Model Y',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Tesla'),'Model S',2012,NULL),
((SELECT id FROM vehicle_brands WHERE name='Tesla'),'Model X',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='Tesla'),'Cybertruck',2023,NULL),
-- Land Rover
((SELECT id FROM vehicle_brands WHERE name='Land Rover'),'Defender',1983,NULL),
((SELECT id FROM vehicle_brands WHERE name='Land Rover'),'Discovery',1989,NULL),
((SELECT id FROM vehicle_brands WHERE name='Land Rover'),'Range Rover',1970,NULL),
((SELECT id FROM vehicle_brands WHERE name='Land Rover'),'Range Rover Sport',2005,NULL),
((SELECT id FROM vehicle_brands WHERE name='Land Rover'),'Evoque',2011,NULL),
((SELECT id FROM vehicle_brands WHERE name='Land Rover'),'Freelander',1997,2014),
-- Jeep
((SELECT id FROM vehicle_brands WHERE name='Jeep'),'Wrangler',1986,NULL),
((SELECT id FROM vehicle_brands WHERE name='Jeep'),'Cherokee',1984,NULL),
((SELECT id FROM vehicle_brands WHERE name='Jeep'),'Grand Cherokee',1992,NULL),
((SELECT id FROM vehicle_brands WHERE name='Jeep'),'Renegade',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='Jeep'),'Compass',2006,NULL),
-- Volvo
((SELECT id FROM vehicle_brands WHERE name='Volvo'),'XC40',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volvo'),'XC60',2008,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volvo'),'XC90',2002,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volvo'),'V40',2012,2019),
((SELECT id FROM vehicle_brands WHERE name='Volvo'),'V60',2010,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volvo'),'V90',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volvo'),'S60',2000,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volvo'),'S90',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Volvo'),'C40',2021,NULL),
-- Skoda
((SELECT id FROM vehicle_brands WHERE name='Skoda'),'Fabia',1999,NULL),
((SELECT id FROM vehicle_brands WHERE name='Skoda'),'Octavia',1996,NULL),
((SELECT id FROM vehicle_brands WHERE name='Skoda'),'Superb',2001,NULL),
((SELECT id FROM vehicle_brands WHERE name='Skoda'),'Kodiaq',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Skoda'),'Karoq',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Skoda'),'Enyaq',2020,NULL),
-- Seat
((SELECT id FROM vehicle_brands WHERE name='Seat'),'Ibiza',1984,NULL),
((SELECT id FROM vehicle_brands WHERE name='Seat'),'Leon',1999,NULL),
((SELECT id FROM vehicle_brands WHERE name='Seat'),'Ateca',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Seat'),'Tarraco',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Seat'),'Arona',2017,NULL),
-- Hyundai (compléter)
((SELECT id FROM vehicle_brands WHERE name='Hyundai'),'i10',2007,NULL),
((SELECT id FROM vehicle_brands WHERE name='Hyundai'),'i20',2008,NULL),
((SELECT id FROM vehicle_brands WHERE name='Hyundai'),'i30',2007,NULL),
((SELECT id FROM vehicle_brands WHERE name='Hyundai'),'Tucson',2004,NULL),
((SELECT id FROM vehicle_brands WHERE name='Hyundai'),'Santa Fe',2000,NULL),
((SELECT id FROM vehicle_brands WHERE name='Hyundai'),'Ioniq 5',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Hyundai'),'Ioniq 6',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Hyundai'),'NEXO',2018,NULL),
-- Kia (compléter)
((SELECT id FROM vehicle_brands WHERE name='Kia'),'Picanto',2004,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kia'),'Rio',2000,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kia'),'Ceed',2006,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kia'),'Sportage',1993,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kia'),'Sorento',2002,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kia'),'EV6',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kia'),'Niro',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kia'),'Stinger',2017,2022),
-- Nissan (compléter)
((SELECT id FROM vehicle_brands WHERE name='Nissan'),'Micra',1982,NULL),
((SELECT id FROM vehicle_brands WHERE name='Nissan'),'Juke',2010,NULL),
((SELECT id FROM vehicle_brands WHERE name='Nissan'),'Qashqai',2006,NULL),
((SELECT id FROM vehicle_brands WHERE name='Nissan'),'X-Trail',2001,NULL),
((SELECT id FROM vehicle_brands WHERE name='Nissan'),'Leaf',2010,NULL),
((SELECT id FROM vehicle_brands WHERE name='Nissan'),'370Z',2009,NULL),
((SELECT id FROM vehicle_brands WHERE name='Nissan'),'GT-R',2007,NULL),
((SELECT id FROM vehicle_brands WHERE name='Nissan'),'Navara',1986,NULL),
-- Mazda (compléter)
((SELECT id FROM vehicle_brands WHERE name='Mazda'),'2',2002,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mazda'),'3',2003,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mazda'),'6',2002,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mazda'),'CX-3',2015,2023),
((SELECT id FROM vehicle_brands WHERE name='Mazda'),'CX-5',2012,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mazda'),'CX-30',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mazda'),'MX-5',1989,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mazda'),'MX-30',2020,NULL),
-- Opel (compléter)
((SELECT id FROM vehicle_brands WHERE name='Opel'),'Corsa',1982,NULL),
((SELECT id FROM vehicle_brands WHERE name='Opel'),'Astra',1991,NULL),
((SELECT id FROM vehicle_brands WHERE name='Opel'),'Insignia',2008,NULL),
((SELECT id FROM vehicle_brands WHERE name='Opel'),'Mokka',2012,NULL),
((SELECT id FROM vehicle_brands WHERE name='Opel'),'Crossland',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Opel'),'Grandland',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Opel'),'Zafira',1999,2019),
((SELECT id FROM vehicle_brands WHERE name='Opel'),'Meriva',2003,2017),
((SELECT id FROM vehicle_brands WHERE name='Opel'),'Mokka-e',2021,NULL),
-- Suzuki
((SELECT id FROM vehicle_brands WHERE name='Suzuki'),'Swift',1983,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki'),'Vitara',1988,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki'),'SX4 S-Cross',2006,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki'),'Jimny',1970,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki'),'Ignis',2000,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki'),'Across',2020,NULL),
-- Mitsubishi
((SELECT id FROM vehicle_brands WHERE name='Mitsubishi'),'Colt',1978,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mitsubishi'),'Outlander',2001,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mitsubishi'),'Eclipse Cross',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mitsubishi'),'ASX',2010,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mitsubishi'),'L200',1978,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mitsubishi'),'Lancer Evo',1992,2016),
-- Subaru
((SELECT id FROM vehicle_brands WHERE name='Subaru'),'Impreza',1992,NULL),
((SELECT id FROM vehicle_brands WHERE name='Subaru'),'Legacy',1989,NULL),
((SELECT id FROM vehicle_brands WHERE name='Subaru'),'Forester',1997,NULL),
((SELECT id FROM vehicle_brands WHERE name='Subaru'),'Outback',1994,NULL),
((SELECT id FROM vehicle_brands WHERE name='Subaru'),'XV / Crosstrek',2011,NULL),
((SELECT id FROM vehicle_brands WHERE name='Subaru'),'WRX STI',1994,2021),
-- Cupra
((SELECT id FROM vehicle_brands WHERE name='Cupra'),'Formentor',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Cupra'),'Born',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Cupra'),'Leon',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Cupra'),'Ateca',2018,NULL),
-- MG
((SELECT id FROM vehicle_brands WHERE name='MG'),'ZS',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='MG'),'4',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='MG'),'5',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='MG'),'HS',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='MG'),'Marvel R',2021,NULL),
-- BYD
((SELECT id FROM vehicle_brands WHERE name='BYD'),'Atto 3',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='BYD'),'Seal',2023,NULL),
((SELECT id FROM vehicle_brands WHERE name='BYD'),'Han',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='BYD'),'Dolphin',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='BYD'),'Tang',2018,NULL);

-- Marques MOTO
INSERT OR IGNORE INTO vehicle_brands (name, country, category) VALUES
('Honda Moto','Japon','moto'),
('Yamaha','Japon','moto'),
('Kawasaki','Japon','moto'),
('Suzuki Moto','Japon','moto'),
('Ducati','Italie','moto'),
('BMW Motorrad','Allemagne','moto'),
('Harley-Davidson','États-Unis','moto'),
('KTM','Autriche','moto'),
('Triumph','Royaume-Uni','moto'),
('Royal Enfield','Inde','moto'),
('Aprilia','Italie','moto'),
('Moto Guzzi','Italie','moto'),
('Norton','Royaume-Uni','moto'),
('Indian','États-Unis','moto'),
('Zero Motorcycles','États-Unis','moto'),
('Husqvarna','Suède','moto'),
('Beta','Italie','moto'),
('GasGas','Espagne','moto'),
('Benelli','Chine','moto'),
('CF Moto','Chine','moto'),
('Kymco','Taïwan','moto'),
('Piaggio','Italie','moto'),
('Vespa','Italie','moto'),
('SYM','Taïwan','moto');

-- Modèles MOTO
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
-- Honda Moto
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'CBR 600 RR',2003,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'CBR 1000 RR Fireblade',1992,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'CB 500 F/X',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'CB 650 R',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'CB 750 Hornet',2023,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'CB 1000 R',2008,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'Africa Twin',1988,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'NC 750 X/S',2012,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'XL 750 Transalp',2023,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'Gold Wing',1974,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'PCX 125',2010,NULL),
((SELECT id FROM vehicle_brands WHERE name='Honda Moto'),'Forza 750',2021,NULL),
-- Yamaha
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'YZF-R1',1998,NULL),
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'YZF-R6',1999,2020),
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'YZF-R3',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'MT-07',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'MT-09',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'MT-10',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'Tracer 9',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'Ténéré 700',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'XSR 700',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'XSR 900',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'NMAX 125',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='Yamaha'),'TMAX 560',2001,NULL),
-- Kawasaki
((SELECT id FROM vehicle_brands WHERE name='Kawasaki'),'Ninja 400',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kawasaki'),'Ninja 650',2004,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kawasaki'),'Ninja ZX-6R',1995,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kawasaki'),'Ninja ZX-10R',2004,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kawasaki'),'Z400',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kawasaki'),'Z650',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kawasaki'),'Z900',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kawasaki'),'Z1000',2003,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kawasaki'),'Versys 650',2006,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kawasaki'),'Versys 1000',2012,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kawasaki'),'W800',2011,NULL),
-- Suzuki Moto
((SELECT id FROM vehicle_brands WHERE name='Suzuki Moto'),'GSX-R 600',1992,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki Moto'),'GSX-R 750',1985,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki Moto'),'GSX-R 1000',2001,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki Moto'),'GSX-S 750',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki Moto'),'GSX-S 1000',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki Moto'),'SV 650',1999,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki Moto'),'V-Strom 650',2004,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki Moto'),'V-Strom 1050',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Suzuki Moto'),'Burgman 400',1999,NULL),
-- Ducati
((SELECT id FROM vehicle_brands WHERE name='Ducati'),'Panigale V2',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ducati'),'Panigale V4',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ducati'),'Monster 937',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ducati'),'Streetfighter V2',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ducati'),'Multistrada V2',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ducati'),'Multistrada V4',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ducati'),'Scrambler 800',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ducati'),'Diavel V4',2023,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ducati'),'DesertX',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ducati'),'Hypermotard 698 Mono',2024,NULL),
-- BMW Motorrad
((SELECT id FROM vehicle_brands WHERE name='BMW Motorrad'),'R 1250 GS',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW Motorrad'),'R 1250 RT',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW Motorrad'),'S 1000 RR',2009,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW Motorrad'),'S 1000 XR',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW Motorrad'),'F 900 R',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW Motorrad'),'F 900 XR',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW Motorrad'),'F 850 GS',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW Motorrad'),'G 310 R',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW Motorrad'),'R nineT',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='BMW Motorrad'),'M 1000 RR',2021,NULL),
-- KTM
((SELECT id FROM vehicle_brands WHERE name='KTM'),'Duke 390',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='KTM'),'Duke 790',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='KTM'),'Duke 890',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='KTM'),'Duke 1290 Super Duke R',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='KTM'),'RC 390',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='KTM'),'Adventure 790',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='KTM'),'Adventure 890',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='KTM'),'Adventure 1290',2015,NULL),
-- Triumph
((SELECT id FROM vehicle_brands WHERE name='Triumph'),'Street Triple',2007,NULL),
((SELECT id FROM vehicle_brands WHERE name='Triumph'),'Speed Triple',1994,NULL),
((SELECT id FROM vehicle_brands WHERE name='Triumph'),'Daytona 675',2006,2017),
((SELECT id FROM vehicle_brands WHERE name='Triumph'),'Tiger 900',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Triumph'),'Tiger 1200',2012,NULL),
((SELECT id FROM vehicle_brands WHERE name='Triumph'),'Bonneville T120',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Triumph'),'Scrambler 1200',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Triumph'),'Rocket 3',2020,NULL),
-- Harley-Davidson
((SELECT id FROM vehicle_brands WHERE name='Harley-Davidson'),'Sportster S',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Harley-Davidson'),'Iron 883',2009,NULL),
((SELECT id FROM vehicle_brands WHERE name='Harley-Davidson'),'Fat Boy',1990,NULL),
((SELECT id FROM vehicle_brands WHERE name='Harley-Davidson'),'Street Glide',2006,NULL),
((SELECT id FROM vehicle_brands WHERE name='Harley-Davidson'),'Road King',1994,NULL),
((SELECT id FROM vehicle_brands WHERE name='Harley-Davidson'),'Pan America',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Harley-Davidson'),'Nightster',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Harley-Davidson'),'LiveWire',2019,NULL),
-- Royal Enfield
((SELECT id FROM vehicle_brands WHERE name='Royal Enfield'),'Classic 350',2009,NULL),
((SELECT id FROM vehicle_brands WHERE name='Royal Enfield'),'Meteor 350',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Royal Enfield'),'Himalayan',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Royal Enfield'),'Interceptor 650',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Royal Enfield'),'Continental GT 650',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Royal Enfield'),'Guerrilla 450',2024,NULL),
-- Aprilia
((SELECT id FROM vehicle_brands WHERE name='Aprilia'),'RS 660',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Aprilia'),'Tuono 660',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Aprilia'),'RSV4',2009,NULL),
((SELECT id FROM vehicle_brands WHERE name='Aprilia'),'Tuono V4',2011,NULL),
((SELECT id FROM vehicle_brands WHERE name='Aprilia'),'Shiver 900',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Aprilia'),'Dorsoduro 900',2017,NULL),
-- Vespa / Piaggio
((SELECT id FROM vehicle_brands WHERE name='Vespa'),'GTS 300',2008,NULL),
((SELECT id FROM vehicle_brands WHERE name='Vespa'),'Primavera 125',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='Vespa'),'Sprint 125',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='Vespa'),'GTV',2007,NULL),
((SELECT id FROM vehicle_brands WHERE name='Piaggio'),'MP3 300',2006,NULL),
((SELECT id FROM vehicle_brands WHERE name='Piaggio'),'MP3 500',2006,NULL),
((SELECT id FROM vehicle_brands WHERE name='Piaggio'),'Beverly 400',2021,NULL);

-- Marques sans modèles (complément)
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
-- Ferrari
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'F40',1987,1992),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'F50',1995,1997),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'Enzo',2002,2004),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'LaFerrari',2013,2016),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'488 GTB',2015,2020),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'488 Spider',2015,2020),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'F8 Tributo',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'SF90 Stradale',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'Roma',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'Portofino',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'812 Superfast',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'296 GTB',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ferrari'),'Purosangue',2022,NULL),
-- Lamborghini
((SELECT id FROM vehicle_brands WHERE name='Lamborghini'),'Huracán',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lamborghini'),'Huracán EVO',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lamborghini'),'Huracán STO',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lamborghini'),'Urus',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lamborghini'),'Urus S',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lamborghini'),'Revuelto',2023,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lamborghini'),'Diablo',1990,2001),
((SELECT id FROM vehicle_brands WHERE name='Lamborghini'),'Murciélago',2001,2010),
((SELECT id FROM vehicle_brands WHERE name='Lamborghini'),'Gallardo',2003,2013),
((SELECT id FROM vehicle_brands WHERE name='Lamborghini'),'Aventador',2011,2022),
-- Maserati
((SELECT id FROM vehicle_brands WHERE name='Maserati'),'Ghibli',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='Maserati'),'Quattroporte',1963,NULL),
((SELECT id FROM vehicle_brands WHERE name='Maserati'),'Levante',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Maserati'),'GranTurismo',2007,NULL),
((SELECT id FROM vehicle_brands WHERE name='Maserati'),'GranCabrio',2009,NULL),
((SELECT id FROM vehicle_brands WHERE name='Maserati'),'Grecale',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Maserati'),'MC20',2020,NULL),
-- Alpine
((SELECT id FROM vehicle_brands WHERE name='Alpine'),'A110',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Alpine'),'A110 S',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Alpine'),'A110 R',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Alpine'),'A290',2024,NULL),
-- Bugatti
((SELECT id FROM vehicle_brands WHERE name='Bugatti'),'Veyron',2005,2015),
((SELECT id FROM vehicle_brands WHERE name='Bugatti'),'Chiron',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Bugatti'),'Chiron Pur Sport',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Bugatti'),'Bolide',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Bugatti'),'Tourbillon',2024,NULL),
-- Porsche (compléter)
((SELECT id FROM vehicle_brands WHERE name='Porsche'),'718 Cayman',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Porsche'),'718 Boxster',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Porsche'),'Macan EV',2024,NULL),
((SELECT id FROM vehicle_brands WHERE name='Porsche'),'Cayenne E-Hybrid',2019,NULL),
-- Chevrolet
((SELECT id FROM vehicle_brands WHERE name='Chevrolet'),'Corvette',1953,NULL),
((SELECT id FROM vehicle_brands WHERE name='Chevrolet'),'Camaro',1966,2024),
((SELECT id FROM vehicle_brands WHERE name='Chevrolet'),'Silverado',1999,NULL),
((SELECT id FROM vehicle_brands WHERE name='Chevrolet'),'Equinox',2004,NULL),
((SELECT id FROM vehicle_brands WHERE name='Chevrolet'),'Tahoe',1995,NULL),
((SELECT id FROM vehicle_brands WHERE name='Chevrolet'),'Blazer',1969,NULL),
((SELECT id FROM vehicle_brands WHERE name='Chevrolet'),'Trax',2012,NULL),
-- Dodge
((SELECT id FROM vehicle_brands WHERE name='Dodge'),'Charger',1964,NULL),
((SELECT id FROM vehicle_brands WHERE name='Dodge'),'Challenger',1970,2023),
((SELECT id FROM vehicle_brands WHERE name='Dodge'),'Durango',1997,NULL),
((SELECT id FROM vehicle_brands WHERE name='Dodge'),'Ram 1500',1980,NULL),
-- Polestar
((SELECT id FROM vehicle_brands WHERE name='Polestar'),'Polestar 2',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Polestar'),'Polestar 3',2023,NULL),
((SELECT id FROM vehicle_brands WHERE name='Polestar'),'Polestar 4',2024,NULL),
-- Mini
((SELECT id FROM vehicle_brands WHERE name='Mini'),'Cooper',2001,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mini'),'Countryman',2010,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mini'),'Clubman',2007,2024),
((SELECT id FROM vehicle_brands WHERE name='Mini'),'Paceman',2012,2016),
((SELECT id FROM vehicle_brands WHERE name='Mini'),'Cabrio',2004,NULL),
((SELECT id FROM vehicle_brands WHERE name='Mini'),'Aceman',2024,NULL),
-- Genesis
((SELECT id FROM vehicle_brands WHERE name='Genesis'),'G70',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Genesis'),'G80',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Genesis'),'GV70',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Genesis'),'GV80',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Genesis'),'Electrified G80',2022,NULL),
-- Jaguar
((SELECT id FROM vehicle_brands WHERE name='Jaguar'),'XE',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='Jaguar'),'XF',2008,NULL),
((SELECT id FROM vehicle_brands WHERE name='Jaguar'),'XJ',1968,2019),
((SELECT id FROM vehicle_brands WHERE name='Jaguar'),'F-Type',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='Jaguar'),'F-Pace',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Jaguar'),'E-Pace',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Jaguar'),'I-Pace',2018,NULL),
-- Bentley
((SELECT id FROM vehicle_brands WHERE name='Bentley'),'Continental GT',2003,NULL),
((SELECT id FROM vehicle_brands WHERE name='Bentley'),'Flying Spur',2005,NULL),
((SELECT id FROM vehicle_brands WHERE name='Bentley'),'Bentayga',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='Bentley'),'Mulliner',2021,NULL),
-- Rolls-Royce
((SELECT id FROM vehicle_brands WHERE name='Rolls-Royce'),'Ghost',2009,NULL),
((SELECT id FROM vehicle_brands WHERE name='Rolls-Royce'),'Phantom',1925,NULL),
((SELECT id FROM vehicle_brands WHERE name='Rolls-Royce'),'Wraith',2013,2023),
((SELECT id FROM vehicle_brands WHERE name='Rolls-Royce'),'Cullinan',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Rolls-Royce'),'Spectre',2023,NULL),
-- Aston Martin
((SELECT id FROM vehicle_brands WHERE name='Aston Martin'),'DB11',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Aston Martin'),'Vantage',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Aston Martin'),'DBX',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Aston Martin'),'DBS',2007,NULL),
((SELECT id FROM vehicle_brands WHERE name='Aston Martin'),'Valkyrie',2021,NULL),
-- BYD (compléter)
((SELECT id FROM vehicle_brands WHERE name='BYD'),'Seal U',2023,NULL),
((SELECT id FROM vehicle_brands WHERE name='BYD'),'Lion 6',2024,NULL),
-- CF Moto (moto)
((SELECT id FROM vehicle_brands WHERE name='CF Moto'),'300NK',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='CF Moto'),'400NK',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='CF Moto'),'700CL-X',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='CF Moto'),'800MT',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='CF Moto'),'450SR',2023,NULL),
-- Benelli (moto)
((SELECT id FROM vehicle_brands WHERE name='Benelli'),'TRK 502',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Benelli'),'TRK 800',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Benelli'),'Leoncino 500',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Benelli'),'TNT 300',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='Benelli'),'Imperiale 400',2019,NULL),
-- Kymco (moto/scooter)
((SELECT id FROM vehicle_brands WHERE name='Kymco'),'AK 550',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kymco'),'CV3',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kymco'),'Xciting S 400',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Kymco'),'Downtown 350',2015,NULL),
-- SYM (scooter)
((SELECT id FROM vehicle_brands WHERE name='SYM'),'Cruisym 300',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='SYM'),'Joyride 300',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='SYM'),'Jet X 125',2019,NULL),
-- Zero Motorcycles
((SELECT id FROM vehicle_brands WHERE name='Zero Motorcycles'),'SR/F',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Zero Motorcycles'),'SR/S',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Zero Motorcycles'),'DSR/X',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Zero Motorcycles'),'FXE',2022,NULL),
-- Norton
((SELECT id FROM vehicle_brands WHERE name='Norton'),'Commando 961',2008,NULL),
((SELECT id FROM vehicle_brands WHERE name='Norton'),'V4CR',2023,NULL),
((SELECT id FROM vehicle_brands WHERE name='Norton'),'V4SV',2023,NULL),
-- Indian
((SELECT id FROM vehicle_brands WHERE name='Indian'),'Scout',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='Indian'),'Chief',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Indian'),'Challenger',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Indian'),'FTR 1200',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Indian'),'Pursuit',2021,NULL),
-- Moto Guzzi
((SELECT id FROM vehicle_brands WHERE name='Moto Guzzi'),'V7',2008,NULL),
((SELECT id FROM vehicle_brands WHERE name='Moto Guzzi'),'V9 Bobber',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Moto Guzzi'),'V85 TT',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Moto Guzzi'),'V100 Mandello',2022,NULL),
-- Husqvarna
((SELECT id FROM vehicle_brands WHERE name='Husqvarna'),'Vitpilen 401',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Husqvarna'),'Svartpilen 401',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Husqvarna'),'Norden 901',2022,NULL),
-- GasGas
((SELECT id FROM vehicle_brands WHERE name='GasGas'),'ES 700',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='GasGas'),'SM 700',2022,NULL);

-- Marques encore sans modèles
INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
((SELECT id FROM vehicle_brands WHERE name='Lexus'),'RX',1998,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lexus'),'NX',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lexus'),'IS',1998,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lexus'),'ES',1989,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lexus'),'UX',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Cadillac'),'Escalade',1999,NULL),
((SELECT id FROM vehicle_brands WHERE name='Cadillac'),'CT5',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Cadillac'),'XT5',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Cadillac'),'Lyriq',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Chrysler'),'300',2004,NULL),
((SELECT id FROM vehicle_brands WHERE name='Chrysler'),'Pacifica',2016,NULL),
((SELECT id FROM vehicle_brands WHERE name='Chrysler'),'Voyager',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lancia'),'Ypsilon',1995,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lancia'),'Delta',1979,2014),
((SELECT id FROM vehicle_brands WHERE name='Lancia'),'Thema',2011,2014),
((SELECT id FROM vehicle_brands WHERE name='Infiniti'),'Q50',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='Infiniti'),'QX50',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='Infiniti'),'QX60',2012,NULL),
((SELECT id FROM vehicle_brands WHERE name='Acura'),'MDX',2000,NULL),
((SELECT id FROM vehicle_brands WHERE name='Acura'),'RDX',2006,NULL),
((SELECT id FROM vehicle_brands WHERE name='Acura'),'TLX',2014,NULL),
((SELECT id FROM vehicle_brands WHERE name='Acura'),'Integra',2022,NULL),
((SELECT id FROM vehicle_brands WHERE name='Isuzu'),'D-Max',2002,NULL),
((SELECT id FROM vehicle_brands WHERE name='Isuzu'),'MU-X',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lada'),'Niva',1977,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lada'),'Granta',2011,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lada'),'Vesta',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lincoln'),'Navigator',1997,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lincoln'),'Aviator',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lincoln'),'Corsair',2019,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lynk & Co'),'01',2017,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lynk & Co'),'02',2018,NULL),
((SELECT id FROM vehicle_brands WHERE name='Lynk & Co'),'03',2021,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ram'),'1500',1980,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ram'),'2500',1994,NULL),
((SELECT id FROM vehicle_brands WHERE name='Ram'),'ProMaster',2013,NULL),
((SELECT id FROM vehicle_brands WHERE name='Saab'),'9-3',1998,2014),
((SELECT id FROM vehicle_brands WHERE name='Saab'),'9-5',1997,2012),
((SELECT id FROM vehicle_brands WHERE name='Saab'),'900',1978,1998),
((SELECT id FROM vehicle_brands WHERE name='SsangYong'),'Rexton',2001,NULL),
((SELECT id FROM vehicle_brands WHERE name='SsangYong'),'Tivoli',2015,NULL),
((SELECT id FROM vehicle_brands WHERE name='SsangYong'),'Korando',1983,NULL),
((SELECT id FROM vehicle_brands WHERE name='Beta'),'RR 125',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Beta'),'RR 250',2020,NULL),
((SELECT id FROM vehicle_brands WHERE name='Beta'),'RR 430',2022,NULL);

-- Motorisations (engine_types)
INSERT OR IGNORE INTO engine_types (model_id, label, fuel_type, displacement, power_hp, year_start, year_end) VALUES
-- Peugeot 206
((SELECT id FROM vehicle_models WHERE name='206' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Peugeot')), '1.1i 60ch','essence','1.1L',60,1998,2012),
((SELECT id FROM vehicle_models WHERE name='206' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Peugeot')), '1.4i 75ch','essence','1.4L',75,1998,2012),
((SELECT id FROM vehicle_models WHERE name='206' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Peugeot')), '1.6i 110ch','essence','1.6L',110,1998,2012),
((SELECT id FROM vehicle_models WHERE name='206' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Peugeot')), '1.4 HDi 70ch','diesel','1.4L',70,2001,2012),
((SELECT id FROM vehicle_models WHERE name='206' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Peugeot')), '1.6 HDi 90ch','diesel','1.6L',90,2001,2012),
-- Peugeot 308
((SELECT id FROM vehicle_models WHERE name='308' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Peugeot')), '1.2 PureTech 110ch','essence','1.2L',110,2013,NULL),
((SELECT id FROM vehicle_models WHERE name='308' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Peugeot')), '1.2 PureTech 130ch','essence','1.2L',130,2017,NULL),
((SELECT id FROM vehicle_models WHERE name='308' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Peugeot')), '1.6 THP 200ch','essence','1.6L',200,2007,2021),
((SELECT id FROM vehicle_models WHERE name='308' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Peugeot')), '1.5 BlueHDi 100ch','diesel','1.5L',100,2017,NULL),
((SELECT id FROM vehicle_models WHERE name='308' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Peugeot')), '2.0 BlueHDi 150ch','diesel','2.0L',150,2013,NULL),
((SELECT id FROM vehicle_models WHERE name='308' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Peugeot')), 'Hybrid 180ch PHEV','hybride','1.6L',180,2021,NULL),
-- Renault Clio
((SELECT id FROM vehicle_models WHERE name='Clio' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Renault')), '1.0 SCe 65ch','essence','1.0L',65,2019,NULL),
((SELECT id FROM vehicle_models WHERE name='Clio' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Renault')), '1.3 TCe 130ch','essence','1.3L',130,2019,NULL),
((SELECT id FROM vehicle_models WHERE name='Clio' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Renault')), 'E-Tech 140ch Hybride','hybride','1.6L',140,2020,NULL),
((SELECT id FROM vehicle_models WHERE name='Clio' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Renault')), '1.5 dCi 85ch','diesel','1.5L',85,2001,NULL),
-- VW Golf
((SELECT id FROM vehicle_models WHERE name='Golf' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Volkswagen')), '1.0 TSI 110ch','essence','1.0L',110,2017,NULL),
((SELECT id FROM vehicle_models WHERE name='Golf' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Volkswagen')), '1.5 eTSI 150ch Mild Hybrid','hybride','1.5L',150,2020,NULL),
((SELECT id FROM vehicle_models WHERE name='Golf' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Volkswagen')), '1.4 GTE PHEV 245ch','hybride','1.4L',245,2014,NULL),
((SELECT id FROM vehicle_models WHERE name='Golf' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Volkswagen')), '2.0 TDI 115ch','diesel','2.0L',115,2012,NULL),
((SELECT id FROM vehicle_models WHERE name='Golf' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Volkswagen')), '2.0 TDI 150ch','diesel','2.0L',150,2012,NULL),
((SELECT id FROM vehicle_models WHERE name='Golf' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='Volkswagen')), '2.0 TSI R 320ch','essence','2.0L',320,2014,NULL),
-- BMW Série 3
((SELECT id FROM vehicle_models WHERE name='Série 3' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='BMW')), '318i 136ch','essence','2.0L',136,2019,NULL),
((SELECT id FROM vehicle_models WHERE name='Série 3' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='BMW')), '320i 184ch','essence','2.0L',184,2012,NULL),
((SELECT id FROM vehicle_models WHERE name='Série 3' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='BMW')), '330i 258ch','essence','2.0L',258,2019,NULL),
((SELECT id FROM vehicle_models WHERE name='Série 3' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='BMW')), '318d 150ch','diesel','2.0L',150,2019,NULL),
((SELECT id FROM vehicle_models WHERE name='Série 3' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='BMW')), '320d 190ch','diesel','2.0L',190,2012,NULL),
((SELECT id FROM vehicle_models WHERE name='Série 3' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='BMW')), '330e PHEV 292ch','hybride','2.0L',292,2019,NULL),
-- BMW Série 5
((SELECT id FROM vehicle_models WHERE name='Série 5' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='BMW')), '520i 184ch','essence','2.0L',184,2017,NULL),
((SELECT id FROM vehicle_models WHERE name='Série 5' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='BMW')), '530i 252ch','essence','2.0L',252,2017,NULL),
((SELECT id FROM vehicle_models WHERE name='Série 5' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='BMW')), '520d 190ch','diesel','2.0L',190,2017,NULL),
((SELECT id FROM vehicle_models WHERE name='Série 5' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='BMW')), '530d 286ch','diesel','3.0L',286,2017,NULL),
((SELECT id FROM vehicle_models WHERE name='Série 5' AND brand_id=(SELECT id FROM vehicle_brands WHERE name='BMW')), '545e PHEV 394ch','hybride','3.0L',394,2020,NULL);

