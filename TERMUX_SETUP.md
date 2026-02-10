# Termux → GitHub Setup Guide

## One-time setup (run once)

```bash
# Install git and openssh
pkg update && pkg upgrade -y
pkg install git openssh -y

# Set your identity
git config --global user.name "Your Name"
git config --global user.email "your@email.com"

# Generate SSH key (recommended over HTTPS)
ssh-keygen -t ed25519 -C "your@email.com"
# Press Enter 3 times (default location, no passphrase)

# Print your public key — copy this to GitHub
cat ~/.ssh/id_ed25519.pub
```

**Add the SSH key to GitHub:**
1. Go to: https://github.com/settings/keys
2. Click "New SSH key"
3. Paste the key from above
4. Save

## Create repo and push

```bash
# Navigate to downloads (where eu-pay zip was saved)
cd ~/storage/downloads
# Or if you saved it elsewhere:
# cd /sdcard/Download

# Unzip the project
unzip eu-pay-v2.2.zip
cd eu-pay

# Initialize git repo
git init
git add -A
git commit -m "Stichting EU Pay v2.2 — PSD2 + 7 EU card issuers + Digital Euro

Three-layer European payment architecture:
- PSD2 Open Banking: 140+ EU/EEA banks (AISP/PISP)
- Card Issuing: 7 EU-licensed providers (Marqeta, Adyen, Stripe, Enfuce, Wallester, Paynetics, Nexi)
- Digital Euro: ECB CBDC preparedness (pilot 2027, launch 2029)

Features:
- NFC tap-to-pay via HCE (Host Card Emulation)
- Zero-knowledge encryption (RSA-4096 + AES-256-GCM)
- Symfony 8.0 + PHP 8.4 + Kotlin Android
- Kubernetes + ArgoCD deployment
- Full EU compliance (GDPR, PSD2 SCA, AML 6AMLD, ePrivacy)
- Stichting (Dutch foundation) — open source, EUPL-1.2"

# OPTION A: Create repo on GitHub first, then push
# Go to https://github.com/new → create "eu-pay" (empty, no README)
git branch -M main
git remote add origin git@github.com:YOUR_USERNAME/eu-pay.git
git push -u origin main

# OPTION B: Using GitHub CLI (alternative)
# pkg install gh
# gh auth login
# gh repo create eu-pay --public --source=. --push
```

## If you already have a repo and want to update

```bash
cd ~/storage/downloads/eu-pay
# Or wherever your repo is cloned

# Remove old files, copy new ones
git rm -r --cached .
# Extract new zip over existing directory
cp -r /path/to/new/eu-pay/* .
git add -A
git commit -m "v2.2: Add 7 EU card issuers + Digital Euro + Pitch Deck"
git push
```

## Enable storage access (if needed)

```bash
# Run this once if ~/storage doesn't exist
termux-setup-storage
# Grant permission when Android asks
```

## Verify push

```bash
git log --oneline -5
git remote -v
```
