# EU Pay

> **📄 [Pitch Deck →](PITCH_DECK.md)** | **Stichting EU Pay** — Dutch foundation (KVK) for European payment sovereignty

NFC tap-to-pay for the European market — no Google Pay, no Apple Pay. Direct card-present payments via Android HCE (Host Card Emulation), powered by PSD2 Open Banking + 17 EU-licensed card issuers + Digital Euro readiness (ECB 2029).

Built with Symfony 8 + PHP 8.4 backend and Kotlin Android app. Zero-knowledge encryption architecture: the server never sees plaintext personal data.

### Legal Entity

| | |
|---|---|
| **Entity** | Stichting EU Pay |
| **Type** | Stichting (Dutch foundation with rechtspersoonlijkheid) |
| **Registry** | Kamer van Koophandel (KVK), Netherlands |
| **Tax status** | ANBI eligible (algemeen nut beogende instelling) |
| **License** | EUPL-1.2 (European Union Public Licence) |
| **Mission** | Open-source European payment infrastructure |

---

## Quick Start (Docker)

```bash
# 1. Install mkcert (one-time)
brew install mkcert    # macOS
# or: sudo apt install mkcert   # Ubuntu
# or: choco install mkcert      # Windows

# 2. Clone and setup
git clone https://github.com/user/eu-pay.git
cd eu-pay
make setup             # generates certs, builds images, installs deps, runs migrations

# 3. Open
open https://eupay.localhost
```

Or step-by-step:

```bash
make certs             # generate HTTPS certificates
make build             # build Docker images
make up                # start PHP + Nginx + PostgreSQL + Redis
make install           # composer install
make jwt-keys          # generate RS256 JWT keypair
make migrate           # run database migrations
make test              # run PHPUnit
```

Add to `/etc/hosts`:

```
127.0.0.1  eupay.localhost api.eupay.localhost
```

---

## Architecture

```
┌──────────────┐       HTTPS/JSON        ┌──────────────────┐       REST        ┌────────────────┐
│  Android App │ ◄──────────────────────► │  Symfony Backend  │ ◄──────────────► │ PSD2 Open Banking│
│  (Kotlin)    │                          │  (PHP 8.4)        │                  │  (PSD2 NextGenPSD2)     │
│              │                          │                    │                  │                 │
│ ┌──────────┐ │   encrypted PII blobs    │ ┌──────────────┐  │   KYC, cards,    │  German banking │
│ │ HCE NFC  │ │ ─────────────────────►   │ │ Encrypted DB │  │   accounts,      │  license        │
│ │ Service  │ │                          │ │ (PostgreSQL) │  │   transactions   │  (PSD2 banks  │
│ ├──────────┤ │   blind indexes          │ ├──────────────┤  │                  │   SE)           │
│ │ RSA-4096 │ │ ─────────────────────►   │ │ HMAC-SHA256  │  │   webhooks ────► │                 │
│ │ Keystore │ │                          │ │ Blind Index  │  │                  │                 │
│ └──────────┘ │                          │ └──────────────┘  │                  │                 │
└──────────────┘                          └──────────────────┘                  └────────────────┘
```

**Key design decisions:**

- **No Google Pay / Apple Pay dependency.** Direct HCE with EMV contactless flow — the app *is* the payment terminal interface.
- **Zero-knowledge encryption.** RSA-4096 OAEP + AES-256-GCM envelope encryption. Backend stores only encrypted blobs + public keys. Client decrypts locally with private key in Android Keystore.
- **Blind indexes.** HMAC-SHA256 over normalized inputs enables login and search without revealing plaintext to the server.
- **EU-only, by design.** GDPR Art. 6/7/13/14/15/17/20, ePrivacy Directive Art. 5(3), PSD2 SCA, Consumer Rights Directive, AML 5-year retention, German TMG/HGB requirements.
- **UUIDv6** for all primary keys (time-sortable, no sequential leaks).

---

## Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend framework | Symfony | 8.0 |
| Backend language | PHP | ≥ 8.4 |
| ORM | Doctrine ORM / DBAL | 3.3 / 4.2 |
| Auth | LexikJWT (RS256) | 3.1 |
| Tests (backend) | PHPUnit | 11.5 |
| Android language | Kotlin | 2.1.21 |
| UI toolkit | Jetpack Compose | BOM 2026.01.01 |
| DI | Hilt + KSP | 2.56.2 |
| Android target | API 35 | Android 15 |
| Android min | API 26 | Android 8.0 |
| Build system | Gradle + AGP | 8.11.1 / 8.10.1 |
| Networking | Retrofit 2 + OkHttp 4 | 2.11 / 4.12 |
| CI/CD | GitHub Actions | JDK 21, PHP 8.4 |
| Database | PostgreSQL | 16 |
| Cache | Redis | 7 |
| Web server | Nginx | 1.27 |
| HTTPS (local) | mkcert | latest |
| Containers | Docker Compose | v2 |
| Banking (accounts) | PSD2 Open Banking | AISP/PISP (all EU/EEA banks) |
| Card issuing | Marqeta Europe Ltd | Visa debit cards + NFC tokenization |

---

## Docker Stack

| Service | Image | Port | Description |
|---------|-------|------|-------------|
| `php` | PHP 8.4 FPM Alpine | 9000 (internal) | Symfony app with OPcache + JIT |
| `nginx` | Nginx 1.27 Alpine | 80 → 443 (HTTPS) | Reverse proxy, TLS termination |
| `postgres` | PostgreSQL 16 Alpine | 5432 | Primary database |
| `redis` | Redis 7 Alpine | 6379 | Sessions, rate limiter, messenger |

**HTTPS:** Locally-trusted certificates via [mkcert](https://github.com/FiloSottile/mkcert). Nginx terminates TLS with HTTP/2. Certificates are generated into `docker/certs/` and git-ignored.

**Makefile commands:**

| Command | Description |
|---------|-------------|
| `make setup` | Full first-time setup (certs + build + install + migrate) |
| `make certs` | Generate mkcert HTTPS certificates |
| `make up` | Start all services |
| `make down` | Stop all services |
| `make test` | Run PHPUnit tests |
| `make shell` | Open shell in PHP container |
| `make db-shell` | Open PostgreSQL shell |
| `make migrate` | Run Doctrine migrations |
| `make jwt-keys` | Generate RS256 JWT keypair |
| `make logs` | Tail logs (all services) |
| `make clean` | Stop + remove volumes |

---

## Project Structure

```
eu-pay/
├── docker-compose.yml                   # PHP + Nginx + PostgreSQL + Redis
├── Makefile                             # Developer commands
├── LICENSE                              # MIT
│
├── docker/
│   ├── php/
│   │   ├── Dockerfile                   # PHP 8.4 FPM + OPcache JIT + Composer
│   │   └── php-fpm.d/www.conf           # FPM pool config
│   ├── nginx/
│   │   ├── Dockerfile                   # Nginx 1.27
│   │   └── eupay.conf                   # HTTPS, TLS 1.2/1.3, security headers
│   └── certs/
│       └── .gitkeep                     # mkcert certs generated here
│
├── backend/
│   ├── composer.json                    # Symfony 8.0, PHP ≥8.4
│   ├── public/index.php                 # Symfony front controller
│   ├── .env.example                     # Environment template
│   ├── phpunit.xml                      # Test configuration
│   ├── config/
│   │   ├── bundles.php                  # Bundle registration
│   │   ├── routes.yaml                  # Attribute routing
│   │   ├── services.yaml                # DI wiring, env bindings
│   │   └── packages/
│   │       ├── framework.yaml           # UUIDv6, rate limiting, serializer
│   │       ├── security.yaml            # JWT firewall, access control
│   │       ├── doctrine.yaml            # ORM, PostgreSQL, test SQLite
│   │       ├── doctrine_migrations.yaml
│   │       ├── lexik_jwt.yaml           # RS256 JWT config
│   │       └── nelmio_cors.yaml         # CORS for Android + webhooks
│   ├── src/
│   │   ├── Kernel.php                   # Symfony MicroKernel
│   │   ├── Controller/
│   │   │   ├── AuthController.php       # Register, login, key rotation
│   │   │   ├── AccountController.php    # PSD2 account, balance, transactions
│   │   │   ├── CardController.php       # Virtual card CRUD, activate/block
│   │   │   ├── HceController.php        # HCE token provisioning + payloads
│   │   │   ├── GdprController.php       # Export, erase, consent, legal pages
│   │   │   └── WebhookController.php    # Webhook receiver
│   │   ├── Entity/
│   │   │   ├── User.php                 # Encrypted PII, blind indexes, public key
│   │   │   ├── Card.php                 # PSD2 card reference
│   │   │   ├── HceToken.php             # NFC session tokens
│   │   │   └── Transaction.php          # Encrypted merchant data
│   │   ├── Repository/
│   │   │   ├── UserRepository.php
│   │   │   ├── CardRepository.php
│   │   │   ├── HceTokenRepository.php
│   │   │   └── TransactionRepository.php
│   │   └── Service/
│   │       ├── OpenBankingService.php     # PSD2 AISP/PISP (accounts, payments)
│   │       ├── CardIssuing/
│   │       │   ├── CardIssuerInterface.php  # Abstraction for any EU card issuer
│   │       │   └── MarqetaCardIssuer.php    # Marqeta Visa (powers Curve, Wise)
│   │       ├── OpenBankingException.php
│   │       ├── CardService.php          # Card lifecycle management
│   │       ├── HceProvisioningService.php
│   │       ├── CardEncryptionService.php
│   │       └── Crypto/
│   │           ├── EnvelopeEncryptionService.php  # RSA-4096 + AES-256-GCM
│   │           └── BlindIndexService.php          # HMAC-SHA256 indexes
│   └── tests/
│       ├── Functional/
│       │   └── WebhookSignatureTest.php
│       └── Unit/
│           ├── Entity/
│           │   ├── UserTest.php
│           │   ├── UserGdprTest.php
│           │   ├── CardTest.php
│           │   ├── HceTokenTest.php
│           │   └── TransactionTest.php
│           └── Service/
│               ├── CardServiceTest.php
│               ├── CardEncryptionServiceTest.php
│               ├── HceProvisioningServiceTest.php
│               ├── 
│               ├── EuComplianceTest.php
│               └── Crypto/
│                   ├── EnvelopeEncryptionServiceTest.php
│                   └── BlindIndexServiceTest.php
│
├── android/
│   ├── build.gradle                     # AGP 8.10.1, Kotlin 2.1.21, Hilt, KSP
│   ├── settings.gradle                  # Plugin management
│   ├── gradle.properties                # Build optimizations
│   ├── gradlew                          # Gradle wrapper
│   ├── gradle/wrapper/
│   │   └── gradle-wrapper.properties    # Gradle 8.11.1
│   ├── app/
│   │   ├── build.gradle                 # compileSdk 35, Compose BOM 2026.01.01
│   │   ├── proguard-rules.pro
│   │   └── src/
│   │       ├── main/
│   │       │   ├── AndroidManifest.xml
│   │       │   ├── java/com/example/eupay/
│   │       │   │   ├── EuPayApp.kt              # @HiltAndroidApp
│   │       │   │   ├── api/
│   │       │   │   │   ├── EuPayApi.kt          # Retrofit interface
│   │       │   │   │   └── AuthInterceptor.kt
│   │       │   │   ├── crypto/
│   │       │   │   │   └── ClientKeyManager.kt  # RSA-4096, Android Keystore
│   │       │   │   ├── di/AppModule.kt          # Hilt DI wiring
│   │       │   │   ├── hce/
│   │       │   │   │   ├── PaymentHceService.kt # HostApduService for NFC
│   │       │   │   │   ├── EmvUtil.kt           # TLV / APDU parsing
│   │       │   │   │   └── HcePaymentDataHolder.kt
│   │       │   │   ├── model/Models.kt          # Zero-knowledge data models
│   │       │   │   ├── repository/TokenRepository.kt
│   │       │   │   ├── service/
│   │       │   │   │   ├── AuthService.kt
│   │       │   │   │   ├── CardService.kt
│   │       │   │   │   └── PaymentService.kt
│   │       │   │   └── util/
│   │       │   │       ├── DeviceFingerprint.kt
│   │       │   │       └── UuidV6.kt
│   │       │   └── res/
│   │       │       ├── values/strings.xml
│   │       │       └── xml/
│   │       │           ├── hce_payment_aid.xml
│   │       │           └── network_security_config.xml
│   │       ├── test/java/com/example/eupay/
│   │       │   ├── hce/
│   │       │   │   ├── EmvUtilTest.kt
│   │       │   │   └── HcePaymentDataHolderTest.kt
│   │       │   ├── service/
│   │       │   │   ├── AuthServiceTest.kt
│   │       │   │   ├── EuComplianceAndroidTest.kt
│   │       │   │   └── PaymentServiceTest.kt
│   │       │   └── util/
│   │       │       ├── DeviceFingerprintTest.kt
│   │       │       └── UuidV6Test.kt
│   │       └── androidTest/java/com/example/eupay/hce/
│   │           └── DeviceFingerprintInstrumentedTest.kt
│
├── docs/
│   ├── EU_COMPLIANCE.md                 # Full EU regulatory matrix
│   └── ZERO_KNOWLEDGE_ENCRYPTION.md     # Crypto architecture docs
│
├── k8s/
│   ├── base/                            # Shared Kustomize base
│   │   ├── kustomization.yaml
│   │   ├── namespace.yaml               # eupay namespace
│   │   ├── configmap.yaml
│   │   ├── secret.yaml                  # Template (use SealedSecrets)
│   │   ├── php-deployment.yaml          # PHP 8.4 FPM
│   │   ├── nginx-deployment.yaml        # Nginx + ConfigMap
│   │   ├── postgres-statefulset.yaml    # PostgreSQL 16 + PVC
│   │   ├── redis-deployment.yaml        # Redis 7
│   │   ├── *-service.yaml              # Services (4)
│   │   ├── ingress.yaml                 # TLS + cert-manager
│   │   ├── cert-issuer.yaml             # Let's Encrypt issuers
│   │   ├── hpa.yaml                     # Autoscaling
│   │   ├── pdb.yaml                     # Disruption budgets
│   │   └── networkpolicy.yaml           # Zero-trust network
│   ├── overlays/
│   │   ├── staging/kustomization.yaml   # staging-api.eupay.eu
│   │   └── production/kustomization.yaml # api.eupay.eu
│   └── argocd/
│       ├── project.yaml                 # AppProject + RBAC
│       ├── staging.yaml                 # Auto-sync from develop
│       └── production.yaml              # Manual sync from main
│
├── .github/workflows/release.yml        # CI: test → build → GitHub Release
└── .gitignore
```

---

## API Endpoints

### Authentication (`/api`)

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/register` | Register with GDPR consent + RSA-4096 public key |
| `GET` | `/api/me` | Get profile (encrypted fields) |
| `POST` | `/api/me/rotate-key` | Rotate encryption key pair |

### Account (`/api/account`)

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/account/create` | Open bank account via PSD2 |
| `GET` | `/api/account/balance` | Fetch account balance |
| `GET` | `/api/account/transactions` | List transactions (encrypted merchant data) |

### Cards (`/api/cards`)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/cards` | List user's cards |
| `POST` | `/api/cards/virtual` | Create virtual card |
| `POST` | `/api/cards/{id}/activate` | Activate card |
| `POST` | `/api/cards/{id}/block` | Block card |
| `POST` | `/api/cards/{id}/unblock` | Unblock card |

### HCE / NFC (`/api/hce`)

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/hce/provision` | Provision HCE token for NFC payments |
| `GET` | `/api/hce/tokens` | List active HCE tokens |
| `GET` | `/api/hce/payload/{tokenId}` | Fetch APDU payload for NFC tap |
| `POST` | `/api/hce/refresh/{tokenId}` | Refresh token before expiry |
| `POST` | `/api/hce/deactivate/{tokenId}` | Deactivate token |

### GDPR & Legal (`/api`)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/gdpr/export` | GDPR Art. 20 data portability (encrypted blobs) |
| `GET` | `/api/gdpr/consent` | View current consent status |
| `PATCH` | `/api/gdpr/consent` | Update consent preferences |
| `POST` | `/api/gdpr/erase` | GDPR Art. 17 right to erasure |
| `GET` | `/api/legal/privacy-policy` | Machine-readable privacy policy |
| `GET` | `/api/legal/imprint` | TMG §5 Impressum |
| `GET` | `/api/legal/withdrawal` | Consumer Rights Directive withdrawal info |

### Webhooks (`/webhook`)

| Method | Path | Description |
|--------|------|-------------|

### Top-Up (`/api/topup`) — PSD2 PISP

Fund your EU Pay account from any EU/EEA bank via your phone.

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/topup/ideal` | iDEAL top-up (Dutch instant — Rabobank, ING, ABN AMRO, etc.) |
| `POST` | `/api/topup/sepa` | SEPA Credit Transfer (any EU/EEA bank) |
| `GET` | `/api/topup/callback` | SCA redirect callback from bank |
| `GET` | `/api/topup/history` | Top-up history |
| `GET` | `/api/topup/banks` | List all EU/EEA PSD2 banks (filter by `?country=NL`) |

### P2P Transfers (`/api/p2p`)

Send money from your phone to any EU Pay user or any EU/EEA bank account.

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/p2p/send/user` | Send to EU Pay user by email (instant, free) |
| `POST` | `/api/p2p/send/iban` | Send to any EU/EEA IBAN via SEPA (all PSD2 banks) |
| `GET` | `/api/p2p/history` | Transfer history (sent + received) |
| `GET` | `/api/p2p/banks` | List EU/EEA PSD2 banks |

---

## PSD2 Open Banking — Pay From Your Phone

EU Pay uses PSD2 PISP (Payment Initiation Service Provider) so you can fund your account and send money directly from your phone, using your existing bank account at any EU/EEA bank.

**PSD2 is mandatory.** Every licensed bank in the EU/EEA must expose XS2A APIs (Directive 2015/2366, enforced since 14 September 2019). EU Pay connects to 140+ banks across all 30 EU/EEA countries.

### Phone Payment Flow

```
1. Open EU Pay app on your phone
2. Tap "Top Up" or "Send Money"
3. Select amount + bank (Rabobank, ING, Deutsche Bank, etc.)
4. App opens your bank's SCA page (Custom Chrome Tab)
5. Authenticate at your bank (biometric / PIN / card reader)
6. Bank confirms → funds arrive in your EU Pay account
7. Tap-to-pay with NFC at any contactless terminal
```

### NFC Tap-to-Pay Architecture (Two Banking Layers)

EU Pay uses **two separate banking integrations** — this is critical:

```
┌──────────────────────────────────────────────────────────────────┐
│  FUNDING LAYER — PSD2 Open Banking (AISP/PISP)                  │
│  ● Top-up from any EU/EEA bank (iDEAL, SEPA)                   │
│  ● P2P transfers to any EU/EEA IBAN                             │
│  ● Account balance & transactions via AISP                       │
│  ● 140+ banks: Rabobank, ING, Deutsche Bank, BNP Paribas, etc. │
└──────────────────────┬───────────────────────────────────────────┘
                       │ funds loaded onto card
┌──────────────────────▼───────────────────────────────────────────┐
│  SPENDING LAYER — Card Issuer (Marqeta/Adyen/Stripe)             │
│  ● Issue Visa/Mastercard virtual debit cards                     │
│  ● DPAN (Device PAN) tokenization for HCE                       │
│  ● EMV session keys (ARQC) for each contactless tap              │
│  ● Transaction authorization via Visa/Mastercard network         │
└──────────────────────┬───────────────────────────────────────────┘
                       │ APDU over NFC
┌──────────────────────▼───────────────────────────────────────────┐
│  ANDROID HCE SERVICE                                             │
│  ● SELECT PPSE → responds with Visa AID                         │
│  ● GET PROCESSING OPTIONS → returns DPAN + EMV data             │
│  ● GENERATE AC → computes ARQC using session keys               │
│  ● POS terminal → acquirer → Visa → Marqeta → approved ✓       │
└──────────────────────────────────────────────────────────────────┘
```

**Why PSD2 alone isn't enough:** PSD2 lets you *access* bank accounts and *initiate* payments, but it cannot *issue* Visa/Mastercard cards or generate the EMV cryptograms needed for contactless NFC payments. You need a licensed card programme manager (Marqeta, Adyen Issuing, Stripe Issuing, or Enfuce).

### Supported Banks (140+)

All 27 EU member states + Norway, Iceland, Liechtenstein:

| Country | Major Banks |
|---------|------------|
| 🇳🇱 Netherlands | Rabobank, ING, ABN AMRO, SNS, ASN, bunq, Triodos, Knab, RegioBank |
| 🇩🇪 Germany | Deutsche Bank, Commerzbank, HypoVereinsbank, ING-DiBa, N26, Sparkasse |
| 🇫🇷 France | BNP Paribas, Société Générale, Crédit Agricole, LCL, Crédit Mutuel, La Banque Postale |
| 🇪🇸 Spain | CaixaBank, Santander, BBVA, Sabadell, Bankinter, Unicaja |
| 🇮🇹 Italy | Intesa Sanpaolo, UniCredit, Banco BPM, BPER, BNL, Crédit Agricole Italia |
| 🇵🇱 Poland | PKO, mBank, ING Śląski, Santander PL, Millennium, Alior |
| 🇧🇪 Belgium | KBC, BNP Paribas Fortis, Belfius, ING Belgium |
| 🇦🇹 Austria | Erste Bank, Raiffeisen, BAWAG, UniCredit Austria |
| 🇸🇪 Sweden | Nordea, Handelsbanken, Swedbank, SEB |
| 🇩🇰 Denmark | Danske Bank, Nordea, Jyske Bank |
| 🇫🇮 Finland | Nordea, OP Group, Danske Bank Finland |
| 🇵🇹 Portugal | Caixa Geral, Millennium BCP, Santander Totta |
| 🇷🇴 Romania | Banca Transilvania, BCR, BRD, ING Romania |
| 🇨🇿 Czechia | Komerční banka, ČSOB, Česká spořitelna |
| 🇮🇪 Ireland | AIB, Bank of Ireland, Permanent TSB |
| 🇬🇷 Greece | National Bank, Piraeus, Eurobank, Alpha Bank |
| 🇭🇺 Hungary | OTP Bank, Erste, K&H, UniCredit |
| 🇳🇴 Norway | DNB, Nordea, SpareBank 1 |
| + 12 more | All EU/EEA countries covered |

Full bank list: `GET /api/topup/banks` or `GET /api/p2p/banks?country=NL`

### P2P Transfers

| Type | Speed | Fee | Method |
|------|-------|-----|--------|
| EU Pay → EU Pay | Instant | Free | Internal PSD2 PISP transfer |
| EU Pay → any EU IBAN | 1-2 days | Free* | SEPA Credit Transfer |
| EU Pay → any EU IBAN | <10 seconds | Free* | SEPA Instant (where supported) |

*Standard SEPA, no EU Pay markup. Bank may charge their own fees.

---

## Zero-Knowledge Encryption

The backend never stores or processes plaintext personal data.

**Envelope encryption flow:**

1. Android app generates RSA-4096 key pair. Private key stays in Android Keystore (hardware-backed, non-exportable). Public key is sent to backend at registration.
2. For every PII field (email, name, phone, IBAN), the backend generates a random AES-256-GCM data encryption key (DEK), encrypts the field with it, then encrypts the DEK with the user's RSA public key.
3. The encrypted blob (encrypted DEK + IV + ciphertext + GCM tag) is stored. No plaintext ever hits disk.
4. For searchable fields (email, phone, IBAN), a blind index (HMAC-SHA256 with a server-side key over normalized input) is computed and stored alongside the encrypted blob.
5. Login: client sends email → backend computes blind index → looks up user → returns JWT. Email plaintext is never persisted.
6. GDPR export: returns encrypted blobs. Client decrypts locally.
7. Key rotation: client decrypts all fields with old key, re-encrypts with new public key, POSTs to `/api/me/rotate-key`.

See `docs/ZERO_KNOWLEDGE_ENCRYPTION.md` for the full specification.

---

## EU Compliance

Full regulatory matrix in `docs/EU_COMPLIANCE.md`. Summary:

| Regulation | Status | Implementation |
|-----------|--------|---------------|
| GDPR Art. 6 (lawful basis) | ✅ | Explicit consent at registration |
| GDPR Art. 7 (consent conditions) | ✅ | Granular, withdrawable, timestamped |
| GDPR Art. 13–14 (transparency) | ✅ | Machine-readable privacy policy endpoint |
| GDPR Art. 15 (access) | ✅ | `/api/gdpr/export` |
| GDPR Art. 17 (erasure) | ✅ | `/api/gdpr/erase` with AML carve-out |
| GDPR Art. 20 (portability) | ✅ | JSON export of encrypted fields |
| ePrivacy Art. 5(3) | ✅ | No tracking cookies/fingerprints |
| PSD2 SCA | ✅ | Biometric + device binding |
| Consumer Rights Directive | ✅ | 14-day withdrawal info endpoint |
| AML (5AMLD) | ✅ | 5-year transaction retention |
| German TMG §5 | ✅ | `/api/legal/imprint` |
| German HGB | ✅ | 10-year financial record retention |

---

## Getting Started

### Prerequisites

- **Docker** + Docker Compose v2
- **mkcert** for local HTTPS certificates
- A PSD2 sandbox account (e.g., [Rabobank Developer](https://developer.rabobank.nl))
- For Android: Android Studio Narwhal 2025.1+ or JDK 21

### Backend Setup (Docker — recommended)

```bash
make setup  # one command does everything
```

This runs: `make certs` → `make build` → `make up` → `make install` → `make jwt-keys` → `make migrate`

### Backend Setup (manual, no Docker)

```bash
cd backend
cp .env.example .env
# Edit .env with your database URL, PSD2 API keys, JWT keys, blind index key

composer install
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:migrations:migrate

# Start PHP dev server
symfony server:start --port=8443

# Run tests
vendor/bin/phpunit --testdox
```

### Android Setup

```bash
cd android

# Debug build
./gradlew assembleDebug

# Run unit tests
./gradlew testDebugUnitTest

# Release build (requires signing config)
./gradlew assembleRelease \
  -PRELEASE_STORE_FILE=release.keystore \
  -PRELEASE_STORE_PASSWORD=*** \
  -PRELEASE_KEY_ALIAS=eupay \
  -PRELEASE_KEY_PASSWORD=*** \
  -PAPI_BASE_URL=https://api.eupay.eu
```

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_ENV` | Symfony environment | `dev` |
| `APP_SECRET` | Symfony secret | — |
| `DATABASE_URL` | PostgreSQL connection string | Docker: auto-configured |
| `JWT_SECRET_KEY` | Path to RS256 private key | `config/jwt/private.pem` |
| `JWT_PUBLIC_KEY` | Path to RS256 public key | `config/jwt/public.pem` |
| `JWT_PASSPHRASE` | JWT key passphrase | — |
| `JWT_TOKEN_TTL` | Token lifetime in seconds | `3600` |
| `PSD2_API_BASE_URL` | PSD2 API endpoint | sandbox URL |
| `PSD2_CLIENT_ID` | PSD2 client ID | — |
| `PSD2_PARTNER_ID` | PSD2 partner ID | — |
| `PSD2_WEBHOOK_SECRET` | PSD2 webhook HMAC key | — |
| `CARD_ISSUER_API_URL` | Marqeta API endpoint | sandbox URL |
| `CARD_ISSUER_APP_TOKEN` | Marqeta application token | — |
| `CARD_ISSUER_ADMIN_TOKEN` | Marqeta admin access token | — |
| `CARD_ISSUER_PRODUCT_TOKEN` | Card product token | — |
| `CARD_ISSUER_FUNDING_TOKEN` | Funding source token | — |
| `BLIND_INDEX_KEY` | 256-bit hex key for HMAC blind indexes | — |
| `CARD_ENCRYPTION_KEY` | 256-bit hex key for card data encryption | — |
| `HCE_SESSION_KEY_TTL` | HCE token lifetime in seconds | `300` |
| `CORS_ALLOW_ORIGIN` | CORS regex pattern | `eupay.localhost` |

Generate crypto keys:

```bash
# Blind index key (32 bytes / 64 hex chars)
openssl rand -hex 32

# Card encryption key
openssl rand -hex 32
```

---

## Kubernetes & ArgoCD

Production-ready Kubernetes manifests using Kustomize overlays for staging and production, with ArgoCD GitOps deployment and cert-manager for automated Let's Encrypt TLS.

### Prerequisites

- Kubernetes 1.28+ cluster (EU-based: Hetzner, OVH, Scaleway, IONOS)
- [cert-manager](https://cert-manager.io/) installed for Let's Encrypt
- [ArgoCD](https://argo-cd.readthedocs.io/) installed
- nginx-ingress controller

### Architecture

```
k8s/
├── base/                          # Shared manifests
│   ├── kustomization.yaml
│   ├── namespace.yaml             # eupay namespace
│   ├── configmap.yaml             # Non-secret app config
│   ├── secret.yaml                # Placeholder (use SealedSecrets in prod)
│   ├── php-deployment.yaml        # PHP 8.4 FPM (2+ replicas, init: migrations)
│   ├── nginx-deployment.yaml      # Nginx reverse proxy + ConfigMap
│   ├── postgres-statefulset.yaml  # PostgreSQL 16 with PVC
│   ├── redis-deployment.yaml      # Redis 7 (sessions, rate-limiter)
│   ├── *-service.yaml             # ClusterIP services (4)
│   ├── ingress.yaml               # TLS ingress + cert-manager annotations
│   ├── cert-issuer.yaml           # Let's Encrypt ClusterIssuers (staging + prod)
│   ├── hpa.yaml                   # Autoscaling (CPU/memory based)
│   ├── pdb.yaml                   # Pod disruption budgets
│   └── networkpolicy.yaml         # Zero-trust pod-to-pod rules
│
├── overlays/
│   ├── staging/                   # 1 replica, 5Gi PVC, LE staging certs
│   │   └── kustomization.yaml     # staging-api.eupay.eu
│   └── production/                # 3 replicas, 50Gi PVC, LE prod certs, HA
│       └── kustomization.yaml     # api.eupay.eu
│
└── argocd/
    ├── project.yaml               # AppProject with RBAC (dev + admin roles)
    ├── staging.yaml               # Auto-sync from develop branch
    └── production.yaml            # Manual sync from main branch
```

### Environments

| | Staging | Production |
|--|---------|-----------|
| Branch | `develop` | `main` |
| Domain | `staging-api.eupay.eu` | `api.eupay.eu` |
| TLS | Let's Encrypt staging | Let's Encrypt production |
| PHP replicas | 1 (HPA: 1→3) | 3 (HPA: 3→20) |
| Nginx replicas | 1 (HPA: 1→2) | 3 (HPA: 3→12) |
| PostgreSQL PVC | 5 Gi | 50 Gi |
| PSD2 API | Sandbox | Production |
| ArgoCD sync | Automated (auto-prune, self-heal) | Manual (approval required) |
| PDB min available | 1 | 2 |

### Network Policies

Zero-trust by default — all ingress denied unless explicitly allowed:

```
Internet → Ingress Controller → Nginx → PHP-FPM → PostgreSQL
                                               └──→ Redis
```

### Deploy

```bash
# 1. Install ArgoCD project + apps
kubectl apply -f k8s/argocd/project.yaml
kubectl apply -f k8s/argocd/staging.yaml
kubectl apply -f k8s/argocd/production.yaml

# 2. Staging auto-syncs from develop branch

# 3. Production: manually sync via ArgoCD UI or CLI
argocd app sync eupay-production

# Manual kustomize preview
kubectl kustomize k8s/overlays/staging
kubectl kustomize k8s/overlays/production
```

### cert-manager TLS

Certificates are automatically provisioned by cert-manager using HTTP-01 challenge:

```bash
# Verify cert-manager is running
kubectl get pods -n cert-manager

# Check certificate status
kubectl get certificate -n eupay
kubectl describe certificate eupay-prod-tls -n eupay

# Check ClusterIssuers
kubectl get clusterissuer
```

---

## CI/CD

GitHub Actions pipeline (`.github/workflows/release.yml`):

1. **Backend Tests** — PHP 8.4, Composer install, PHPUnit
2. **Android Tests** — JDK 21, Gradle 8.11.1, `testDebugUnitTest`
3. **Release** (on `v*` tag) — Build signed APK, create GitHub Release with artifact

```bash
# Trigger a release
git tag v1.0.1
git push origin v1.0.1
# → CI runs tests → builds APK → publishes GitHub Release
```

---

## Test Suite

### Backend (16 tests)

| Test | Coverage |
|------|----------|
| `UserTest` | Encrypted fields, public key, blind indexes |
| `UserGdprTest` | Anonymization, encrypted PII clearing, AML audit trail |
| `CardTest` | Card entity, PSD2 references |
| `HceTokenTest` | Token lifecycle, expiry |
| `TransactionTest` | Encrypted merchant data |
| `TopUpTest` | Top-up entity, iDEAL/SEPA methods, state transitions |
| `P2PTransferTest` | Internal/external types, encrypted messages, IBAN indexes |
| `CardServiceTest` | Card issuing via CardIssuerInterface, load funds, sync status |
| `HceProvisioningServiceTest` | DPAN provisioning, EMV session keys, token lifecycle |
| `CardEncryptionServiceTest` | Card data encryption |
| `EnvelopeEncryptionServiceTest` | RSA-4096 + AES-256-GCM encrypt/decrypt round-trip |
| `BlindIndexServiceTest` | Deterministic, case-insensitive, format-normalized |
| `EuBankRegistryTest` | 30 EU/EEA countries, 140+ banks, BIC lookup |
| `OpenBankingServiceTest` | HTTP client, error handling |
| `EuComplianceTest` | GDPR endpoints, consent, legal pages |

### Android (9 tests)

| Test | Coverage |
|------|----------|
| `EmvUtilTest` | TLV encoding, APDU commands |
| `HcePaymentDataHolderTest` | Payment state singleton |
| `AuthServiceTest` | Registration with public key, zero-knowledge models |
| `PaymentServiceTest` | Payment flow |
| `P2PServiceTest` | IBAN validation (EU/EEA countries), checksum, format |
| `EuComplianceAndroidTest` | GDPR consent fields, public key in requests |
| `DeviceFingerprintTest` | Device binding |
| `UuidV6Test` | Time-sortable UUID generation |
| `DeviceFingerprintInstrumentedTest` | Hardware fingerprint (instrumented) |

---

## Security

- **Encryption at rest:** All PII envelope-encrypted (RSA-4096 + AES-256-GCM). Backend stores only ciphertext.
- **Encryption in transit:** TLS 1.2/1.3 enforced. HSTS enabled. Certificate pinning in Android.
- **Authentication:** RS256 JWT with configurable TTL. Rate-limited login (5/min).
- **Webhook verification:** HMAC-SHA256 signature on all bank webhooks.
- **Android:** Private keys in hardware-backed Keystore (non-exportable). Biometric gating.
- **Headers:** `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Strict-Transport-Security`.
- **No tracking:** No cookies, no analytics, no device fingerprinting beyond payment security requirements.

---

## License

MIT License. See [LICENSE](LICENSE) for details.

---

### Card Issuing Providers (7 EU-Licensed)

Switch provider with **one line** in `services.yaml` — zero code changes:

```yaml
App\Service\CardIssuing\CardIssuerInterface:
    alias: App\Service\CardIssuing\MarqetaCardIssuer    # ← change this
```

| Provider | Scheme | License | Country | Coverage | Powers |
|----------|--------|---------|---------|----------|--------|
| **Marqeta** (default) | Visa | Central Bank of Ireland | IE | 40+ countries | Curve, Wise, Monese |
| **Adyen Issuing** | Visa+MC | De Nederlandsche Bank | NL | 30+ EU/EEA | eBay, Klarna, H&M |
| **Stripe Issuing** | Visa | Central Bank of Ireland | IE | 20 EU countries | Ramp, Brex, Expensify |
| **Enfuce** | Visa+MC | Finnish FSA (EMI) | FI | 30 EEA + UK | Porsche Card, SEB, Pleo |
| **Wallester** | Visa | Estonian FSA | EE | 30 EEA + UK | Free tier (300 cards) |
| **Paynetics** | Visa+MC | Bulgarian National Bank | BG | All EEA | phyre, iCard, Phos |
| **Nexi Group** | Visa+MC | Banca d'Italia | IT | EU-wide | 2.9B tx/year, 1000+ banks |
| **Treezor** | MC | ACPR France | FR | 25 EU/EEA | Qonto, Lydia, Swile, Shine |
| **Swan** | MC | ACPR France | FR | EU/EEA | Pennylane, Agicap, Carrefour |
| **DECTA** | Visa+MC | FCMC Latvia | LV | EU/EEA | White-label card programmes |
| **Paynovate** | Visa+MC+UPI | Nat. Bank Belgium | BE | 30 EEA + UK | BIN sponsorship, €200M+/mo |
| **Pecunpay** | Visa+MC+UPI | Bank of Spain | ES | All SEPA | 500K+ cards, Pagaqui |
| **Solaris** | Visa+MC | BaFin Germany (bank) | DE | EU/EEA | Samsung Pay DE, Vivid Money |
| **TransactPay** | Visa+MC | MFSA Malta | MT | EU/EEA + UK | BIN sponsor, modular cards |
| **Vodeno/Aion** | Visa+MC | NBB Belgium (bank) | BE | EU/EEA | Carrefour BE, UniCredit |
| **iCard** | Visa+MC | Bulgarian Nat. Bank | BG | EU/EEA | 1M+ wallet users, SE Europe |
| **Bankable** | Visa+MC | CSSF Luxembourg | LU | EU/EEA | White-label BaaS, FX |

### Digital Euro (ECB CBDC) — Coming 2029

EU Pay includes a **Digital Euro preparedness layer** for the ECB's upcoming
central bank digital currency. See [docs/DIGITAL_EURO.md](docs/DIGITAL_EURO.md).

| Milestone | Date |
|-----------|------|
| EU Parliament vote on regulation | H1 2026 |
| Pilot with selected PSPs | H2 2027 |
| Potential first issuance | 2029 |

Three payment rails when digital euro launches:
1. **PSD2** — bank transfers, top-up (today ✅)
2. **Card issuing** — Visa/MC NFC tap-to-pay (today ✅)
3. **Digital euro** — zero-fee pan-European payments (2029 🔮)

```
GET /api/digital-euro/status → regulation parameters + availability
```
