(() => {
  // 1 YEAR.psd: 3508 x 2480 px, 300 ppi, 97.27822580645162% print scale.
  // Print descriptor Left = 3 points (Adobe #Rlt, 72 units/inch), Top = 0.
  // Bounds come from the front/back background layers, not resident text layers.
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
        x: 3 * 25.4 / 72 + x * mm + finite(settings.x, 0, -20, 20),
        y: y * mm + finite(settings.y, 0, -20, 20),
        width: width * mm * factor,
        height: height * mm * factor,
        rotation: side === 'back' && settings.rotateBack === true ? 180 : 0
      };
    }
  };
})();
