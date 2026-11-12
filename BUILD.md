# 构建 OOT SW2SE

## Windows

需要 Windows x64、.NET Framework 4.8 和 Microsoft Edge WebView2 运行时。在此目录中打开 PowerShell 并运行：

```powershell
.\build.ps1
```

脚本验证固定版本的 Microsoft WebView2 SDK 包，编译可执行文件，并将其连同项目许可证（`LICENSE.txt`）和 WebView2 许可证（`WebView2-LICENSE.txt`）放入 `dist`。应用可离线使用。首次构建需下载 SDK，也可通过 `-WebView2Package` 指定本地 SDK 包。

`Program.cs` 包含原生窗口和本地文件对话框；`editor.html` 包含简体中文编辑界面、存档解析器和补丁生成器。源文件使用 UTF-8 编码。

原始编辑器软件采用 MIT 许可证。WebView2 和游戏图像等第三方内容保留各自的许可条款。

## macOS

需要 macOS 13 或更新版本，以及 Xcode 命令行工具（`xcode-select --install`）。在此目录的终端中运行：

```sh
macos/build.sh
```

脚本构建同时支持 Apple silicon 和 Intel 的通用应用，为构建所在的 Mac 进行临时签名，并将应用放入 `macos/dist/OOT SW2SE.app`，项目许可证包含在应用内。它直接打包相同的 `editor.html`，版本号读取自 `Program.cs`。

GitHub Actions 也运行此脚本（[`.github/workflows/macos.yml`](.github/workflows/macos.yml)）。可在 Actions 页的构建记录或发行版中下载应用；发行版附件名为 `OOT-SW2SE-<version>-macOS.zip`。这些构建采用临时签名，未经公证，首次启动会被 macOS 阻止；请在“系统设置 → 隐私与安全性”中选择“仍要打开”。

`macos/OOTSW2SE.swift` 包含原生窗口和文件对话框。使用方法以及在另一台 Mac 上运行的说明见 [macos/README.md](macos/README.md)。

## 检查

运行合成存档回归测试：

```sh
node --test tests/editor.test.cjs
```

在安装了 Swift 和 Mono 的 Mac 上，还可运行：

```sh
python3 tests/host_exports.py
```

此测试编译并执行实际的 Swift 和 C# 文件夹导出辅助代码，检查路径校验及输出字节是否一致。这些检查不能替代使用真实存档进行游戏内测试。

## 下载 Windows 打包成品

推送构建相关文件后，仓库 Actions 页的 **Windows build** 工作流会在 Windows x64 构建机上运行回归测试并执行 `build.ps1`，也可手动触发。
成功后，在该次运行的 Artifacts 区域下载 `OOT-SW2SE-zh-CN-Windows-x64`，解压得到打包 ZIP，再解压即可获得 `OOT SW2SE.exe` 及两份许可证。
构建产物保留 30 天，下载需要登录 GitHub。EXE 未进行代码签名，Windows 可能显示安全提示。运行仍需安装 Microsoft Edge WebView2 运行时。
