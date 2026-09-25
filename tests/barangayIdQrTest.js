// Run from the repository root with JavaScriptCore's jsc (or another shell providing readFile).
const window = { location: { origin: 'https://example.test' } };
const appBase = '/barangay';
function firstNonEmpty(values) { return values.find(value => value != null && String(value).trim() !== '') || ''; }
function extractFunctions(path, startName, endName) {
  const source = readFile(path);
  const start = source.indexOf(`  function ${startName}(`);
  const end = source.indexOf(`  function ${endName}(`, start);
  if (start < 0 || end < 0) throw new Error('Missing QR helpers');
  return source.slice(start, end);
}
eval(extractFunctions('JS-Script-Files/Shared/barangayIdDigital.js', 'verificationUrl', 'mergeSampleData'));
eval(extractFunctions('JS-Script-Files/Admin-End/certificateTrackerScript.js', 'barangayIdVerificationUrl', 'additionalDetailRows'));
const cases = [
  [{ request_id: 'DRTEST', qr_code_path: '/old-invalid-qr.png' }, {}, ''],
  [{ request_id: 'DRTEST', verification_code: 'SAVED+CODE', qr_code_path: '/old-invalid-qr.png' }, { barangay_id_number: 'CUSTOM-123' }, 'SAVED+CODE'],
  [{ request_id: 'DRTEST' }, { verification_code: 'PAYLOAD-CODE' }, 'PAYLOAD-CODE'],
  [{ verification_code: 'CODE' }, {}, ''],
];
for (const [row, payload, code] of cases) {
  const expected = code ? `https://example.test/barangay/transactions?request_id=DRTEST&vc=${encodeURIComponent(code)}` : '';
  if (verificationUrl(appBase, row, payload) !== expected || barangayIdVerificationUrl(row, payload) !== expected) throw new Error('Wrong verification URL');
  const shared = qrPreviewUrl(appBase, row, payload);
  const admin = barangayIdQrPreviewUrl(row, payload);
  if (shared.primary !== admin || shared.fallback !== '') throw new Error('QR renderers disagree');
  if (!expected && admin !== '') throw new Error('Generated QR without a saved code');
  if (expected && decodeURIComponent(admin.split('data=')[1]) !== expected) throw new Error('QR contains wrong URL');
}
print('PASS: missing codes produce no QR; saved codes are encoded; stale images and assigned ID numbers cannot change the QR.');
const trackerSource = readFile('JS-Script-Files/Admin-End/certificateTrackerScript.js');
const guardStart = trackerSource.indexOf('  async function requireBarangayIdQrImages(');
const guardEnd = trackerSource.indexOf('  async function exportIdForEpsonPhotoPlus(', guardStart);
eval(trackerSource.slice(guardStart, guardEnd));
async function testPrintGuard() {
  const card = image => ({ querySelectorAll: () => [{ querySelector: () => image }] });
  for (const image of [null, { complete: true, naturalWidth: 0 }]) {
    let rejected = false;
    try { await requireBarangayIdQrImages(card(image)); } catch (_) { rejected = true; }
    if (!rejected) throw new Error('Printing allowed without a loaded QR');
  }
  await requireBarangayIdQrImages(card({ complete: true, naturalWidth: 220 }));
  await requireBarangayIdQrImages({ querySelectorAll: () => [] });
  print('PASS: print guard rejects missing/broken QR images and permits loaded QR images and front-only artwork.');
}
testPrintGuard().catch(error => { print(error); quit(1); });
