# wa-moodle

Postgres-backed WhatsApp session service built with Baileys.

## What it does

- Persists Baileys auth creds and signal keys in PostgreSQL instead of local files.
- Stores sent/received messages so `getMessage()` works for retries.
- Handles QR login, pairing-code login, reconnects, and logout flows.
- Exposes REST endpoints for connection and messaging operations.

## Setup

1. Copy `.env.example` to `.env`.
2. Set `NEXT_DB_URL` to a working PostgreSQL connection string.
3. Optionally set `BAILEYS_AUTH_ENCRYPTION_KEY` to encrypt stored auth payloads.
4. Install dependencies with `npm install`.
5. Start the server with `npm run dev` or `npm start`.

## API

### `POST /api/sessions/:sessionId/connect`

Start or resume a session.

```json
{
  "mode": "qr",
  "syncFullHistory": false
}
```

For pairing login:

```json
{
  "mode": "pairing",
  "phoneNumber": "919999999999"
}
```

### `POST /api/sessions/:sessionId/pairing-code`

Generate a pairing code.

```json
{
  "phoneNumber": "919999999999"
}
```

### `GET /api/sessions/:sessionId/status`

Get the current persisted session state.

### `GET /api/sessions/:sessionId/qr`

Get the raw QR string plus a data URL for frontend rendering.

### `POST /api/sessions/:sessionId/messages/text`

Send a plain text message.

```json
{
  "to": "919999999999",
  "text": "Hello from Baileys"
}
```

The `to` value also accepts full JIDs like `1234567890@g.us`.

### `POST /api/sessions/:sessionId/disconnect`

Disconnect while keeping stored auth for later reconnects.

### `POST /api/sessions/:sessionId/logout`

Log out and delete stored auth material for the session.

## Notes

- This service creates its own tables on startup.
- Existing sessions with stored creds are automatically restored on boot.
- Group metadata is cached in memory to avoid unnecessary fetches during sends.
