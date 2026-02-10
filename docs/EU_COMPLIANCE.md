# EU Regulatory Compliance — EuPay

## Overview

EuPay is designed for **EU-only operation** from the ground up. Every component, service,
and data flow stays within the European Union / European Economic Area.

---

## 1. GDPR (General Data Protection Regulation)

### Legal Bases for Processing (Art. 6)

| Data                | Legal Basis            | Justification                              |
|---------------------|------------------------|--------------------------------------------|
| Name, email, phone  | Art. 6(1)(b) Contract  | Necessary for account creation & KYC       |
| IBAN, card data     | Art. 6(1)(b) Contract  | Necessary for payment services             |
| KYC documents       | Art. 6(1)(c) Legal     | EU AML Directive 2015/849                  |
| Transaction records | Art. 6(1)(c) Legal     | AML + German HGB §257 (10-year retention)  |
| Device fingerprint  | Art. 6(1)(a) Consent   | Optional — user must opt in                |
| Marketing email     | Art. 6(1)(a) Consent   | Optional — user must opt in                |

### Data Subject Rights Implemented

| Right                          | GDPR Article | Endpoint                  |
|--------------------------------|-------------|---------------------------|
| Right of Access                | Art. 15     | `GET /api/gdpr/export`    |
| Right to Erasure               | Art. 17     | `POST /api/gdpr/erase`    |
| Right to Data Portability      | Art. 20     | `GET /api/gdpr/export`    |
| Consent Management             | Art. 7      | `GET/PATCH /api/gdpr/consent` |
| Right to Rectification         | Art. 16     | Contact DPO               |
| Right to Object                | Art. 21     | Contact DPO               |

### Consent Implementation

- Registration **requires** `gdpr_consent: true` — request is rejected otherwise
- Privacy policy version is tracked per-user — enabling re-consent on policy updates
- Optional consents (device tracking, marketing) default to **false** (opt-in only)
- Consent withdrawal is as easy as granting it (`PATCH /api/gdpr/consent`)
- All consent timestamps are stored for audit purposes

### Right to Erasure (Art. 17)

- `POST /api/gdpr/erase` anonymizes all personal data
- Requires explicit `confirm_deletion: true` confirmation
- Email → `deleted-<random>@anonymized.local`
- Name → `DELETED`
- Phone, IBAN, password → cleared
- Transaction records retained (anonymized) per AML requirements
- All HCE tokens deactivated, cards closed

### Data Retention

| Data Type           | Retention Period | Legal Basis                          |
|---------------------|-----------------|--------------------------------------|
| Account data        | Contract + 5 yrs| EU AML Directive 2015/849            |
| Transaction records | 10 years        | German Commercial Code (HGB §257)    |
| KYC documents       | 5 years         | EU AML Directive 2015/849            |
| Marketing consent   | 3 yrs after withdrawal | Proof of consent                |

---

## 2. PSD2 (Payment Services Directive 2)

### Strong Customer Authentication (SCA)

- JWT tokens + biometric authentication on the Android app
- Sensitive operations (HCE provisioning, card activation, SEPA transfers) require active JWT
- Session keys for HCE tokens expire after configurable TTL (default: 300s)
- Device binding ensures tokens are tied to a specific authorized device

### Secure Communication

- All API calls use TLS 1.2+ (HTTPS only)
- PSD2 API communication uses eIDAS QWAC certificate mutual TLS
- Card data (DPANs, session keys) encrypted at rest with AES-256-GCM
- Webhook payloads verified via HMAC-SHA256 signature

---

## 3. ePrivacy Directive

### Device Fingerprinting (Art. 5(3))

- Device fingerprint generation **requires explicit consent** (`consentGiven: true`)
- Attempting to generate a fingerprint without consent throws `ConsentRequiredException`
- A privacy-preserving alternative exists that uses only a random installation ID
- The fingerprint is a one-way SHA-256 hash — original identifiers cannot be recovered

### No Cookies / No Tracking

- The EuPay mobile app does not use browser cookies
- No third-party analytics, crash reporting, or ad SDKs
- No data is sent to Google, Facebook, or any non-EU tracker

---

## 4. EU Consumer Rights Directive (2011/83/EU)

- 14-day withdrawal right information available at `GET /api/legal/withdrawal`
- EU Online Dispute Resolution platform linked
- Pre-contractual information provided before account opening

---

## 5. Data Residency & Infrastructure

### Mandatory EU Hosting

All infrastructure MUST be hosted within the EU/EEA:

- **Backend servers**: Germany recommended (Hetzner, IONOS)
- **Database**: PostgreSQL in EU data center
- **Banking API**: PSD2 Open Banking — all EU/EEA licensed banks
- **NFC processing**: On-device (Android HCE) — no data leaves the phone during tap

### Prohibited

- ❌ No AWS us-east/us-west regions
- ❌ No Google Cloud us-central
- ❌ No Cloudflare CDN for user data
- ❌ No US-based analytics (Google Analytics, Mixpanel, Amplitude)
- ❌ No US-based crash reporting (Firebase Crashlytics, Sentry US)
- ❌ No US-based push notifications (Firebase Cloud Messaging)

### Allowed EU Alternatives

- ✅ Matomo (EU self-hosted analytics, if needed)
- ✅ Sentry EU (sentry.io EU data center)
- ✅ ntfy / Gotify (EU self-hosted push, if needed)

---

## 6. Anti-Money Laundering (EU AML Directive 2015/849)

- KYC verification via PSD2 bank partner (eIDAS/videoident)
- Transaction monitoring via PSD2 compliance framework
- 5-year record retention for all financial data
- Records preserved even after account erasure (anonymized per GDPR Art. 17(3)(b))

---

## 7. German-Specific Requirements

- **Impressum** (TMG §5): Available at `GET /api/legal/imprint`
- **Record retention** (HGB §257): 10 years for accounting records
- **Regulatory oversight**: Via national competent authorities under PSD2

---

## 8. Dependencies Audit

All third-party libraries are open-source and run locally (no data exfiltration):

| Library         | Type      | Data Sent Externally |
|-----------------|-----------|---------------------|
| Symfony 7       | Backend   | None                |
| Doctrine ORM    | Backend   | None (local DB)     |
| Kotlin          | Android   | None                |
| Retrofit/OkHttp | Android   | Only to our backend |
| Hilt (Dagger)   | Android   | None                |
| Jetpack Compose | Android   | None                |
| EncryptedPrefs  | Android   | None                |

No Google Play Services, Firebase, or any Google SDK is used for data processing.
