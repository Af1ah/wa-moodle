# wa-moodle

Moodle WhatsApp notification plugin plus a shared secure backend broker.

## What stays in this repo

- `backend`
- `moodle/message/output/wamoodle`

The Moodle plugin:

- adds WhatsApp as a Moodle message output
- stores queue and sender-session state in Moodle tables
- sends notifications through the shared backend broker
- uses Moodle adhoc tasks for retries and background delivery
- keeps local queue and retry logic
- shows broker and sender status without exposing provider controls

The backend:

- manages clients, sites, entitlements, secrets, expiries, and audit logs
- verifies plugin licenses with signed requests
- brokers Evolution status, groups, and message sends

Developer-facing integration notes live at `backend/DEVELOPER_NOTES.md`.

## Install

1. Copy `moodle/message/output/wamoodle` into your Moodle codebase at:
   `message/output/wamoodle`
2. Run Moodle upgrade.
3. Purge caches.

## Configure

Open:

- `http://localhost/admin/settings.php?section=messagesettingwamoodle`

Set:

- Broker backend base URL
- Plugin code
- Client key
- Plugin secret
- Sender session ID
- Default country code
- Primary Moodle mobile field

Use the status page to inspect license and sender state:

- `http://localhost/message/output/wamoodle/status.php`

QR login, pairing, provider credentials, client provisioning, and expiry control are handled from the shared backend dashboard.
