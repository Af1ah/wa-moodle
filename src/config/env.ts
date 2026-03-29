import { z } from 'zod';

const envSchema = z.object({
  NODE_ENV: z.enum(['development', 'test', 'production']).default('development'),
  PORT: z.coerce.number().int().positive().default(3000),
  NEXT_DB_URL: z.string().url().optional(),
  DATABASE_URL: z.string().url().optional(),
  BAILEYS_AUTH_ENCRYPTION_KEY: z.string().optional(),
});

const parsed = envSchema.parse(process.env);

const databaseUrl = parsed.NEXT_DB_URL ?? parsed.DATABASE_URL;

if (!databaseUrl) {
  throw new Error('Missing NEXT_DB_URL or DATABASE_URL in environment');
}

export const env = {
  nodeEnv: parsed.NODE_ENV,
  port: parsed.PORT,
  databaseUrl,
  authEncryptionKey: parsed.BAILEYS_AUTH_ENCRYPTION_KEY,
};
