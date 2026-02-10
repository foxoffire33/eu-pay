export interface Feature {
  icon: string;
  title: string;
  description: string;
}

export const features: Feature[] = [
  {
    icon: 'nfc',
    title: 'NFC Tap-to-Pay',
    description:
      'Pay at any contactless terminal with your Android phone. Direct HCE — no Google Pay or Apple Pay needed.',
  },
  {
    icon: 'bank',
    title: '140+ EU Banks',
    description:
      'Top up from any EU/EEA bank via PSD2 Open Banking. iDEAL, SEPA, and instant payments supported.',
  },
  {
    icon: 'shield',
    title: 'Zero-Knowledge Encryption',
    description:
      'RSA-4096 + AES-256-GCM envelope encryption. The server never sees your personal data in plaintext.',
  },
  {
    icon: 'fingerprint',
    title: 'Passkey Login',
    description:
      'No passwords. Sign in with fingerprint, face, or a physical security key (YubiKey). WebAuthn / FIDO2.',
  },
  {
    icon: 'transfer',
    title: 'P2P Transfers',
    description:
      'Send money to any EU Pay user instantly, or to any EU/EEA IBAN via SEPA. Free and borderless.',
  },
  {
    icon: 'euro',
    title: 'Digital Euro Ready',
    description:
      'Prepared for the ECB central bank digital currency (2029). Three payment rails: PSD2, card, digital euro.',
  },
];
