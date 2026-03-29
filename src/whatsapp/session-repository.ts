import type { QueryResultRow } from 'pg';

import { pool } from '../db/pool.js';

export type SessionStatus =
  | 'idle'
  | 'connecting'
  | 'qr_ready'
  | 'pairing_code_ready'
  | 'open'
  | 'disconnected'
  | 'logged_out'
  | 'error'
  | 'restarting';

export type SessionMode = 'qr' | 'pairing';

export type SessionRecord = {
  sessionId: string;
  status: SessionStatus;
  mode: SessionMode | null;
  phoneNumber: string | null;
  meJid: string | null;
  qr: string | null;
  pairingCode: string | null;
  lastConnectedAt: string | null;
  lastErrorCode: number | null;
  lastErrorMessage: string | null;
  createdAt: string;
  updatedAt: string;
};

type SessionPatch = Partial<{
  status: SessionStatus;
  mode: SessionMode | null;
  phoneNumber: string | null;
  meJid: string | null;
  qr: string | null;
  pairingCode: string | null;
  lastConnectedAt: Date | null;
  lastErrorCode: number | null;
  lastErrorMessage: string | null;
}>;

function mapRow(row: QueryResultRow): SessionRecord {
  return {
    sessionId: row.session_id,
    status: row.status,
    mode: row.mode,
    phoneNumber: row.phone_number,
    meJid: row.me_jid,
    qr: row.qr,
    pairingCode: row.pairing_code,
    lastConnectedAt: row.last_connected_at?.toISOString?.() ?? row.last_connected_at ?? null,
    lastErrorCode: row.last_error_code,
    lastErrorMessage: row.last_error_message,
    createdAt: row.created_at.toISOString?.() ?? row.created_at,
    updatedAt: row.updated_at.toISOString?.() ?? row.updated_at,
  };
}

function toColumnName(field: keyof SessionPatch): string {
  const map: Record<keyof SessionPatch, string> = {
    status: 'status',
    mode: 'mode',
    phoneNumber: 'phone_number',
    meJid: 'me_jid',
    qr: 'qr',
    pairingCode: 'pairing_code',
    lastConnectedAt: 'last_connected_at',
    lastErrorCode: 'last_error_code',
    lastErrorMessage: 'last_error_message',
  };

  return map[field];
}

export class SessionRepository {
  async ensureSession(sessionId: string): Promise<void> {
    await pool.query(
      `
        INSERT INTO wa_sessions(session_id)
        VALUES ($1)
        ON CONFLICT (session_id) DO NOTHING
      `,
      [sessionId],
    );
  }

  async get(sessionId: string): Promise<SessionRecord | null> {
    const result = await pool.query(
      `
        SELECT *
        FROM wa_sessions
        WHERE session_id = $1
      `,
      [sessionId],
    );

    return result.rowCount ? mapRow(result.rows[0]) : null;
  }

  async patch(sessionId: string, patch: SessionPatch): Promise<SessionRecord> {
    await this.ensureSession(sessionId);

    const entries = Object.entries(patch) as Array<[keyof SessionPatch, SessionPatch[keyof SessionPatch]]>;
    if (!entries.length) {
      const existing = await this.get(sessionId);
      if (!existing) {
        throw new Error(`Session ${sessionId} not found`);
      }

      return existing;
    }

    const values: unknown[] = [sessionId];
    const assignments = entries.map(([key, value], index) => {
      values.push(value);
      return `${toColumnName(key)} = $${index + 2}`;
    });

    values.push(new Date());

    const result = await pool.query(
      `
        UPDATE wa_sessions
        SET ${assignments.join(', ')}, updated_at = $${values.length}
        WHERE session_id = $1
        RETURNING *
      `,
      values,
    );

    return mapRow(result.rows[0]);
  }

  async listReconnectableSessions(): Promise<SessionRecord[]> {
    const result = await pool.query(`
      SELECT s.*
      FROM wa_sessions s
      INNER JOIN wa_auth_credentials c ON c.session_id = s.session_id
      WHERE s.status <> 'logged_out'
        AND NOT (s.mode = 'pairing' AND s.me_jid IS NULL)
      ORDER BY s.created_at ASC
    `);

    return result.rows.map(mapRow);
  }
}
