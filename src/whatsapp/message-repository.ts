import type { proto, WAMessage, WAMessageKey } from 'baileys';
import type { PoolClient } from 'pg';

import { pool } from '../db/pool.js';
import { decodeBaileysPayload, encodeBaileysPayload } from '../lib/crypto.js';

async function upsertMessage(client: PoolClient, sessionId: string, message: WAMessage): Promise<void> {
  const remoteJid = message.key.remoteJid;
  const messageId = message.key.id;

  if (!remoteJid || !messageId) {
    return;
  }

  await client.query(
    `
      INSERT INTO wa_messages(
        session_id,
        remote_jid,
        message_id,
        participant,
        from_me,
        message_timestamp,
        push_name,
        message_payload
      )
      VALUES($1, $2, $3, $4, $5, $6, $7, $8)
      ON CONFLICT (session_id, remote_jid, message_id)
      DO UPDATE SET
        participant = EXCLUDED.participant,
        from_me = EXCLUDED.from_me,
        message_timestamp = EXCLUDED.message_timestamp,
        push_name = EXCLUDED.push_name,
        message_payload = EXCLUDED.message_payload,
        updated_at = NOW()
    `,
    [
      sessionId,
      remoteJid,
      messageId,
      message.key.participant ?? null,
      Boolean(message.key.fromMe),
      typeof message.messageTimestamp === 'number'
        ? message.messageTimestamp
        : Number(message.messageTimestamp ?? 0),
      message.pushName ?? null,
      encodeBaileysPayload(message),
    ],
  );
}

export class MessageRepository {
  async saveMessages(sessionId: string, messages: WAMessage[]): Promise<void> {
    if (!messages.length) {
      return;
    }

    const client = await pool.connect();

    try {
      await client.query('BEGIN');
      for (const message of messages) {
        await upsertMessage(client, sessionId, message);
      }
      await client.query('COMMIT');
    } catch (error) {
      await client.query('ROLLBACK');
      throw error;
    } finally {
      client.release();
    }
  }

  async getMessage(sessionId: string, key: WAMessageKey): Promise<proto.IMessage | undefined> {
    if (!key.remoteJid || !key.id) {
      return undefined;
    }

    const result = await pool.query(
      `
        SELECT message_payload
        FROM wa_messages
        WHERE session_id = $1 AND remote_jid = $2 AND message_id = $3
      `,
      [sessionId, key.remoteJid, key.id],
    );

    if (!result.rowCount) {
      return undefined;
    }

    const message = decodeBaileysPayload<WAMessage>(result.rows[0].message_payload);
    return message.message ?? undefined;
  }
}
