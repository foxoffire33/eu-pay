#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────────────
#  EU Pay — Build & Run (backend + Android on emulator)
# ──────────────────────────────────────────────────────────

PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"
ANDROID_DIR="$PROJECT_ROOT/android"
APK_PATH="$ANDROID_DIR/app/build/outputs/apk/debug/app-debug.apk"
PACKAGE="nl.delaparra_services.apps.eupay.debug"

# Emulator reaches host via 10.0.2.2; nginx dev port 8080 (HTTP, no TLS)
API_BASE_URL="http://10.0.2.2:8080"

# ── Colors ──
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# ── 1. Start Docker Compose ──────────────────────────────
echo ""
echo "═══════════════════════════════════════════"
echo "  EU Pay — Build & Run"
echo "═══════════════════════════════════════════"
echo ""

info "Starting Docker Compose services..."
cd "$PROJECT_ROOT"
docker compose up -d --build 2>&1 | tail -5
info "Docker services running."

# ── 2. Wait for backend health ────────────────────────────
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

# ── 3. Run backend tests ─────────────────────────────────
info "Running PHPUnit tests..."
if docker exec eupay-php php vendor/bin/phpunit --testdox 2>&1 | tail -3; then
    info "Backend tests passed."
else
    warn "Backend tests had failures (check output above)."
fi

# ── 4. Check emulator ────────────────────────────────────
info "Checking for connected Android device/emulator..."
if ! adb devices 2>/dev/null | grep -q "device$"; then
    error "No Android device/emulator found. Start one with: emulator -avd <name>"
fi
DEVICE=$(adb devices | grep "device$" | head -1 | awk '{print $1}')
info "Found device: $DEVICE"

# ── 5. Build Android APK ─────────────────────────────────
info "Building Android debug APK (API_BASE_URL=$API_BASE_URL)..."
cd "$ANDROID_DIR"
./gradlew assembleDebug --no-daemon -PAPI_BASE_URL="$API_BASE_URL" 2>&1 | tail -5
if [ ! -f "$APK_PATH" ]; then
    error "APK not found at $APK_PATH"
fi
info "APK built successfully."

# ── 6. Run Android unit tests ────────────────────────────
info "Running Android unit tests..."
if ./gradlew testDebugUnitTest --no-daemon 2>&1 | tail -3; then
    info "Android tests passed."
else
    warn "Android tests had failures (check output above)."
fi

# ── 7. Install & Launch ──────────────────────────────────
info "Installing APK on $DEVICE..."
adb -s "$DEVICE" install -r "$APK_PATH" 2>&1
info "APK installed."

info "Launching EU Pay..."
adb -s "$DEVICE" shell am start -a android.intent.action.MAIN \
    -c android.intent.category.LAUNCHER \
    -n "$PACKAGE/nl.delaparra_services.apps.eupay.MainActivity" 2>/dev/null \
    || adb -s "$DEVICE" shell monkey -p "$PACKAGE" -c android.intent.category.LAUNCHER 1 2>/dev/null

echo ""
info "EU Pay is running!"
echo ""
echo "  Backend API:  http://localhost:8080  (HTTPS: https://localhost)"
echo "  Landing page: http://localhost:3000"
echo "  Emulator API: $API_BASE_URL"
echo "  Logs:         docker compose logs -f php"
echo ""
