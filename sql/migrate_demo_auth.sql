-- MecaBuddy — Auth démo & quotas journaliers (migration idempotente)

CREATE TABLE IF NOT EXISTS demo_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    tutorial_daily_quota INTEGER NOT NULL DEFAULT 15,
    buddy_daily_quota INTEGER NOT NULL DEFAULT 15,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS demo_usage_daily (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    usage_date TEXT NOT NULL,
    usage_type TEXT NOT NULL CHECK(usage_type IN ('tutorial', 'buddy')),
    used_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    UNIQUE(user_id, usage_date, usage_type),
    FOREIGN KEY(user_id) REFERENCES demo_users(id)
);

CREATE INDEX IF NOT EXISTS idx_demo_usage_user_date ON demo_usage_daily(user_id, usage_date);
