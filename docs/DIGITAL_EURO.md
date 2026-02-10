# Digital Euro — EU Pay Integration Roadmap

## What Is the Digital Euro?

A central bank digital currency (CBDC) issued by the European Central Bank (ECB)
for retail payments across the euro area. Think "digital cash" — issued by a public
institution, free for consumers, with cash-like privacy protections.

## Current Status (February 2026)

| Milestone | Date | Status |
|-----------|------|--------|
| Investigation phase | Oct 2021 – Oct 2023 | ✅ Completed |
| Preparation phase | Nov 2023 – Oct 2025 | ✅ Completed |
| ECB Governing Council: next phase | 29 Oct 2025 | ✅ Decided |
| EU Parliament vote on Digital Euro Regulation | H1 2026 | ⏳ Pending |
| Call for PSP expression of interest | Q1 2026 | ⏳ In progress |
| ECB TSP workshops (technical service providers) | Mar 2026 | ⏳ Scheduled |
| Pilot with selected PSPs | H2 2027 (12 months) | 🔮 Planned |
| Potential first issuance | 2029 | 🔮 Target |

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│  EUROSYSTEM (ECB + 20 national central banks)                   │
│  ● Issues digital euro                                          │
│  ● Manages DESP (Digital Euro Service Platform)                 │
│  ● Settlement, alias lookup, fraud management, tokenization     │
│  ● Cannot see user balances or payment patterns (privacy)       │
└────────────────────────┬────────────────────────────────────────┘
                         │ Digital Euro Access Gateway
                         │ RESTful APIs (Berlin Group NextGenPSD2)
                         │ ISO 20022 messaging
┌────────────────────────▼────────────────────────────────────────┐
│  PAYMENT SERVICE PROVIDERS (PSPs) — intermediaries              │
│  ● Onboarding + KYC/KYB                                        │
│  ● Digital Euro Account (DEA) management                       │
│  ● Transaction processing                                      │
│  ● Fraud & dispute management                                  │
│  ● EU Pay operates at this layer (or via PSP partner)           │
└────────────────────────┬────────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────────┐
│  END USERS (consumers + merchants)                              │
│  ● Dedicated ECB app OR PSP's own app (e.g., EU Pay app)       │
│  ● NFC tap-to-pay at POS                                       │
│  ● QR code payments                                            │
│  ● P2P via alias (phone/email)                                 │
│  ● E-commerce payments                                         │
│  ● Offline device-to-device payments (no internet)             │
└─────────────────────────────────────────────────────────────────┘
```

## Key Features

- **Holding limit**: Up to €3,000 per person (tested by ECB — no financial stability risk)
- **Interest rate**: 0% — DEA is interest-free by design
- **Free for consumers**: Basic use is free; merchants pay capped fees
- **Privacy**: Pseudonymous identifiers; ECB cannot track balances or patterns
- **Online payments**: POS (NFC/QR), e-commerce, P2P
- **Offline payments**: Device-to-device without internet (pre-funded)
- **Pan-European**: Works across all 20 euro area countries instantly

## EU Pay Three-Rail Payment Architecture

When the digital euro launches, EU Pay will offer **three payment rails**:

```
┌─────────────────────────────────────────────────────────────────┐
│  RAIL 1: PSD2 Open Banking (AISP/PISP) — TODAY ✅              │
│  ● Top-up from any EU/EEA bank (iDEAL, SEPA)                  │
│  ● P2P transfers to any IBAN                                   │
│  ● Account balance & transaction history                       │
│  ● 153 banks across 30 countries                               │
├─────────────────────────────────────────────────────────────────┤
│  RAIL 2: Card Issuing (Marqeta/Adyen/Stripe/Enfuce/...) — ✅  │
│  ● Visa/Mastercard virtual debit cards                         │
│  ● NFC tap-to-pay via HCE (Android)                            │
│  ● DPAN tokenization + EMV session keys                        │
│  ● 7 EU-licensed providers supported                           │
├─────────────────────────────────────────────────────────────────┤
│  RAIL 3: Digital Euro (ECB CBDC) — 2029 🔮                    │
│  ● Instant pan-European payments (no card network fees)         │
│  ● NFC + QR at POS                                             │
│  ● P2P via phone/email alias                                   │
│  ● Offline payments without internet                           │
│  ● €3,000 holding limit, 0% interest                           │
│  ● Interface ready — stub active, swap when DESP API goes live │
└─────────────────────────────────────────────────────────────────┘
```

## Implementation in EU Pay

### Current (pre-launch)

```
App\Service\DigitalEuro\DigitalEuroInterface    ← abstraction
App\Service\DigitalEuro\DigitalEuroStub         ← returns NOT_AVAILABLE
App\Controller\DigitalEuroController            ← /api/digital-euro/status
```

The stub returns regulatory parameters and timeline info. The Android app
can show a "Coming Soon" card with the ECB timeline, building user awareness.

### Post-launch (2027 pilot / 2029 issuance)

1. Replace `DigitalEuroStub` with `DigitalEuroDesp` (DESP API client)
2. One line change in `services.yaml`:
   ```yaml
   App\Service\DigitalEuro\DigitalEuroInterface:
       alias: App\Service\DigitalEuro\DigitalEuroDesp  # was: DigitalEuroStub
   ```
3. Zero changes to controllers, Android app, or other services

### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/digital-euro/status` | GET | Availability + regulation parameters |
| `/api/digital-euro/account` | POST | Open DEA (503 pre-launch) |
| `/api/digital-euro/balance/{deaId}` | GET | DEA balance |
| `/api/digital-euro/pay/p2p` | POST | P2P payment via alias |
| `/api/digital-euro/pay/pos` | POST | POS payment (NFC/QR) |

## Technical Standards

The digital euro will use:

- **Berlin Group NextGenPSD2** — RESTful API standard (same as our PSD2 layer)
- **ISO 20022** — Messaging standard for settlement
- **eIDAS** — Electronic identification for onboarding
- **Nexo standards** — POS/ATM terminal protocols

This means EU Pay's existing PSD2 infrastructure (eIDAS certificates, Berlin Group
API knowledge, SEPA connectivity) is directly reusable for digital euro integration.

## Competitive Advantage for EU Pay

The digital euro eliminates card network fees (Visa/Mastercard interchange),
making it cheaper than card-based NFC payments. However, card payments will
still be needed for non-euro transactions and countries outside the euro area.

EU Pay's three-rail architecture means users can choose:
- **Digital euro** for zero-fee euro area payments (2029+)
- **Visa/MC card** for international payments and non-euro merchants
- **PSD2 bank transfer** for large amounts exceeding the €3,000 DEA limit

## References

- [ECB Digital Euro Progress](https://www.ecb.europa.eu/euro/digital_euro/progress/html/index.en.html)
- [ECB Press Release: Next Phase (Oct 2025)](https://www.ecb.europa.eu/press/pr/date/2025/html/ecb.pr251030~8c5b5beef0.en.html)
- [Preparation Phase Closing Report](https://www.ecb.europa.eu/euro/digital_euro/progress/html/ecb.deprp202510.en.html)
- [Digital Euro Regulation Proposal (COM/2023/369)](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:52023PC0369)
- [ECB Blog: A digital euro for the digital age (Dec 2025)](https://www.ecb.europa.eu/press/blog/date/2025/html/ecb.blog20251209~9ba130ff20.en.html)
- [Call for TSP Workshops (Jan 2026)](https://www.ecb.europa.eu/press/intro/news/html/ecb.mipnews260127.en.html)
