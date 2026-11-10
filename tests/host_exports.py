"""Exercise the actual C# and Swift export helpers without native file dialogs."""
from pathlib import Path
import subprocess
import tempfile

root = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='oot-export-tests-') as temp:
    work = Path(temp)
    swift = (root / 'macos/OOTSW2SE.swift').read_text()
    helper = swift.split('enum PatchFolderWriter {', 1)[1].split('// MARK: - Editor window', 1)[0]
    source = '''import Foundation
struct HostError: LocalizedError { let message: String; init(_ message: String) { self.message = message }; var errorDescription: String? { message } }
enum HostLimits { static let maxFileBytes = 2 * 1024 * 1024; static let maxBackupBytes = 8 * 1024 * 1024 }
enum PatchFolderWriter {''' + helper + r'''
let parent = URL(fileURLWithPath: CommandLine.arguments[1])
let bytes = Data([0, 1, 2, 255])
let name = "Ocarina-of-Time-slot_00-Patch-20261109_094216_11111111-1111-4111-8111-111111111111"
let otherName = name.replacingOccurrences(of: "11111111-1111", with: "22222222-2222")
let paths = ["20261109_094216/slot_00/progress.sav", "20261109_094216/slot_00/caption.sav", "20261109_094216/option.sav", "manifest.json"]
let entries = paths.map { ["relativePath": $0, "contents": bytes.base64EncodedString()] }
let files = try PatchFolderWriter.decode(entries, name: name)
let first = try PatchFolderWriter.write(parent: parent, files: files, name: name)
let second = try PatchFolderWriter.write(parent: parent, files: files, name: otherName)
precondition(first.lastPathComponent == name && second.lastPathComponent == otherName)
do { _ = try PatchFolderWriter.write(parent: parent, files: files, name: name); fatalError("Overwrote destination") } catch {}
for bad in ["../escape", name + "/child", name + "\n"] {
    do { _ = try PatchFolderWriter.decode(entries, name: bad); fatalError("Accepted invalid name") } catch {}
}
for folder in [first, second] { for path in paths { precondition(try Data(contentsOf: folder.appendingPathComponent(path)) == bytes) } }
for bad in ["../progress.sav", "/progress.sav", "files/../../caption.sav", "files\\progress.sav", "20261109_094216/slot_06/progress.sav", "20261109_094217/progress.sav", "progress.sav\n"] {
    do { _ = try PatchFolderWriter.decode([["relativePath": bad, "contents": "AQ=="], entries[1]], name: name); fatalError("Accepted invalid path") }
    catch {}
}
do { _ = try PatchFolderWriter.decode([entries[0], entries[0]], name: name); fatalError("Accepted duplicate") } catch {}
do { _ = try PatchFolderWriter.decode([["relativePath":"20261109_094216/progress.sav", "contents":Data(repeating: 1, count: HostLimits.maxFileBytes + 1).base64EncodedString()], entries[1]], name: name); fatalError("Accepted oversized file") } catch {}
print("PASS Swift: nested export bytes, unique destinations, invalid paths, duplicates and size limits")
'''
    # A throwing expression cannot be evaluated inside precondition's autoclosure.
    source = source.replace('precondition(try Data(contentsOf: folder.appendingPathComponent(path)) == bytes)', 'let actual = try Data(contentsOf: folder.appendingPathComponent(path)); precondition(actual == bytes)')
    (work / 'main.swift').write_text(source)
    subprocess.run(['swiftc', '-swift-version', '6', '-module-cache-path', str(work / 'cache'), str(work / 'main.swift'), '-o', str(work / 'swift-test')], check=True)
    (work / 'swift-output').mkdir()
    subprocess.run([str(work / 'swift-test'), str(work / 'swift-output')], check=True)

    cs = (root / 'Program.cs').read_text()
    helper = cs.split('internal static class PatchFolderWriter', 1)[1].split('internal static class FolderPicker', 1)[0]
    source = '''using System; using System.IO; using System.Collections.Generic;
internal static class PatchFolderWriter''' + helper + r'''
class Test {
  static void Main(string[] args) {
    var bytes = new byte[] {0,1,2,255};
    var name = "Ocarina-of-Time-slot_00-Patch-20261109_094216_11111111-1111-4111-8111-111111111111";
    var otherName = name.Replace("11111111-1111", "22222222-2222");
    var paths = new[] {"20261109_094216/slot_00/progress.sav", "20261109_094216/slot_00/caption.sav", "20261109_094216/option.sav", "manifest.json"};
    var entries = Array.ConvertAll(paths, p => new PatchFolderWriter.Entry { relativePath=p, contents=Convert.ToBase64String(bytes) });
    var files = PatchFolderWriter.Decode(entries, name);
    var first = PatchFolderWriter.Write(args[0], files, name);
    var second = PatchFolderWriter.Write(args[0], files, otherName);
    if (Path.GetFileName(first) != name || Path.GetFileName(second) != otherName) throw new Exception("Incorrect name");
    bool overwritten = true;
    try { PatchFolderWriter.Write(args[0], files, name); } catch (IOException) { overwritten=false; }
    if (overwritten) throw new Exception("Overwrote destination");
    foreach (var badName in new[] {"../escape", name + "/child", name + "\n"}) {
      try { PatchFolderWriter.Decode(entries, badName); } catch (IOException) { continue; }
      throw new Exception("Accepted invalid name");
    }
    foreach (var folder in new[] {first,second}) foreach (var path in paths)
      if (Convert.ToBase64String(File.ReadAllBytes(Path.Combine(folder,path))) != Convert.ToBase64String(bytes)) throw new Exception("Bytes differ");
    foreach (var bad in new[] {"../progress.sav", "/progress.sav", "files/../../caption.sav", "files\\progress.sav", "20261109_094216/slot_06/progress.sav", "20261109_094217/progress.sav", "progress.sav\n"}) {
      try { PatchFolderWriter.Decode(new[] {new PatchFolderWriter.Entry { relativePath=bad, contents="AQ==" }, entries[1]}, name); }
      catch (IOException) { continue; }
      throw new Exception("Accepted invalid path");
    }
    bool rejected = false;
    try { PatchFolderWriter.Decode(new[] {entries[0],entries[0]}, name); } catch (IOException) { rejected=true; }
    if (!rejected) throw new Exception("Accepted duplicate");
    rejected = false;
    try { PatchFolderWriter.Decode(new[] {new PatchFolderWriter.Entry { relativePath="20261109_094216/progress.sav", contents=Convert.ToBase64String(new byte[2*1024*1024+1]) }, entries[1]}, name); } catch (IOException) { rejected=true; }
    if (!rejected) throw new Exception("Accepted oversized file");
    Console.WriteLine("PASS C#: nested export bytes, unique destinations, invalid paths, duplicates and size limits");
  }
}
'''
    (work / 'Test.cs').write_text(source)
    subprocess.run(['mcs', '-out:' + str(work / 'cs-test.exe'), str(work / 'Test.cs')], check=True)
    (work / 'cs-output').mkdir()
    subprocess.run(['mono', str(work / 'cs-test.exe'), str(work / 'cs-output')], check=True)
