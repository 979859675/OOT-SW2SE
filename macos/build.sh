#!/bin/zsh
# build.sh
#
# Builds "OOT SW2SE.app" for macOS from OOTSW2SE.swift and the upstream
# ../editor.html (bundled unchanged). macOS counterpart of ../build.ps1.
# Needs only the Xcode command-line tools (swiftc, lipo, iconutil, python3);
# no Xcode project is involved.
#
# Usage: macos/build.sh            (output: macos/dist/OOT SW2SE.app)
# The binary is universal (Apple silicon + Intel) and ad-hoc signed, which is
# enough to run on the Mac that built it; see README.md for other Macs.
# The app is assembled in .build first and only replaces the one in dist
# once every step has succeeded, so a failed build keeps the last good app.

set -euo pipefail

# Resolve paths from this script's location, not the caller's directory.
here="$(cd "$(dirname "$0")" && pwd)"
repo="$(dirname "$here")"
build="$here/.build"
dist="$here/dist"
name="OOT SW2SE.app"
staged="$build/$name"

# Take the version from AssemblyVersion("x.y.z.w") in ../Program.cs, the
# upstream source of truth, keeping the first three parts like the Windows
# build does (Version.ToString(3)).
version="$(sed -n 's/.*AssemblyVersion("\([0-9]*\.[0-9]*\.[0-9]*\).*/\1/p' "$repo/Program.cs" | head -n 1)"
if [[ -z "$version" ]]; then
  echo "error: no AssemblyVersion found in $repo/Program.cs" >&2
  exit 1
fi
# Oldest macOS the app supports (WKWebView APIs used need 13+).
min_macos="13.0"

rm -rf "$build"
mkdir -p "$staged/Contents/MacOS" "$staged/Contents/Resources"

# Compile one slice per architecture, then merge them into a universal binary.
# Swift 6 mode turns data-race warnings into errors, so they can't creep in.
for arch in arm64 x86_64; do
  swiftc -O -swift-version 6 \
    -module-cache-path "$build/ModuleCache" \
    -target "$arch-apple-macos$min_macos" \
    -o "$build/OOTSW2SE-$arch" \
    "$here/OOTSW2SE.swift"
done
lipo -create -output "$staged/Contents/MacOS/OOT SW2SE" "$build"/OOTSW2SE-arm64 "$build"/OOTSW2SE-x86_64

# The editor page is used as-is; the version placeholder is filled in at runtime.
cp "$repo/editor.html" "$staged/Contents/Resources/editor.html"

# Ship the project's MIT license inside the app, as build.ps1 does for the
# Windows build (it places LICENSE.txt next to the executable).
if [[ ! -f "$repo/LICENSE" ]]; then
  echo "error: $repo/LICENSE not found" >&2
  exit 1
fi
cp "$repo/LICENSE" "$staged/Contents/Resources/LICENSE.txt"

# Build the .icns from the Windows .ico. Its images are PNGs (16, 24, 32, 48,
# 64, 128 and 256 px): each is extracted as ico-<size>.png and used as-is
# where macOS wants that pixel size, so small icons keep their own artwork
# instead of a downscaled 256 px image. Only 512 and 1024 px are upscaled
# from 256, so those look slightly soft on Retina.
python3 -I - "$repo/editor.ico" "$build" <<'PY'
import struct, sys
data = open(sys.argv[1], 'rb').read()
count = struct.unpack_from('<H', data, 4)[0]
for i in range(count):
    width, _, _, _, _, _, size, offset = struct.unpack_from('<BBBBHHII', data, 6 + 16 * i)
    image = data[offset:offset + size]
    if image.startswith(b'\x89PNG\r\n\x1a\n'):  # skip legacy BMP entries
        open(f'{sys.argv[2]}/ico-{width or 256}.png', 'wb').write(image)
PY
largest="$build/ico-256.png"
if [[ ! -f "$largest" ]]; then
  echo "error: $repo/editor.ico has no 256 px PNG image" >&2
  exit 1
fi
iconset="$build/AppIcon.iconset"
mkdir -p "$iconset"
# Copies the native image for pixel size $1 to $2, or scales the largest one.
icon_image() {
  if [[ -f "$build/ico-$1.png" ]]; then
    cp "$build/ico-$1.png" "$2"
  else
    sips -z "$1" "$1" "$largest" --out "$2" >/dev/null
  fi
}
for size in 16 32 128 256 512; do
  icon_image "$size" "$iconset/icon_${size}x${size}.png"
  icon_image "$((size * 2))" "$iconset/icon_${size}x${size}@2x.png"
done
iconutil -c icns "$iconset" -o "$staged/Contents/Resources/AppIcon.icns"

cat > "$staged/Contents/Info.plist" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>CFBundleName</key><string>OOT SW2SE</string>
  <key>CFBundleDisplayName</key><string>OOT SW2SE</string>
  <key>CFBundleExecutable</key><string>OOT SW2SE</string>
  <key>CFBundleIdentifier</key><string>local.oot.sw2se</string>
  <key>CFBundlePackageType</key><string>APPL</string>
  <key>CFBundleShortVersionString</key><string>$version</string>
  <key>CFBundleVersion</key><string>$version</string>
  <key>CFBundleIconFile</key><string>AppIcon</string>
  <key>LSMinimumSystemVersion</key><string>$min_macos</string>
  <key>LSApplicationCategoryType</key><string>public.app-category.utilities</string>
  <key>NSHighResolutionCapable</key><true/>
  <key>NSHumanReadableCopyright</key><string>Copyright © 2026 Traiver. MIT License.</string>
</dict>
</plist>
PLIST

codesign --force --sign - "$staged"

# Everything succeeded: swap the new app in for the old one.
mkdir -p "$dist"
rm -rf "$dist/$name"
mv "$staged" "$dist/$name"
echo "$dist/$name"
