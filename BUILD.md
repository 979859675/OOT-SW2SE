# Build OOT SW2SE

Use Windows x64 with .NET Framework 4.8 and the Microsoft Edge WebView2 Runtime. From PowerShell in this folder, run:

```powershell
.\build.ps1
```

The script verifies the pinned Microsoft WebView2 SDK package, builds the executable, and places it with the WebView2 license in `dist`. The application works offline. The build downloads the SDK once, unless you pass a local copy with `-WebView2Package`.

`Program.cs` contains the native window and local file dialogs. `editor.html` contains the English editor interface, save parser, and patch generator.
