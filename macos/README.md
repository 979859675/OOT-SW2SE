# OOT SW2SE for macOS

A native macOS window for the OOT SW2SE save editor. It loads the same
[`editor.html`](../editor.html) as the Windows version, unchanged, so the
editor itself behaves identically; only the window, the folder picker and the
save dialog are macOS code ([`OOTSW2SE.swift`](OOTSW2SE.swift)).

## Build

Requires macOS 13 or later and the Xcode command-line tools
(`xcode-select --install`). From the repository folder:

```sh
macos/build.sh
```

The app is written to `macos/dist/OOT SW2SE.app` (Apple silicon and Intel).
The version shown in the editor is read from `Program.cs`.

## Use

1. Open the app and click **Open Backup**.
2. Pick one of:
   - a backup folder (with `files/slot_00` … or `slot_00` … inside),
   - the `files` folder of a backup,
   - a single `slot_0X` folder,
   - any folder that holds `progress.sav` and `caption.sav`.
3. Edit, then **Save Patch ZIP** and copy the files from the ZIP over a
   **copy** of your saves.

Close the game or emulator first: a save file that changes while it is
being read is refused. Files stored in iCloud must be downloaded first.

## Running it on another Mac

The build is signed only for the Mac that built it ("ad-hoc"). Copied to
another Mac, macOS blocks it the first time: open it once, then allow it in
**System Settings → Privacy & Security → Open Anyway**. Sharing it widely
would need a Developer ID signature and notarization.

On macOS 26 and later the system shows the icon inside the standard rounded
square, as it does for every icon that isn't drawn in that shape.

## License

MIT, like the rest of the project: see [`LICENSE`](../LICENSE). The build
copies it into the app (`Contents/Resources/LICENSE.txt`); keep it with the
app when you share it. Game imagery shown by the editor keeps its own terms.
