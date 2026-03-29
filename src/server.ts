import 'dotenv/config';

import express, { type NextFunction, type Request, type Response } from 'express';
import { z } from 'zod';

import { env } from './config/env.js';
import { runMigrations } from './db/migrate.js';
import { pool } from './db/pool.js';
import { logger } from './lib/logger.js';
import { SessionManager } from './whatsapp/session-manager.js';

const app = express();
const sessions = new SessionManager();

const sessionIdSchema = z.object({
  sessionId: z.string().min(1).max(120).regex(/^[a-zA-Z0-9_-]+$/),
});

const connectBodySchema = z.object({
  mode: z.enum(['qr', 'pairing']).default('qr'),
  phoneNumber: z.string().optional(),
  syncFullHistory: z.boolean().optional(),
});

const pairingBodySchema = z.object({
  phoneNumber: z.string().min(6),
});

const sendTextSchema = z.object({
  to: z.string().min(1),
  text: z.string().min(1),
});

app.use(express.json({ limit: '1mb' }));

function asyncRoute(
  handler: (request: Request, response: Response, next: NextFunction) => Promise<void>,
) {
  return (request: Request, response: Response, next: NextFunction) => {
    handler(request, response, next).catch(next);
  };
}

app.get(
  '/health',
  asyncRoute(async (_request, response) => {
    await pool.query('SELECT 1');
    response.json({
      ok: true,
      database: 'up',
    });
  }),
);

app.post(
  '/api/sessions/:sessionId/connect',
  asyncRoute(async (request, response) => {
    const { sessionId } = sessionIdSchema.parse(request.params);
    const body = connectBodySchema.parse(request.body ?? {});

    if (body.mode === 'pairing' && !body.phoneNumber) {
      response.status(400).json({
        error: 'phoneNumber is required when mode=pairing',
      });
      return;
    }

    const session = await sessions.connect(sessionId, body);
    response.json({
      session,
    });
  }),
);

app.post(
  '/api/sessions/:sessionId/pairing-code',
  asyncRoute(async (request, response) => {
    const { sessionId } = sessionIdSchema.parse(request.params);
    const body = pairingBodySchema.parse(request.body ?? {});
    const result = await sessions.requestPairingCode(sessionId, body.phoneNumber);

    response.json(result);
  }),
);

app.get(
  '/api/sessions/:sessionId/status',
  asyncRoute(async (request, response) => {
    const { sessionId } = sessionIdSchema.parse(request.params);
    const session = await sessions.getStatus(sessionId);

    if (!session) {
      response.status(404).json({
        error: 'Session not found',
      });
      return;
    }

    response.json({
      session,
    });
  }),
);

app.get(
  '/api/sessions/:sessionId/qr',
  asyncRoute(async (request, response) => {
    const { sessionId } = sessionIdSchema.parse(request.params);
    const result = await sessions.getQrCode(sessionId);
    response.json(result);
  }),
);

app.post(
  '/api/sessions/:sessionId/messages/text',
  asyncRoute(async (request, response) => {
    const { sessionId } = sessionIdSchema.parse(request.params);
    const body = sendTextSchema.parse(request.body ?? {});
    const message = await sessions.sendText(sessionId, body.to, body.text);

    response.json({
      ok: true,
      messageId: message.key.id,
      remoteJid: message.key.remoteJid,
      message,
    });
  }),
);

app.post(
  '/api/sessions/:sessionId/disconnect',
  asyncRoute(async (request, response) => {
    const { sessionId } = sessionIdSchema.parse(request.params);
    const session = await sessions.disconnect(sessionId);
    response.json({
      session,
    });
  }),
);

app.post(
  '/api/sessions/:sessionId/logout',
  asyncRoute(async (request, response) => {
    const { sessionId } = sessionIdSchema.parse(request.params);
    const session = await sessions.logout(sessionId);
    response.json({
      session,
    });
  }),
);

app.use((error: unknown, _request: Request, response: Response, _next: NextFunction) => {
  if (error instanceof z.ZodError) {
    response.status(400).json({
      error: 'Validation failed',
      details: error.flatten(),
    });
    return;
  }

  logger.error({ error }, 'Request failed');

  response.status(500).json({
    error: error instanceof Error ? error.message : 'Internal server error',
  });
});

async function bootstrap(): Promise<void> {
  await runMigrations();
  await sessions.restoreSessions();

  app.listen(env.port, () => {
    logger.info({ port: env.port }, 'WhatsApp API server listening');
  });
}

bootstrap().catch((error) => {
  logger.error({ error }, 'Failed to bootstrap application');
  process.exit(1);
});
