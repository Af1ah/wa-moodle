import makeWASocket, {
  Browsers,
  DisconnectReason,
  type ConnectionState,
  type GroupMetadata,
  type WAMessage,
} from 'baileys';
import QRCode from 'qrcode';

import { logger } from '../lib/logger.js';
import { TTLCache } from '../lib/ttl-cache.js';
import { createPostgresAuthState } from './auth-state.js';
import { normalizePhoneNumber, normalizeRecipientJid } from './jid.js';
import { MessageRepository } from './message-repository.js';
import {
  SessionRepository,
  type SessionMode,
  type SessionRecord,
  type SessionStatus,
} from './session-repository.js';

const RECONNECTABLE_REASONS = new Set<number>([
  DisconnectReason.connectionClosed,
  DisconnectReason.connectionLost,
  DisconnectReason.restartRequired,
  DisconnectReason.timedOut,
  DisconnectReason.unavailableService,
]);

type ConnectOptions = {
  mode?: SessionMode;
  phoneNumber?: string;
  syncFullHistory?: boolean;
};

type RuntimeSession = {
  auth: Awaited<ReturnType<typeof createPostgresAuthState>>;
  connecting?: Promise<SessionRecord>;
  groupCache: TTLCache<GroupMetadata>;
  mode: SessionMode;
  pairingCodeRequested: boolean;
  pairingRequestInFlight: boolean;
  phoneNumber?: string;
  reconnectTimer?: NodeJS.Timeout;
  shouldReconnect: boolean;
  socket?: ReturnType<typeof makeWASocket>;
  syncFullHistory: boolean;
};

function resolveBrowser(mode: SessionMode, syncFullHistory: boolean): [string, string, string] {
  if (mode === 'pairing') {
    return Browsers.macOS('Google Chrome');
  }

  if (syncFullHistory) {
    return Browsers.macOS('Desktop');
  }

  return Browsers.ubuntu('Chrome');
}

export class SessionManager {
  private readonly sessions = new SessionRepository();

  private readonly messages = new MessageRepository();

  private readonly runtimes = new Map<string, RuntimeSession>();

  async restoreSessions(): Promise<void> {
    const existing = await this.sessions.listReconnectableSessions();

    await Promise.all(
      existing.map((session) =>
        this.connect(session.sessionId, {
          mode: session.mode ?? 'qr',
          phoneNumber: session.phoneNumber ?? undefined,
        }).catch((error) => {
          logger.error({ error, sessionId: session.sessionId }, 'Failed to restore session');
        }),
      ),
    );
  }

  async connect(sessionId: string, options: ConnectOptions = {}): Promise<SessionRecord> {
    let runtime = this.runtimes.get(sessionId);

    if (!runtime) {
      runtime = await this.bootstrapRuntime(sessionId, options);
      this.runtimes.set(sessionId, runtime);
    }

    if (runtime.connecting) {
      return runtime.connecting;
    }

    const pending = this.createOrReplaceSocket(sessionId, options).finally(() => {
      const currentRuntime = this.runtimes.get(sessionId);
      if (currentRuntime) {
        currentRuntime.connecting = undefined;
      }
    });

    runtime.connecting = pending;
    return pending;
  }

  async requestPairingCode(sessionId: string, phoneNumber: string): Promise<{
    pairingCode: string;
    session: SessionRecord;
  }> {
    await this.connect(sessionId, {
      mode: 'pairing',
      phoneNumber,
    });

    const normalizedPhoneNumber = normalizePhoneNumber(phoneNumber);
    await this.sessions.patch(sessionId, {
      mode: 'pairing',
      phoneNumber: normalizedPhoneNumber,
    });

    const deadline = Date.now() + 30_000;
    while (Date.now() < deadline) {
      const session = await this.sessions.get(sessionId);
      if (session?.pairingCode) {
        return {
          pairingCode: session.pairingCode,
          session,
        };
      }

      await new Promise((resolve) => setTimeout(resolve, 400));
    }

    throw new Error('Timed out while waiting for a pairing code');
  }

  async getStatus(sessionId: string): Promise<SessionRecord | null> {
    return this.sessions.get(sessionId);
  }

  async getQrCode(sessionId: string): Promise<{
    qr: string;
    qrDataUrl: string;
    session: SessionRecord;
  }> {
    const session = await this.sessions.get(sessionId);
    if (!session?.qr) {
      throw new Error('No QR code is currently available for this session');
    }

    return {
      qr: session.qr,
      qrDataUrl: await QRCode.toDataURL(session.qr),
      session,
    };
  }

  async sendText(sessionId: string, to: string, text: string): Promise<WAMessage> {
    const runtime = this.runtimes.get(sessionId);
    const status = await this.sessions.get(sessionId);

    if (!runtime?.socket || status?.status !== 'open') {
      throw new Error('Session is not connected');
    }

    const jid = normalizeRecipientJid(to);
    const message = await runtime.socket.sendMessage(jid, { text });
    if (!message) {
      throw new Error('Baileys did not return a message payload after sendMessage');
    }

    await this.messages.saveMessages(sessionId, [message]);
    return message;
  }

  async disconnect(sessionId: string): Promise<SessionRecord> {
    const runtime = this.runtimes.get(sessionId);
    if (runtime?.reconnectTimer) {
      clearTimeout(runtime.reconnectTimer);
    }

    if (runtime) {
      runtime.shouldReconnect = false;
      if (runtime.socket) {
        await runtime.socket.end(new Error('Manual disconnect'));
      }
      this.runtimes.delete(sessionId);
    }

    return this.sessions.patch(sessionId, {
      status: 'disconnected',
      qr: null,
      pairingCode: null,
      lastErrorCode: null,
      lastErrorMessage: null,
    });
  }

  async logout(sessionId: string): Promise<SessionRecord> {
    const runtime = this.runtimes.get(sessionId);
    if (runtime?.reconnectTimer) {
      clearTimeout(runtime.reconnectTimer);
    }

    if (runtime) {
      runtime.shouldReconnect = false;
      if (runtime.socket) {
        await runtime.socket.logout();
      }
      await runtime.auth.clear();
      this.runtimes.delete(sessionId);
    }

    await this.sessions.ensureSession(sessionId);
    return this.sessions.patch(sessionId, {
      status: 'logged_out',
      qr: null,
      pairingCode: null,
      lastErrorCode: null,
      lastErrorMessage: null,
      meJid: null,
      mode: null,
      phoneNumber: null,
    });
  }

  private async bootstrapRuntime(sessionId: string, options: ConnectOptions): Promise<RuntimeSession> {
    const auth = await createPostgresAuthState(sessionId, logger.child({ scope: 'auth', sessionId }));
    const groupCache = new TTLCache<GroupMetadata>(5 * 60_000);

    const runtime: RuntimeSession = {
      auth,
      groupCache,
      mode: options.mode ?? 'qr',
      pairingCodeRequested: false,
      phoneNumber: options.phoneNumber ? normalizePhoneNumber(options.phoneNumber) : undefined,
      pairingRequestInFlight: false,
      shouldReconnect: true,
      syncFullHistory: options.syncFullHistory ?? false,
    };

    return runtime;
  }

  private async createOrReplaceSocket(
    sessionId: string,
    options: ConnectOptions,
  ): Promise<SessionRecord> {
    const existing = this.runtimes.get(sessionId);
    const runtime = existing ?? (await this.bootstrapRuntime(sessionId, options));

    if (existing?.reconnectTimer) {
      clearTimeout(existing.reconnectTimer);
      existing.reconnectTimer = undefined;
    }

    runtime.mode = options.mode ?? runtime.mode ?? 'qr';
    runtime.phoneNumber = options.phoneNumber
      ? normalizePhoneNumber(options.phoneNumber)
      : runtime.phoneNumber;
    runtime.pairingCodeRequested = false;
    runtime.syncFullHistory = options.syncFullHistory ?? runtime.syncFullHistory;
    runtime.shouldReconnect = true;
    runtime.pairingRequestInFlight = false;

    if (existing?.socket) {
      existing.socket.end(new Error('Session replaced'));
    }

    runtime.socket = this.makeSocket(sessionId, runtime);

    return this.sessions.patch(sessionId, {
      status: 'connecting',
      mode: runtime.mode,
      phoneNumber: runtime.phoneNumber ?? null,
      qr: null,
      pairingCode: null,
      lastErrorCode: null,
      lastErrorMessage: null,
    });
  }

  private makeSocket(sessionId: string, runtime: RuntimeSession): ReturnType<typeof makeWASocket> {
    const childLogger = logger.child({ sessionId, scope: 'baileys' });

    const socket = makeWASocket({
      auth: runtime.auth.state,
      browser: resolveBrowser(runtime.mode, runtime.syncFullHistory),
      cachedGroupMetadata: async (jid) => {
        const cached = runtime.groupCache.get(jid);
        if (cached) {
          return cached;
        }

        const metadata = await socket.groupMetadata(jid);
        runtime.groupCache.set(jid, metadata);
        return metadata;
      },
      getMessage: async (key) => this.messages.getMessage(sessionId, key),
      logger: childLogger,
      markOnlineOnConnect: false,
      printQRInTerminal: false,
      syncFullHistory: runtime.syncFullHistory,
    });

    socket.ev.on('creds.update', runtime.auth.saveCreds);

    socket.ev.on('connection.update', async (update) => {
      try {
        await this.handleConnectionUpdate(sessionId, runtime, socket, update);
      } catch (error) {
        logger.error({ error, sessionId }, 'Unhandled connection.update failure');
        await this.sessions.patch(sessionId, {
          status: 'error',
          lastErrorMessage: error instanceof Error ? error.message : 'connection.update failed',
        });
      }
    });

    socket.ev.on('messages.upsert', async ({ messages }) => {
      try {
        await this.messages.saveMessages(sessionId, messages);
      } catch (error) {
        logger.error({ error, sessionId }, 'Failed to persist upserted messages');
      }
    });

    socket.ev.on('messaging-history.set', async ({ messages }) => {
      try {
        await this.messages.saveMessages(sessionId, messages as WAMessage[]);
      } catch (error) {
        logger.error({ error, sessionId }, 'Failed to persist message history');
      }
    });

    socket.ev.on('groups.update', async (updates) => {
      for (const update of updates) {
        if (update.id) {
          runtime.groupCache.delete(update.id);
        }
      }
    });

    socket.ev.on('group-participants.update', async (update) => {
      runtime.groupCache.delete(update.id);
    });

    return socket;
  }

  private async handleConnectionUpdate(
    sessionId: string,
    runtime: RuntimeSession,
    socket: ReturnType<typeof makeWASocket>,
    update: Partial<ConnectionState>,
  ): Promise<void> {
    if (runtime.socket !== socket) {
      return;
    }

    if (update.qr) {
      await this.sessions.patch(sessionId, {
        status: 'qr_ready',
        qr: update.qr,
        pairingCode: null,
      });
    }

    if (
      runtime.mode === 'pairing' &&
      runtime.phoneNumber &&
      !runtime.auth.state.creds.registered &&
      !runtime.pairingCodeRequested &&
      !runtime.pairingRequestInFlight &&
      update.connection === 'open'
    ) {
      await this.requestPairingCodeForSocket(sessionId, runtime, socket);
    }

    if (update.connection === 'open') {
      await this.sessions.patch(sessionId, {
        status: 'open',
        meJid: socket.user?.id ?? null,
        qr: null,
        pairingCode: null,
        lastConnectedAt: new Date(),
        lastErrorCode: null,
        lastErrorMessage: null,
      });
      return;
    }

    if (update.connection === 'connecting') {
      await this.sessions.patch(sessionId, {
        status: 'connecting',
        lastErrorCode: null,
        lastErrorMessage: null,
      });
      return;
    }

    if (update.connection !== 'close') {
      return;
    }

    const error = update.lastDisconnect?.error as {
      message?: string;
      output?: {
        statusCode?: number;
      };
    } | undefined;

    const statusCode = error?.output?.statusCode;

    if (!runtime.shouldReconnect) {
      await this.sessions.patch(sessionId, {
        status: 'disconnected',
        qr: null,
        pairingCode: null,
      });
      return;
    }

    if (statusCode === DisconnectReason.loggedOut || statusCode === DisconnectReason.badSession) {
      await runtime.auth.clear();
      this.runtimes.delete(sessionId);
      await this.sessions.patch(sessionId, {
        status: 'logged_out',
        qr: null,
        pairingCode: null,
        meJid: null,
        lastErrorCode: statusCode ?? null,
        lastErrorMessage: error?.message ?? 'Logged out from WhatsApp',
      });
      return;
    }

    if (statusCode && RECONNECTABLE_REASONS.has(statusCode)) {
      await this.sessions.patch(sessionId, {
        status: statusCode === DisconnectReason.restartRequired ? 'restarting' : 'disconnected',
        qr: null,
        pairingCode: null,
        lastErrorCode: statusCode,
        lastErrorMessage: error?.message ?? null,
      });

      runtime.reconnectTimer = setTimeout(() => {
        runtime.connecting = undefined;
        this.connect(sessionId, {
          mode: runtime.mode,
          phoneNumber: runtime.phoneNumber,
          syncFullHistory: runtime.syncFullHistory,
        }).catch((reconnectError) => {
          logger.error({ reconnectError, sessionId }, 'Failed to reconnect session');
        });
      }, statusCode === DisconnectReason.restartRequired ? 250 : 2_000);
      return;
    }

    this.runtimes.delete(sessionId);
    await this.sessions.patch(sessionId, {
      status: 'error',
      qr: null,
      pairingCode: null,
      lastErrorCode: statusCode ?? null,
      lastErrorMessage: error?.message ?? 'Connection closed unexpectedly',
    });
  }

  private async requestPairingCodeForSocket(
    sessionId: string,
    runtime: RuntimeSession,
    socket: ReturnType<typeof makeWASocket>,
  ): Promise<void> {
    runtime.pairingRequestInFlight = true;

    try {
      const maxAttempts = 4;

      for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
        if (runtime.socket !== socket || runtime.auth.state.creds.registered) {
          return;
        }

        try {
          const pairingCode = await socket.requestPairingCode(runtime.phoneNumber!);
          runtime.pairingCodeRequested = true;
          await this.sessions.patch(sessionId, {
            status: 'pairing_code_ready',
            pairingCode,
            qr: null,
            lastErrorCode: null,
            lastErrorMessage: null,
          });
          return;
        } catch (error) {
          const statusCode =
            typeof error === 'object' && error && 'output' in error
              ? (error as { output?: { statusCode?: number } }).output?.statusCode
              : undefined;

          if (attempt === maxAttempts || statusCode !== DisconnectReason.connectionClosed) {
            throw error;
          }

          await new Promise((resolve) => setTimeout(resolve, 1_000 * attempt));
        }
      }
    } catch (error) {
      logger.warn({ error, sessionId }, 'Failed to obtain pairing code');
      await this.sessions.patch(sessionId, {
        status: 'error',
        lastErrorCode:
          typeof error === 'object' && error && 'output' in error
            ? ((error as { output?: { statusCode?: number } }).output?.statusCode ?? null)
            : null,
        lastErrorMessage: error instanceof Error ? error.message : 'Failed to obtain pairing code',
      });
    } finally {
      runtime.pairingRequestInFlight = false;
    }
  }
}
