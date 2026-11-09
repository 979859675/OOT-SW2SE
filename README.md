# OOT SW2SE

Save editor for the Nintendo Switch 2 remake of The Legend of Zelda: Ocarina of Time.

Original editor by [Traiver](https://github.com/Traiver/OOT-SW2SE) and upstream contributors.

![OOT SW2SE showing the stats of a loaded save](editor-screenshot.jpg)

- Edit rupees, hearts, magic & more.
- Change item quantities, bottles, small keys, and bag or quiver capacity.
- Add missing items, including masks, with their acquisition flags set automatically.
- Remove supported owned single items, optionally clearing their mapped acquisition flags. Acquisition history is kept by default; story and quest progress are not reset. Unequip weapons and shields in both eras first. Default clothes, quantities, and capacity upgrades use their own controls rather than inventory removal.
- Export replacement files as a ZIP or to a new patch folder on Windows and macOS. Folder export preserves the same relative paths and does not overwrite the source backup. It contains only replacement files, not a complete backup.
- Toggle progress flags and change the spoken dialogue language when `option.sav` is available.

## Changes in this fork

2026-11-09 · 20:42 (UTC+8)

- Added removal of supported owned single items on Windows and macOS.
- Added an optional acquisition-flag reset when removing an item; acquisition history is preserved by default.
- Added folder export on both platforms alongside ZIP export, preserving relative paths in a new patch folder.
- Added regression tests for save editing and both native folder exporters.
- Built macOS v1.0.1 for Apple silicon and Intel; verified removal, acquisition-flag reset, and folder export through the app with a synthetic save.

Clearing acquisition flags does not rewind story events or quest progress. Windows UI and in-game behavior have not yet been verified.
