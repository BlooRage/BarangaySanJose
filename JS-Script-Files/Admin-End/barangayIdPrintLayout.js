(() => {
  // 1 YEAR.psd: 3508 x 2480 px, 300 ppi, 97.27822580645162% print scale.
  // Print descriptor Left = 3 points (Adobe #Rlt, 72 units/inch), Top = 0.
  // Bounds come from the front/back background layers, not resident text layers.
  // Resource 1085 (Windows DEVMODE) confirms A4 landscape and 100% driver scale.
  // These are artwork bounds, NOT independently measured physical tray slots.
  // Printer-driver printable-origin differences still require physical calibration.
  const bounds = {
    front: { 1: [433, 812, 1036, 659], 2: [437, 1695, 1036, 659] },
    back: { 1: [428, 812, 1030, 656], 2: [440, 1698, 1030, 656] }
  };
  const finite = (value, fallback, min, max) => {
    const number = Number(value);
    return Number.isFinite(number) ? Math.min(max, Math.max(min, number)) : fallback;
  };
  window.BarangayIdPrintLayout = {
    geometry(side, slot, settings = {}) {
      side = side === 'back' ? 'back' : 'front';
      slot = Number(slot) === 2 ? 2 : 1;
      const [x, y, width, height] = bounds[side][slot];
      const mm = 25.4 / 300 * 0.9727822580645162;
      const factor = finite(settings.size, 100, 95, 105) / 100;
      return {
        // Size adjustments keep the artwork center fixed; X/Y control translation.
        x: 3 * 25.4 / 72 + x * mm + finite(settings.x, 0, -20, 20) - width * mm * (factor - 1) / 2,
        y: y * mm + finite(settings.y, 0, -20, 20) - height * mm * (factor - 1) / 2,
        width: width * mm * factor,
        height: height * mm * factor,
        rotation: side === 'back' && settings.rotateBack === true ? 180 : 0
      };
    },
    pagePreview(side, slot, settings = {}) {
      const box = this.geometry(side, slot, settings);
      const other = this.geometry(side === 'back' ? 'front' : 'back', slot);
      const f = value => value.toFixed(2);
      return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 297 210" role="img" aria-label="A4 landscape placement preview" style="width:100%;max-width:560px;border:1px solid #64748b;background:white">
        <rect x="${f(other.x)}" y="${f(other.y)}" width="${f(other.width)}" height="${f(other.height)}" fill="none" stroke="#94a3b8" stroke-width="0.4" stroke-dasharray="2 1"/>
        <rect x="${f(box.x)}" y="${f(box.y)}" width="${f(box.width)}" height="${f(box.height)}" fill="#e0f2fe" fill-opacity="0.6" stroke="#0369a1" stroke-width="0.4"/>
        <path d="M0 ${f(box.y)}H${f(box.x)} M${f(box.x)} 0V${f(box.y)}" stroke="#475569" stroke-width="0.3" stroke-dasharray="1 1"/>
        <text x="${f(box.x + 2)}" y="${f(box.y + 6)}" font-size="3.5">${f(box.width)} × ${f(box.height)} mm</text>
        <text x="${f(box.x + 2)}" y="${f(box.y + 12)}" font-size="3.5">Left ${f(box.x)} / Top ${f(box.y)} mm</text>
        <text x="160" y="18" font-size="4">A4 landscape · 297 × 210 mm</text>
        <text x="160" y="26" font-size="3.5">Blue: selected side and adjustments</text>
        <text x="160" y="33" font-size="3.5">Dashed: opposite side, PSD default</text>
      </svg>`;
    },
    alignmentReference() {
      // This ruler is outside the artwork. Use only on paper alignment sheets.
      return `<div style="position:absolute;left:160mm;top:50mm;width:100mm;font:10pt Arial">
        <div style="width:100mm;height:5mm;border:solid black;border-width:0 0.2mm 0.2mm;box-sizing:border-box"></div>
        <p>Measure this line: exactly 100 mm.<br>If it differs, correct print scaling first.<br>Paper test only — not a PVC tray print.</p>
      </div>`;
    }
  };
})();
