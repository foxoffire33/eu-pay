#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────────────
#  EU Pay — Build & Run on real Android device
# ──────────────────────────────────────────────────────────

PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"
ANDROID_DIR="$PROJECT_ROOT/android"
APK_PATH="$ANDROID_DIR/app/build/outputs/apk/debug/app-debug.apk"
PACKAGE="nl.delaparra_services.apps.eupay.debug"
ACTIVITY="nl.delaparra_services.apps.eupay.ui.MainActivity"

# ── Colors ──
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# ── 1. Detect local network IP ───────────────────────────
echo ""
echo "═══════════════════════════════════════════"
echo "  EU Pay — Real Device Build & Run"
echo "═══════════════════════════════════════════"
echo ""

LOCAL_IP=$(ip -4 addr show scope global | grep -oP '(?<=inet\s)192\.168\.\d+\.\d+' | head -1)
if [ -z "$LOCAL_IP" ]; then
    LOCAL_IP=$(ip -4 addr show scope global | grep -oP '(?<=inet\s)\d+\.\d+\.\d+\.\d+' | head -1)
fi
if [ -z "$LOCAL_IP" ]; then
    error "Could not detect local network IP. Check your network connection."
fi

API_BASE_URL="http://${LOCAL_IP}:8080"
info "Local IP: $LOCAL_IP"
info "API URL:  $API_BASE_URL"

# ── 2. Find real device (skip emulators) ──────────────────
info "Looking for real device..."
DEVICE=""
while IFS= read -r line; do
    dev=$(echo "$line" | awk '{print $1}')
    state=$(echo "$line" | awk '{print $2}')
    # Skip emulators and non-device entries
    if [[ "$dev" == emulator-* ]] || [[ "$state" != "device" ]]; then
        continue
    fi
    DEVICE="$dev"
    break
done < <(adb devices 2>/dev/null | tail -n +2 | grep -v "^$")

if [ -z "$DEVICE" ]; then
    error "No real device found. Connect via USB and enable USB debugging."
fi
info "Found device: $DEVICE"

# ── 3. Start Docker Compose ──────────────────────────────
info "Starting Docker Compose services..."
cd "$PROJECT_ROOT"
docker compose up -d --build 2>&1 | tail -5
info "Docker services running."

# ── 4. Wait for backend health ────────────────────────────
info "Waiting for backend (port 8080)..."
RETRIES=30
until curl -sf http://localhost:8080/health > /dev/null 2>&1; do
    RETRIES=$((RETRIES - 1))
    if [ "$RETRIES" -le 0 ]; then
        error "Backend did not become healthy on port 8080"
    fi
    sleep 1
done
info "Backend is healthy."

# ── 5. Build Android APK ─────────────────────────────────
info "Building Android debug APK..."
cd "$ANDROID_DIR"
./gradlew assembleDebug --no-daemon -PAPI_BASE_URL="$API_BASE_URL" 2>&1 | tail -5
if [ ! -f "$APK_PATH" ]; then
    error "APK not found at $APK_PATH"
fi
info "APK built successfully."

# ── 6. Install & Launch ──────────────────────────────────
info "Installing APK on $DEVICE..."
adb -s "$DEVICE" install -r "$APK_PATH" 2>&1
info "APK installed."

info "Launching EU Pay..."
adb -s "$DEVICE" shell am force-stop "$PACKAGE" 2>/dev/null || true
adb -s "$DEVICE" shell am start -a android.intent.action.MAIN \
    -c android.intent.category.LAUNCHER \
    -n "$PACKAGE/$ACTIVITY" 2>&1

echo ""
info "EU Pay is running on device $DEVICE!"
echo ""
echo "  Backend API:  $API_BASE_URL"
echo "  Landing page: http://localhost:3000"
echo "  Device logs:  adb -s $DEVICE logcat --pid=\$(adb -s $DEVICE shell pidof $PACKAGE)"
echo ""
