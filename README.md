# wa-moodle

Moodle WhatsApp notification plugin with direct Evolution API integration.

## What stays in this repo

- `moodle/message/output/wamoodle`

This plugin:

- adds WhatsApp as a Moodle message output
- stores queue and sender-session state in Moodle tables
- sends notifications through Evolution API directly
- uses Moodle adhoc tasks for retries and background delivery
- provides admin tools for QR login, pairing code login, sender status, and test sends

## Install

1. Copy `moodle/message/output/wamoodle` into your Moodle codebase at:
   `message/output/wamoodle`
2. Run Moodle upgrade.
3. Purge caches.

## Configure

Open:

- `http://localhost/admin/settings.php?section=messagesettingwamoodle`

Set:

- Evolution API base URL
- Evolution API key
- Sender session ID
- Default country code
- Primary Moodle mobile field

Use the status page to manage the sender session:

- `http://localhost/message/output/wamoodle/status.php`

## Sender session flow

From the plugin status page you can:

- refresh sender session state
- start QR login
- request a pairing code with a phone number
- send a test message

The plugin stores sender session state in Moodle and sends queued notifications through the configured Evolution sender session.
