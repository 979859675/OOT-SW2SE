# Build OOT SW2SE

Use Windows x64 with .NET Framework 4.8 and the Microsoft Edge WebView2 Runtime. From PowerShell in this folder, run:

```powershell
.\build.ps1
```

The script verifies the pinned Microsoft WebView2 SDK package, builds the executable, and places it with the project license (`LICENSE.txt`) and the WebView2 license (`WebView2-LICENSE.txt`) in `dist`. The application works offline. The build downloads the SDK once, unless you pass a local copy with `-WebView2Package`.

`Program.cs` contains the native window and local file dialogs. `editor.html` contains the English editor interface, save parser, and patch generator.

The MIT license applies to the original editor software. WebView2 and third-party material, such as game imagery, retain their own terms.
