import { pool } from './pool.js';

export async function runMigrations(): Promise<void> {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS wa_sessions (
      session_id TEXT PRIMARY KEY,
      status TEXT NOT NULL DEFAULT 'idle',
      mode TEXT,
      phone_number TEXT,
      me_jid TEXT,
      qr TEXT,
      pairing_code TEXT,
      last_connected_at TIMESTAMPTZ,
      last_error_code INTEGER,
      last_error_message TEXT,
      created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
      updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    );
  `);

  await pool.query(`
    CREATE TABLE IF NOT EXISTS wa_auth_credentials (
      session_id TEXT PRIMARY KEY REFERENCES wa_sessions(session_id) ON DELETE CASCADE,
      creds_payload TEXT NOT NULL,
      updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    );
  `);

  await pool.query(`
    CREATE TABLE IF NOT EXISTS wa_auth_keys (
      session_id TEXT NOT NULL REFERENCES wa_sessions(session_id) ON DELETE CASCADE,
      category TEXT NOT NULL,
      key_id TEXT NOT NULL,
      value_payload TEXT NOT NULL,
      updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
      PRIMARY KEY (session_id, category, key_id)
    );
  `);

  await pool.query(`
    CREATE TABLE IF NOT EXISTS wa_messages (
      session_id TEXT NOT NULL REFERENCES wa_sessions(session_id) ON DELETE CASCADE,
      remote_jid TEXT NOT NULL,
      message_id TEXT NOT NULL,
      participant TEXT,
      from_me BOOLEAN NOT NULL DEFAULT FALSE,
      message_timestamp BIGINT,
      push_name TEXT,
      message_payload TEXT NOT NULL,
      created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
      updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
      PRIMARY KEY (session_id, remote_jid, message_id)
    );
  `);

  await pool.query(`
    CREATE INDEX IF NOT EXISTS wa_messages_session_remote_created_idx
    ON wa_messages(session_id, remote_jid, created_at DESC);
  `);
}
