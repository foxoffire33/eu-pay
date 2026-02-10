# Zero-Knowledge Encryption Architecture

## Principle

> The backend is a **blind vault**: it stores data it cannot read.
> Only the user's Android device holds the private key.

```
┌─────────────────┐                    ┌──────────────────────┐
│   Android App   │                    │   Symfony Backend     │
│                 │                    │                      │
│ ┌─────────────┐ │   public key       │ ┌──────────────────┐ │
│ │ Android     │ │ ──────────────────▶│ │ User public key  │ │
│ │ Keystore    │ │                    │ │ (stored in DB)   │ │
│ │             │ │                    │ └──────────────────┘ │
│ │ Private Key │ │                    │                      │
│ │ (NEVER      │ │   encrypted data   │ ┌──────────────────┐ │
│ │  leaves     │ │ ◀────────────────  │ │ Encrypted PII    │ │
│ │  device)    │ │                    │ │ (opaque blobs)   │ │
│ └─────────────┘ │                    │ └──────────────────┘ │
│                 │                    │                      │
│ Decrypts data   │                    │ CAN'T decrypt        │
│ locally         │                    │ anything             │
└─────────────────┘                    └──────────────────────┘
```

## Cryptographic Primitives

| Purpose                | Algorithm              | Where          |
|------------------------|------------------------|----------------|
| Asymmetric keypair     | RSA-4096 OAEP SHA-256  | Android Keystore |
| Envelope data key      | AES-256-GCM            | Backend (random per record) |
| Data key encryption    | RSA-OAEP (user pubkey) | Backend        |
| Blind index (search)   | HMAC-SHA256            | Backend        |
| Blind index key        | Derived from server secret | Backend     |

## How It Works

### Envelope Encryption (for each piece of PII)

RSA can only encrypt ~446 bytes (4096-bit key with OAEP). So we use **envelope encryption**:

1. Backend generates a random AES-256 key (the "data encryption key" / DEK)
2. Backend encrypts the plaintext with AES-256-GCM using the DEK
3. Backend encrypts the DEK with the user's RSA public key (RSA-OAEP)
4. Backend stores: `encrypted_dek || iv || auth_tag || ciphertext`
5. Backend **discards** the plaintext DEK — it can never recover it

### Decryption (Android only)

1. Android receives the encrypted blob
2. Extracts the encrypted DEK (first 512 bytes for RSA-4096)
3. Decrypts DEK using private key in Android Keystore (never exportable)
4. Decrypts the ciphertext with AES-256-GCM using the recovered DEK
5. Returns plaintext

### Blind Indexes (for searchable fields)

The backend can't read encrypted email, so how does login work?

**Blind index** = `HMAC-SHA256(server_secret, lowercase(email))`

- Deterministic: same email always produces same index
- One-way: can't reverse the HMAC to get the email
- The backend stores the blind index alongside the encrypted email
- Login: compute `HMAC(email)` → find matching row → return encrypted data to client

## Field-Level Encryption Map

| Field          | Encrypted? | Blind Index? | Rationale                           |
|----------------|-----------|-------------|-------------------------------------|
| email          | ✅ Yes     | ✅ Yes       | PII — need login lookup             |
| first_name     | ✅ Yes     | ❌ No        | PII — no search needed              |
| last_name      | ✅ Yes     | ❌ No        | PII — no search needed              |
| phone_number   | ✅ Yes     | ❌ No        | PII — no search needed              |
| iban           | ✅ Yes     | ❌ No        | Financial — highly sensitive         |
| password_hash  | ❌ No      | N/A         | Already hashed — needed for auth     |
| external_person_id | ❌ No   | ❌ No        | PSD2 external reference (not PII)|
| kyc_status     | ❌ No      | ❌ No        | Operational — not PII               |
| dpan           | ✅ Yes     | ❌ No        | Card token — already AES encrypted  |
| session_key    | ✅ Yes     | ❌ No        | Crypto key — already AES encrypted  |
| tx amount      | ✅ Yes     | ❌ No        | Financial data                      |
| tx merchant    | ✅ Yes     | ❌ No        | Financial data                      |

## Key Lifecycle

- **Key generation**: On first app launch or account creation
- **Key storage**: Android Keystore (hardware-backed when available)
- **Key backup**: User prompted to save encrypted recovery key (optional)
- **Key rotation**: User can re-encrypt with new keypair (triggers full re-encryption)
- **Key loss**: Without recovery key, data is permanently unrecoverable (by design)

## Limitations

1. **Server-side search** is limited to blind-indexed fields only
2. **PSD2 API calls** require plaintext — the Android app sends data to backend
   for PSD2 calls as ephemeral (not stored)
3. **Data loss risk** — if user loses device AND recovery key, all data is gone
4. **Performance** — RSA decryption on every data read adds ~10ms per field
5. **Backend analytics** — impossible on encrypted fields (this is a feature, not a bug)
