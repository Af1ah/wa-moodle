import { jidNormalizedUser } from 'baileys';

const KNOWN_SUFFIXES = ['@s.whatsapp.net', '@c.us', '@g.us', '@broadcast', '@newsletter', '@lid'];

export function normalizePhoneNumber(phoneNumber: string): string {
  const digits = phoneNumber.replace(/\D/g, '');

  if (!digits) {
    throw new Error('Phone number must contain digits only');
  }

  return digits;
}

export function normalizeRecipientJid(input: string): string {
  const trimmed = input.trim();

  if (!trimmed) {
    throw new Error('Recipient is required');
  }

  if (KNOWN_SUFFIXES.some((suffix) => trimmed.endsWith(suffix))) {
    return trimmed.endsWith('@c.us') ? jidNormalizedUser(trimmed) : trimmed;
  }

  return `${normalizePhoneNumber(trimmed)}@s.whatsapp.net`;
}
