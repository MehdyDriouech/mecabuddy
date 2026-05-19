-- MecaBuddy — Véhicules liés aux comptes démo (migration idempotente)
-- demo_user_id : garage isolé par compte démo
-- is_demo_seed : 1 = véhicule préconfiguré (supprimable via reset démo uniquement)
-- Note : migrateSQLiteDemoVehicles() dans includes/demo_vehicles.php applique aussi ces colonnes au runtime.

ALTER TABLE vehicles ADD COLUMN demo_user_id INTEGER NULL;
ALTER TABLE vehicles ADD COLUMN is_demo_seed INTEGER NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_vehicles_demo_user_id ON vehicles(demo_user_id);
