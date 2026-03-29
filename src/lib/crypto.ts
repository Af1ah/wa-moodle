import { createCipheriv, createDecipheriv, createHash, randomBytes } from 'node:crypto';

import { BufferJSON } from 'baileys';

import { env } from '../config/env.js';

const ENCRYPTION_PREFIX = 'enc:v1';
const PLAINTEXT_PREFIX = 'plain:v1';
const IV_LENGTH = 12;

function resolveKey(raw: string | undefined): Buffer | undefined {
  if (!raw) {
    return undefined;
  }

  if (/^[0-9a-f]{64}$/i.test(raw)) {
    return Buffer.from(raw, 'hex');
  }

  const base64 = Buffer.from(raw, 'base64');
  if (base64.length === 32) {
    return base64;
  }

  const utf8 = Buffer.from(raw, 'utf8');
  if (utf8.length === 32) {
    return utf8;
  }

  return createHash('sha256').update(raw).digest();
}

const encryptionKey = resolveKey(env.authEncryptionKey);

function encryptString(value: string): string {
  if (!encryptionKey) {
    return `${PLAINTEXT_PREFIX}:${value}`;
  }

  const iv = randomBytes(IV_LENGTH);
  const cipher = createCipheriv('aes-256-gcm', encryptionKey, iv);
  const encrypted = Buffer.concat([cipher.update(value, 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();

  return [
    ENCRYPTION_PREFIX,
    iv.toString('base64'),
    tag.toString('base64'),
    encrypted.toString('base64'),
  ].join(':');
}

function decryptString(value: string): string {
  if (value.startsWith(`${PLAINTEXT_PREFIX}:`)) {
    return value.slice(PLAINTEXT_PREFIX.length + 1);
  }

  if (!value.startsWith(`${ENCRYPTION_PREFIX}:`)) {
    return value;
  }

  if (!encryptionKey) {
    throw new Error(
      'Encountered encrypted auth data but BAILEYS_AUTH_ENCRYPTION_KEY is not configured',
    );
  }

  const [, ivBase64, tagBase64, encryptedBase64] = value.split(':');

  const decipher = createDecipheriv(
    'aes-256-gcm',
    encryptionKey,
    Buffer.from(ivBase64, 'base64'),
  );
  decipher.setAuthTag(Buffer.from(tagBase64, 'base64'));

  const decrypted = Buffer.concat([
    decipher.update(Buffer.from(encryptedBase64, 'base64')),
    decipher.final(),
  ]);

  return decrypted.toString('utf8');
}

export function encodeBaileysPayload(value: unknown): string {
  return encryptString(JSON.stringify(value, BufferJSON.replacer));
}

export function decodeBaileysPayload<T>(value: string): T {
  return JSON.parse(decryptString(value), BufferJSON.reviver) as T;
}
