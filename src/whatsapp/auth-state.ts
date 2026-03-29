import {
  initAuthCreds,
  makeCacheableSignalKeyStore,
  proto,
  type AuthenticationCreds,
  type AuthenticationState,
  type SignalDataSet,
  type SignalDataTypeMap,
  type SignalKeyStore,
} from 'baileys';
import type { Logger } from 'pino';
import type { PoolClient } from 'pg';

import { pool } from '../db/pool.js';
import { decodeBaileysPayload, encodeBaileysPayload } from '../lib/crypto.js';
import { SessionRepository } from './session-repository.js';

type SignalCategory = keyof SignalDataTypeMap;

async function loadCreds(sessionId: string): Promise<AuthenticationCreds> {
  const result = await pool.query(
    `
      SELECT creds_payload
      FROM wa_auth_credentials
      WHERE session_id = $1
    `,
    [sessionId],
  );

  if (!result.rowCount) {
    return initAuthCreds();
  }

  return decodeBaileysPayload<AuthenticationCreds>(result.rows[0].creds_payload);
}

function reviveSignalValue<T extends SignalCategory>(type: T, value: SignalDataTypeMap[T]): SignalDataTypeMap[T] {
  if (type === 'app-state-sync-key' && value) {
    return proto.Message.AppStateSyncKeyData.fromObject(value as object) as unknown as SignalDataTypeMap[T];
  }

  return value;
}

async function setSignalData(
  client: PoolClient,
  sessionId: string,
  data: SignalDataSet,
): Promise<void> {
  const entries = Object.entries(data) as Array<[SignalCategory, Record<string, unknown>]>;

  for (const [category, values] of entries) {
    for (const [keyId, value] of Object.entries(values ?? {})) {
      if (value) {
        await client.query(
          `
            INSERT INTO wa_auth_keys(session_id, category, key_id, value_payload)
            VALUES ($1, $2, $3, $4)
            ON CONFLICT (session_id, category, key_id)
            DO UPDATE SET value_payload = EXCLUDED.value_payload, updated_at = NOW()
          `,
          [sessionId, category, keyId, encodeBaileysPayload(value)],
        );
      } else {
        await client.query(
          `
            DELETE FROM wa_auth_keys
            WHERE session_id = $1 AND category = $2 AND key_id = $3
          `,
          [sessionId, category, keyId],
        );
      }
    }
  }
}

export async function createPostgresAuthState(sessionId: string, logger: Logger): Promise<{
  clear: () => Promise<void>;
  saveCreds: () => Promise<void>;
  state: AuthenticationState;
}> {
  const sessions = new SessionRepository();
  await sessions.ensureSession(sessionId);

  const creds = await loadCreds(sessionId);

  const rawKeys: SignalKeyStore = {
    get: async <T extends SignalCategory>(type: T, ids: string[]) => {
      if (!ids.length) {
        return {} as { [id: string]: SignalDataTypeMap[T] };
      }

      const result = await pool.query(
        `
          SELECT key_id, value_payload
          FROM wa_auth_keys
          WHERE session_id = $1 AND category = $2 AND key_id = ANY($3)
        `,
        [sessionId, type, ids],
      );

      const mapped = {} as { [id: string]: SignalDataTypeMap[T] };
      for (const row of result.rows) {
        mapped[row.key_id] = reviveSignalValue(
          type,
          decodeBaileysPayload<SignalDataTypeMap[T]>(row.value_payload),
        );
      }

      return mapped;
    },
    set: async (data: SignalDataSet) => {
      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        await setSignalData(client, sessionId, data);
        await client.query('COMMIT');
      } catch (error) {
        await client.query('ROLLBACK');
        throw error;
      } finally {
        client.release();
      }
    },
    clear: async () => {
      await pool.query(
        `
          DELETE FROM wa_auth_keys
          WHERE session_id = $1
        `,
        [sessionId],
      );
    },
  };

  const state: AuthenticationState = {
    creds,
    keys: makeCacheableSignalKeyStore(rawKeys, logger),
  };

  return {
    state,
    saveCreds: async () => {
      await pool.query(
        `
          INSERT INTO wa_auth_credentials(session_id, creds_payload)
          VALUES ($1, $2)
          ON CONFLICT (session_id)
          DO UPDATE SET creds_payload = EXCLUDED.creds_payload, updated_at = NOW()
        `,
        [sessionId, encodeBaileysPayload(state.creds)],
      );
    },
    clear: async () => {
      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        await client.query('DELETE FROM wa_auth_keys WHERE session_id = $1', [sessionId]);
        await client.query('DELETE FROM wa_auth_credentials WHERE session_id = $1', [sessionId]);
        await client.query('COMMIT');
      } catch (error) {
        await client.query('ROLLBACK');
        throw error;
      } finally {
        client.release();
      }
    },
  };
}
