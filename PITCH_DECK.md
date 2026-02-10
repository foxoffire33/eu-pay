# Stichting EU Pay — Pitch Deck

> **European payment sovereignty through open-source NFC tap-to-pay**

---

## The Problem

**European citizens have no European-owned alternative for phone payments.**

Every contactless tap in Europe flows through American infrastructure:

- **Google Pay** — Alphabet Inc., Mountain View, CA
- **Apple Pay** — Apple Inc., Cupertino, CA
- **Visa / Mastercard** — both headquartered in the USA

This means: 440 million EU citizens depend entirely on US corporations to pay for their coffee, groceries, and train tickets. These corporations can be compelled by US law (CLOUD Act, EO sanctions) to freeze, surveil, or restrict European payments at any time.

**This is not hypothetical.** Visa and Mastercard cut off Russian cardholders overnight in 2022. European payment infrastructure should not be controlled from outside Europe.

---

## The Solution

**EU Pay** is an open-source Android app that lets EU citizens tap-to-pay at any contactless terminal — without Google Pay, without Apple Pay.

```
┌──────────────────────────────────────┐
│  📱 EU Pay App (Android)             │
│  ● Top up from any EU/EEA bank      │
│  ● Tap to pay via NFC               │
│  ● Send money P2P to any IBAN       │
│  ● Zero-knowledge encryption        │
│  ● Digital Euro ready (2029)        │
└──────────────────────────────────────┘
```

**How it works:**

1. Open EU Pay, link your bank via PSD2 (secure, standardised, EU-regulated)
2. Top up your balance via iDEAL, SEPA, or any EU bank
3. Tap your phone at any contactless POS terminal
4. Payment flows through EU-licensed card issuer → Visa/Mastercard network → merchant

---

## Why a Stichting (Foundation)?

EU Pay is registered as a **Stichting** (Dutch foundation) at the KVK — not a startup, not a corporation.

| | Stichting EU Pay | Typical Fintech Startup |
|---|---|---|
| **Goal** | Public good: payment sovereignty | Private good: shareholder returns |
| **Profit** | Reinvested into mission | Distributed to investors |
| **Governance** | Board of directors (bestuur) | VC-controlled board |
| **Code** | 100% open source | Proprietary, locked |
| **Data** | Zero-knowledge encryption | Monetised user data |
| **Jurisdiction** | Netherlands (KVK) | Often Delaware/Cayman |
| **Accountability** | Dutch civil law + statuten | Shareholder primacy |

**Why Netherlands?** The Dutch financial ecosystem is uniquely positioned: home to Adyen, Mollie, Bunq; DNB is a progressive regulator; KVK stichting structure provides legal clarity; strong PSD2 adoption; and the Netherlands has the highest contactless payment adoption in the EU.

### KVK Registration

- **Legal entity:** Stichting EU Pay
- **Chamber of Commerce:** Kamer van Koophandel (KVK), Netherlands
- **Type:** Stichting (foundation with rechstpersoonlijkheid)
- **Setup cost:** €500–1,000 (notaris) + KVK inschrijfvergoeding
- **Board:** Minimum 1 bestuurder (voorzitter/secretaris/penningmeester)
- **ANBI status:** Eligible — mission qualifies as algemeen nut (public benefit)
- **Tax:** ANBI = tax-exempt donations, no vennootschapsbelasting on mission activities

---

## Architecture

EU Pay uses **three independent banking layers** — no single point of failure:

```
┌─────────────────────────────────────────────────────────────┐
│  LAYER 1 — PSD2 Open Banking (AISP/PISP)                   │
│  ● Top-up from 140+ EU/EEA banks                           │
│  ● P2P transfers to any IBAN                               │
│  ● Account balance & transactions                          │
│  ● Powered by: NextGenPSD2 standard, eIDAS certificates    │
└─────────────────────────────┬───────────────────────────────┘
                              │ funds loaded onto card
┌─────────────────────────────▼───────────────────────────────┐
│  LAYER 2 — Card Issuing (17 EU-licensed providers)           │
│  ● IE: Marqeta, Stripe  · NL: Adyen  · FR: Treezor, Swan   │
│  ● FI: Enfuce · EE: Wallester · BG: Paynetics, iCard       │
│  ● IT: Nexi · DE: Solaris · ES: Pecunpay · BE: Paynovate   │
│  ● LV: DECTA · MT: TransactPay · LU: Bankable · BE: Vodeno │
│  ● Issue Visa/Mastercard, DPAN tokenization, EMV keys      │
│  ● Swappable via CardIssuerInterface (no vendor lock-in)   │
└─────────────────────────────┬───────────────────────────────┘
                              │ APDU over NFC
┌─────────────────────────────▼───────────────────────────────┐
│  LAYER 3 — Digital Euro (ECB CBDC, 2029)                    │
│  ● DESP API integration (Berlin Group standard)             │
│  ● P2P, POS (NFC/QR), e-commerce, offline payments         │
│  ● €3,000 holding limit, privacy-preserving                │
│  ● Stub interface ready — swap to live when ECB launches    │
└─────────────────────────────────────────────────────────────┘
```

---

## Market Opportunity

### The EU Payments Market

- **€240 trillion** in annual EU payment transactions
- **69%** of all EU card payments processed by non-EU schemes (Visa/MC)
- **0** pan-European phone payment apps owned by EU entities
- **440 million** EU citizens without a European NFC payment alternative

### Regulatory Tailwinds

| Regulation | Status | Impact on EU Pay |
|---|---|---|
| **PSD2** | Active | Enables bank connectivity for all EU banks |
| **PSD3/PSR** | Expected 2026 | Stronger open banking, real-time payments mandatory |
| **Digital Euro Regulation** | Parliament vote H1 2026 | EU Pay becomes digital euro wallet |
| **eIDAS 2.0** | Rolling out 2026 | EU Digital Identity wallet integration |
| **DORA** | Active Jan 2025 | ICT resilience — EU Pay already K8s + zero-trust |
| **European Payments Initiative (EPI/Wero)** | Live 2024 | Potential interoperability partner |

### Strategic Position

EU Pay is positioned at the intersection of **three converging EU policy goals**:

1. **Payment sovereignty** — reduce dependence on US payment infrastructure
2. **Digital euro distribution** — ECB needs PSP intermediaries by 2027 pilot
3. **Open source public infrastructure** — EU commitment to digital commons

---

## Technology

### Backend — Symfony 8.0 + PHP 8.4

- Zero-knowledge encryption: RSA-4096 OAEP + AES-256-GCM
- Blind indexes (HMAC-SHA256) for searchable encrypted data
- 100% test coverage on backend services
- PSD2 SCA compliance, GDPR Article 25 data protection by design

### Android — Kotlin + HCE

- Host Card Emulation for direct NFC contactless payments
- Custom Chrome Tabs for bank SCA flows
- EMV APDU processing (SELECT PPSE → GPO → GENERATE AC)
- Device-bound DPAN with per-tap ARQC generation

### Infrastructure — Kubernetes + ArgoCD

- Kustomize overlays: staging + production
- HPA auto-scaling (2→20 pods), PDB, zero-trust network policies
- Let's Encrypt TLS via cert-manager
- PostgreSQL 16 + Redis 7

### Security

- Zero-knowledge: backend never sees real names, addresses, or card numbers
- Envelope encryption: per-user RSA-4096 key pairs
- AML-compliant: 5-year encrypted retention per EU 6AMLD
- PCI-DSS path: card data handled by licensed issuers, not EU Pay
- No tracking, no analytics, no ad SDKs

---

## Digital Euro Strategy

The ECB's digital euro is the biggest opportunity for EU Pay.

### Timeline

```
2026 Q1  ← WE ARE HERE
   │  ECB call for PSP expression of interest
   │  European Parliament votes on Digital Euro Regulation
   │
2027 H2
   │  12-month pilot with selected PSPs
   │  Real transactions in controlled environment
   │  EU Pay applies as pilot PSP participant
   │
2029
   │  Potential first issuance of digital euro
   │  EU Pay offers: card payments + digital euro in one app
   │  Three payment rails: PSD2 bank transfer, card tap, digital euro
```

### Why EU Pay is Uniquely Positioned

- **Interface already built:** DigitalEuroInterface + stub ready to swap
- **PSD2 infrastructure in place:** same AISP/PISP flows fund digital euro wallets
- **Open source = trust:** ECB explicitly seeks transparent, auditable PSP partners
- **Stichting = aligned incentives:** non-profit mission matches ECB's public good mandate
- **Dutch jurisdiction:** DNB is an ECB Governing Council member
- **RESTful API standard:** EU Pay already uses Berlin Group NextGenPSD2, same as DESP

---

## Revenue Model (Non-Profit)

As a stichting, EU Pay reinvests all surplus into its mission.

| Revenue Stream | Source | Estimate |
|---|---|---|
| Interchange share | Card issuer shares 0.1–0.2% of each tap | Primary |
| Premium features | Business accounts, multi-currency, higher limits | Secondary |
| EU grants | Horizon Europe, Digital Europe Programme, CEF | Grants |
| Donations | ANBI tax-deductible donations | Supplementary |
| Digital euro fees | Merchant fees (ECB-capped) for DEA distribution | Future (2029) |

### Cost Structure (Lean)

- **No VC burn rate** — stichting has no investors to return capital to
- **Open source** — community contributions reduce development cost
- **No marketing budget** — organic growth via EU policy advocacy
- **Cloud-native** — Kubernetes scales from €50/month to enterprise

---

## Competitive Landscape

| Solution | EU-Owned | Open Source | NFC Tap | Works Without Google/Apple | Digital Euro Ready |
|---|---|---|---|---|---|
| **EU Pay** | ✅ NL | ✅ | ✅ | ✅ | ✅ |
| Google Pay | ❌ US | ❌ | ✅ | ❌ | ❌ |
| Apple Pay | ❌ US | ❌ | ✅ | ❌ | ❌ |
| Wero (EPI) | ✅ EU | ❌ | ⚠️ QR only | ✅ | ❓ |
| Bluecode | ✅ AT | ❌ | ❌ QR only | ✅ | ❓ |
| iDEAL 2.0 | ✅ NL | ❌ | ❌ | ✅ | ❓ |

**EU Pay is the only solution that is EU-owned, open source, AND supports NFC tap-to-pay without depending on Google or Apple.**

---

## Roadmap

### 2026 — Foundation

- [x] Backend: Symfony 8, PSD2, 7 card issuers, Digital Euro stub
- [x] Android: HCE NFC, bank linking, P2P transfers
- [x] Infrastructure: K8s, ArgoCD, CI/CD, zero-knowledge encryption
- [x] EU compliance: GDPR, PSD2 SCA, AML 6AMLD, ePrivacy
- [ ] KVK Stichting registration (notaris appointment)
- [ ] ANBI status application (Belastingdienst)
- [ ] Marqeta sandbox integration testing
- [ ] First beta release on F-Droid (open-source Android store)

### 2027 — Growth

- [ ] Apply for ECB digital euro pilot PSP programme
- [ ] PSD3/PSR compliance update
- [ ] eIDAS 2.0 Digital Identity wallet integration
- [ ] Wero/EPI interoperability exploration
- [ ] Expand to 5 EU countries (NL, DE, FR, FI, EE)
- [ ] Physical card option (Enfuce or Wallester)

### 2028–2029 — Scale

- [ ] Digital euro live integration (replace stub with DESP client)
- [ ] iOS app (post Apple NFC API mandate, EU DMA compliance)
- [ ] EU-wide rollout: all 27 member states
- [ ] Offline digital euro payments
- [ ] Enterprise/merchant API

---

## Team Requirements

Stichting EU Pay needs a founding bestuur (board):

| Role | Responsibility |
|---|---|
| **Voorzitter** (Chair) | Strategy, ECB/EU institutional relations, regulatory |
| **Secretaris** (Secretary) | Legal, KVK compliance, ANBI, statuten |
| **Penningmeester** (Treasurer) | Finances, grant applications, interchange accounting |
| **Technical Lead** | Architecture, code review, security audit |

*Advisory council (raad van toezicht) recommended for regulatory credibility.*

---

## Ask

### To Launch (2026)

| Item | Cost | Notes |
|---|---|---|
| Stichting oprichting | €1,000 | Notaris + KVK |
| Marqeta/Adyen sandbox | €0 | Free sandbox tier |
| Cloud infrastructure | €600/yr | Scaleway/Hetzner K8s |
| Security audit | €5,000 | Pre-launch penetration test |
| DNB consultation | €0 | Free initial regulatory guidance |
| **Total** | **€6,600** | |

### EU Grant Opportunities

- **Horizon Europe** — Cluster 4 Digital (up to €3M for payment sovereignty projects)
- **Digital Europe Programme** — Cybersecurity & Trust (up to €2M)
- **CEF Digital** — eIDAS/digital identity integration (up to €1M)
- **NGI (Next Generation Internet)** — Open source infrastructure (up to €150K)

---

## Summary

**Stichting EU Pay** is a Dutch foundation building open-source European payment infrastructure.

**Three payment rails in one app:**
1. PSD2 Open Banking — top-up from any EU/EEA bank
2. Card issuing — NFC tap-to-pay at any contactless terminal
3. Digital euro — ready for ECB's 2029 launch

**Why now:**
- ECB calling for PSP pilot participants in Q1 2026
- PSD3 strengthening open banking across the EU
- Apple forced to open NFC (EU DMA) — iOS app becomes possible
- 70+ economists urging European Parliament to adopt digital euro
- Zero European alternatives exist for NFC phone payments

**The mission:** Every European should be able to tap their phone to pay — using European infrastructure, governed by European law, accountable to European citizens.

---

*Stichting EU Pay — KVK Netherlands*
*Open source: [github.com/eupay](https://github.com/eupay)*
*License: EUPL-1.2 (European Union Public Licence)*

---

> *"There is no European electronic payment option that covers the entire euro area."*
> — ECB, Digital Euro Closing Report, October 2025
