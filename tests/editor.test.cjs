const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const html = fs.readFileSync(require('node:path').join(__dirname, '../editor.html'), 'utf8');
const catalog = JSON.parse(html.match(/<script id="catalog" type="application\/json">(.*?)<\/script>/s)[1]);
const context = vm.createContext({ TextEncoder, TextDecoder, Blob,
  window: { chrome: { webview: { addEventListener() {}, postMessage() {} } } } });
vm.runInContext(html.match(/<script>\s*('use strict';.*?)<\/script>/s)[1], context);
const api = vm.runInContext('({readSlot, updateSlot, editOptions, removalIssue, acquisitionFlags, zip})', context);
const editable = api.editOptions(catalog);
const item = name => catalog.inventory.find(row => row[1] === name)[0];
const registration = hash => catalog.registrations.find(([stocks]) => stocks.includes(hash));

function save(headerEnd, fields) {
  const strings = fields.filter(([, type]) => ['String32', 'String64'].includes(catalog.types[type]));
  const bytes = new Uint8Array(headerEnd + strings.length * 64 + 4);
  const view = new DataView(bytes.buffer);
  view.setUint32(0, 0x01020304, true);
  view.setUint32(4, 0x002D80EB, true);
  view.setUint32(8, headerEnd, true);
  let body = headerEnd;
  fields.forEach(([hash, type], i) => {
    view.setUint32(40 + i * 8, hash, true);
    if (['String32', 'String64'].includes(catalog.types[type])) {
      view.setUint32(44 + i * 8, body, true);
      body += 64;
    }
  });
  return bytes;
}
function fixture(names = ['Item_Boomerang']) {
  const p = save(0x175D8, catalog.fields);
  const captionHashes = [0xA9557547, 0x1E55ECC5, 0x74BD05D0, 0x19F26B6A, 0x943F8EC3,
    0xBD46F485, 0xDFD840D3, 0x9811A3F7, 0x23F3D75E, 0x27853BF7, 0x25F03CAA, 0x63696A32];
  const c = save(0x2A0, captionHashes.map(h => [h, 2]));
  const cv = new DataView(c.buffer);
  for (const hash of [0x1E55ECC5, 0x74BD05D0]) cv.setInt32(44 + captionHashes.indexOf(hash) * 8, 12, true);
  cv.setUint32(44 + captionHashes.indexOf(0x63696A32) * 8, 0x2A0, true);
  const offsets = new Map(catalog.fields.map(([h], i) => [h, 44 + i * 8]));
  const view = new DataView(p.buffer);
  const set = (h, v) => view.setInt32(offsets.get(h), v, true);
  const string = (h, text) => {
    const o = view.getUint32(offsets.get(h), true);
    p.fill(0, o, o + 64); p.set(new TextEncoder().encode(text), o);
  };
  string(0x3E42AFFA, 'Item_Wallet_Small');
  set(0xE0010A50, 200); set(0x1D74617E, 1);
  for (const flag of registration(item('Item_Wallet_Small')).slice(1, 3)) set(flag, 1);
  set(0x31AB5580, 12); set(0xFBE01DA1, 12);
  for (const name of names) {
    const hash = item(name); set(hash, 1);
    for (const flag of registration(hash).slice(1, 3)) set(flag, 1);
  }
  return { p, c, set, string, slot: () => api.readSlot(p, c) };
}
function changes(slot, extra = {}) {
  return { wallet: slot.w.id, rupees: slot.rupees, vitals: slot.vitals,
    amounts: slot.counts, bottles: 0, additions: [], removals: [], clearAcquisitions: [], flags: [], ...extra };
}
function edit(f, extra) {
  const slot = f.slot();
  return api.readSlot(...api.updateSlot(slot, changes(slot, extra), editable));
}
function changedHashes(before, after) {
  return catalog.fields.filter(([h]) => before.int(h) !== after.int(h)).map(([h]) => h).sort((a,b) => a-b);
}

test('removal preserves acquisition history and changes only the selected stock', () => {
  const f = fixture(); const h = item('Item_Boomerang'); const before = f.slot();
  const after = edit(f, { removals: [h] });
  assert.equal(after.p.int(h), 0);
  for (const flag of registration(h).slice(1,3)) assert.equal(after.p.int(flag), 1);
  assert.deepEqual(changedHashes(before.p, after.p), [h]);
  assert.deepEqual(after.c.bytes, before.c.bytes);
  assert.equal(f.slot().p.int(h), 1, 'source backup stays intact');
});

test('optional reset clears exactly the two mapped flags and stays cleared after another export', () => {
  const f = fixture(); const h = item('Item_Boomerang'); const before = f.slot();
  const after = edit(f, { removals: [h], clearAcquisitions: [h] });
  assert.deepEqual(changedHashes(before.p, after.p), [h, ...registration(h).slice(1,3)].sort((a,b)=>a-b));
  for (const flag of registration(h).slice(1,3)) assert.equal(after.p.int(flag), 0);
  const again = api.readSlot(...api.updateSlot(after, changes(after), editable));
  assert.equal(again.p.int(h), 0);
  for (const flag of registration(h).slice(1,3)) assert.equal(again.p.int(flag), 0);
});

test('adding an item still registers acquisition flags', () => {
  const f = fixture([]); const h = item('Item_Boomerang');
  const after = edit(f, { additions: [h] });
  for (const value of [h, ...registration(h).slice(1,3)]) assert.equal(after.p.int(value), 1);
});

test('reject flag-only resets, contradictory progress edits, and add/remove conflicts', () => {
  const f = fixture(); const h = item('Item_Boomerang'); const flag = registration(h)[1];
  assert.throws(() => edit(f, { clearAcquisitions: [h] }), /requires removing/);
  assert.throws(() => edit(f, { removals: [h], clearAcquisitions: [h], flags: [[flag, true]] }), /conflicts/);
  assert.throws(() => edit(f, { removals: [h], additions: [h] }), /cannot be added/);
});

test('reject removal of equipped weapons in either era and default clothes', () => {
  const h = item('Weapon_Sword_Kokiri');
  for (const field of [0xA95A3521, 0xA1F7EE2B]) {
    const f = fixture(['Weapon_Sword_Kokiri']); f.string(field, 'Weapon_Sword_Kokiri');
    assert.throws(() => edit(f, { removals: [h] }), /Unequip/);
  }
  const f = fixture(['Armor_Adult_Normal']);
  assert.throws(() => edit(f, { removals: [item('Armor_Adult_Normal')] }), /supported/);
});

test('reject unsupported stocks, non-boolean acquisition flags and shared flags still in use', () => {
  const f = fixture(); const h = item('Item_Boomerang'); const flag = registration(h)[1];
  assert.throws(() => edit(f, { removals: [item('Item_Wallet_Small')] }), /supported/);
  f.set(flag, 2);
  assert.throws(() => edit(f, { removals: [h], clearAcquisitions: [h] }), /safely/);
  const g = fixture(['Item_Boomerang', 'Item_Hammer']); const slot = g.slot();
  const shared = { ...editable, registrations: [...catalog.registrations, [[item('Item_Hammer')], flag, 0, 'Shared test']] };
  assert.throws(() => api.updateSlot(slot, changes(slot, {removals:[h], clearAcquisitions:[h]}), shared), /shared/);
});

module.exports = { fixture };
