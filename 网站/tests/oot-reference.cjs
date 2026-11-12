// Only loaded by Node.js. Never expose this module or editor.html as static files.
const fs = require('node:fs');
const vm = require('node:vm');
const html = fs.readFileSync(require('node:path').join(__dirname, '../../editor.html'), 'utf8');
const catalog = JSON.parse(html.match(/<script id="catalog" type="application\/json">(.*?)<\/script>/s)[1]);
let script = html.match(/<script>\s*('use strict';.*?)<\/script>/s)[1];
script = script.slice(0, script.indexOf('const desktop =')) + script.slice(script.indexOf('const HEX ='));
const ctx = vm.createContext({ TextEncoder, TextDecoder, Blob, crypto: require('node:crypto').webcrypto });
vm.runInContext(script, ctx);
module.exports = { catalog, ...vm.runInContext('({readSlot,readOptions,updateSlot,updateOptions,editOptions,findBackupFiles,slotEdits,exportLayout,zip,patchManifest,captionImage,fieldValue,wallets,capacityGroups,quantityFields,magicStages,voiceLanguages,removalIssue})', ctx) };
