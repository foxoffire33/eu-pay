#!/usr/bin/env bash
set -euo pipefail

ANDROID_SDK="${ANDROID_HOME:-$HOME/Android/Sdk}"
EMULATOR="$ANDROID_SDK/emulator/emulator"
ADB="$ANDROID_SDK/platform-tools/adb"
AVD_NAME="${1:-Medium_Phone_API_36.1}"
APP_ID="nl.delaparra_services.apps.eupay.debug"
ACTIVITY="com.example.eupay.ui.MainActivity"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BOOT_TIMEOUT=120

# ── Kill stale emulator if running ───────────────────────────────
if "$ADB" devices 2>/dev/null | grep -q "emulator-"; then
    echo ":: Killing stale emulator..."
    "$ADB" emu kill 2>/dev/null || true
    sleep 3
fi

# ── Verify AVD exists ────────────────────────────────────────────
if ! "$EMULATOR" -list-avds 2>/dev/null | grep -qx "$AVD_NAME"; then
    echo "ERROR: AVD '$AVD_NAME' not found. Available:"
    "$EMULATOR" -list-avds
    exit 1
fi

# ── Start emulator ───────────────────────────────────────────────
echo ":: Starting emulator ($AVD_NAME)..."
"$EMULATOR" -avd "$AVD_NAME" -no-snapshot-load -gpu auto &>/dev/null &
EMULATOR_PID=$!

# ── Wait for device and boot ────────────────────────────────────
echo ":: Waiting for device..."
"$ADB" wait-for-device

echo ":: Waiting for boot (timeout ${BOOT_TIMEOUT}s)..."
elapsed=0
while [ $elapsed -lt $BOOT_TIMEOUT ]; do
    BOOT=$("$ADB" shell getprop sys.boot_completed 2>/dev/null | tr -d '\r' || true)
    if [ "$BOOT" = "1" ]; then
        break
    fi
    sleep 3
    elapsed=$((elapsed + 3))
done

if [ "$BOOT" != "1" ]; then
    echo "ERROR: Emulator did not boot within ${BOOT_TIMEOUT}s"
    kill "$EMULATOR_PID" 2>/dev/null || true
    exit 1
fi
echo ":: Emulator booted."

# ── Build & install ──────────────────────────────────────────────
echo ":: Building debug APK..."
"$SCRIPT_DIR/gradlew" -p "$SCRIPT_DIR" installDebug -q

# ── Launch app ───────────────────────────────────────────────────
echo ":: Launching EU Pay..."
"$ADB" shell am start -n "$APP_ID/$ACTIVITY"

echo ":: Done. EU Pay is running on $AVD_NAME."
