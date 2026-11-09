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
let paths = ["files/slot_00/progress.sav", "files/slot_00/caption.sav", "files/option.sav", "manifest.json"]
let entries = paths.map { ["relativePath": $0, "contents": bytes.base64EncodedString()] }
let files = try PatchFolderWriter.decode(entries)
let first = try PatchFolderWriter.write(parent: parent, files: files)
let second = try PatchFolderWriter.write(parent: parent, files: files)
precondition(first != second)
for folder in [first, second] { for path in paths { precondition(try Data(contentsOf: folder.appendingPathComponent(path)) == bytes) } }
for bad in ["../progress.sav", "/progress.sav", "files/../../caption.sav", "files\\progress.sav", "files/slot_06/progress.sav", "progress.sav\n"] {
    do { _ = try PatchFolderWriter.decode([["relativePath": bad, "contents": "AQ=="], entries[1]]); fatalError("Accepted invalid path") }
    catch {}
}
do { _ = try PatchFolderWriter.decode([entries[0], entries[0]]); fatalError("Accepted duplicate") } catch {}
do { _ = try PatchFolderWriter.decode([["relativePath":"progress.sav", "contents":Data(repeating: 1, count: HostLimits.maxFileBytes + 1).base64EncodedString()], entries[1]]); fatalError("Accepted oversized file") } catch {}
print("PASS Swift: nested export bytes, unique destinations, invalid paths, duplicates and size limits")
'''
    # A throwing expression cannot be evaluated inside precondition's autoclosure.
    source = source.replace('precondition(try Data(contentsOf: folder.appendingPathComponent(path)) == bytes)', 'let actual = try Data(contentsOf: folder.appendingPathComponent(path)); precondition(actual == bytes)')
    (work / 'main.swift').write_text(source)
    subprocess.run(['swiftc', '-swift-version', '6', '-module-cache-path', str(work / 'cache'), str(work / 'main.swift'), '-o', str(work / 'swift-test')], check=True)
    subprocess.run([str(work / 'swift-test'), str(work)], check=True)

    cs = (root / 'Program.cs').read_text()
    helper = cs.split('internal static class PatchFolderWriter', 1)[1].split('internal static class FolderPicker', 1)[0]
    source = '''using System; using System.IO; using System.Collections.Generic;
internal static class PatchFolderWriter''' + helper + r'''
class Test {
  static void Main(string[] args) {
    var bytes = new byte[] {0,1,2,255};
    var paths = new[] {"files/slot_00/progress.sav", "files/slot_00/caption.sav", "files/option.sav", "manifest.json"};
    var entries = Array.ConvertAll(paths, p => new PatchFolderWriter.Entry { relativePath=p, contents=Convert.ToBase64String(bytes) });
    var files = PatchFolderWriter.Decode(entries);
    var first = PatchFolderWriter.Write(args[0], files);
    var second = PatchFolderWriter.Write(args[0], files);
    if (first == second) throw new Exception("Reused destination");
    foreach (var folder in new[] {first,second}) foreach (var path in paths)
      if (Convert.ToBase64String(File.ReadAllBytes(Path.Combine(folder,path))) != Convert.ToBase64String(bytes)) throw new Exception("Bytes differ");
    foreach (var bad in new[] {"../progress.sav", "/progress.sav", "files/../../caption.sav", "files\\progress.sav", "files/slot_06/progress.sav", "progress.sav\n"}) {
      try { PatchFolderWriter.Decode(new[] {new PatchFolderWriter.Entry { relativePath=bad, contents="AQ==" }, entries[1]}); }
      catch (IOException) { continue; }
      throw new Exception("Accepted invalid path");
    }
    bool rejected = false;
    try { PatchFolderWriter.Decode(new[] {entries[0],entries[0]}); } catch (IOException) { rejected=true; }
    if (!rejected) throw new Exception("Accepted duplicate");
    rejected = false;
    try { PatchFolderWriter.Decode(new[] {new PatchFolderWriter.Entry { relativePath="progress.sav", contents=Convert.ToBase64String(new byte[2*1024*1024+1]) }, entries[1]}); } catch (IOException) { rejected=true; }
    if (!rejected) throw new Exception("Accepted oversized file");
    Console.WriteLine("PASS C#: nested export bytes, unique destinations, invalid paths, duplicates and size limits");
  }
}
'''
    (work / 'Test.cs').write_text(source)
    subprocess.run(['mcs', '-out:' + str(work / 'cs-test.exe'), str(work / 'Test.cs')], check=True)
    subprocess.run(['mono', str(work / 'cs-test.exe'), str(work)], check=True)
