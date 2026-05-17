-- MecaBuddy — Clés LLM personnelles (BYOK) par utilisateur démo

CREATE TABLE IF NOT EXISTS demo_user_llm_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    provider_type TEXT NOT NULL CHECK(provider_type IN ('mistral', 'gemini')),
    provider_name TEXT NOT NULL,
    base_url TEXT NULL,
    model TEXT NOT NULL,
    api_key_encrypted TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 0,
    is_validated INTEGER NOT NULL DEFAULT 0,
    last_test_status TEXT NULL,
    last_test_message TEXT NULL,
    last_test_at TEXT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    UNIQUE(user_id, provider_type),
    FOREIGN KEY(user_id) REFERENCES demo_users(id)
);

CREATE INDEX IF NOT EXISTS idx_demo_user_llm_keys_user ON demo_user_llm_keys(user_id);
