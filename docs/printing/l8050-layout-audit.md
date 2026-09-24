# L8050 layout audit — 24 September 2026

Status: PSD-derived artwork placement; physical printer alignment unverified.

## Evidence from `1 YEAR.psd`

The source file was read locally. No resident names, images, or signatures are included in this report or the calibration PDF.

- Document: 3508 × 2480 pixels, RGB, 300 ppi.
- Photoshop resources 1083 and 1062 independently agree: print scale 97.27822580645162%, left offset 3 points (1.058333 mm), top offset 0. Legacy resource 1062 indicates fit-to-page. The scale is already baked into the generated layout; do not apply fit-to-page again.
- Resource 1085 contains Windows DEVMODE: L8050 Series (Copy 1), A4 (paper code 9), landscape (orientation 2), 100% driver scale, one copy, simplex, 720 × 720 dpi.
- The standard paper-source field is code 4 (manual selection). The proprietary Epson settings are not decoded; the PSD does not independently establish the physical tray coordinates or the driver's printable-area origin.
- Both front background layers measure 1036 × 659 pixels; both back backgrounds measure 1030 × 656. Decoding each layer's alpha channel confirms its nontransparent extent reaches these bounds. These are artwork rectangles, not a specification for the plastic card or its tray.

## Reconstructed page coordinates

All measurements are from the top-left of a 297 × 210 mm landscape page, assuming the saved Photoshop offset is applied at that origin. A driver-origin difference must be measured, not guessed.

`mm per PSD pixel = 25.4 / 300 × 0.9727822580645162`

`left = 1.058333 + PSD x × mm per pixel`; `top = PSD y × mm per pixel`.

| Artwork | PSD x, y, width, height (px) | Left mm | Top mm | Width mm | Height mm |
|---|---|---:|---:|---:|---:|
| Front 1 | 433, 812, 1036, 659 | 36.721 | 66.878 | 85.327 | 54.277 |
| Front 2 | 437, 1695, 1036, 659 | 37.051 | 139.604 | 85.327 | 54.277 |
| Back 1 | 428, 812, 1030, 656 | 36.309 | 66.878 | 84.833 | 54.030 |
| Back 2 | 440, 1698, 1030, 656 | 37.298 | 139.851 | 84.833 | 54.030 |

The slight front/back differences are present in the source file. They are retained rather than replaced with guessed identical rectangles. Template positions 1 and 2 refer to upper/lower artwork on this page; their correspondence to physical tray slot labels must be verified.

## Implementation corrections

- Fit each generated ID within its PSD artwork rectangle without stretching or cropping. This can leave a small unprinted strip because the application ID and PSD artwork have different aspect ratios. Preserve the complete ID and QR code rather than inventing bleed.
- Scale artwork around its center so size adjustments do not unexpectedly move its center. X/Y shifts remain independent.
- Use separate calibration values for each side and position. Calibration storage moves to version 2 because scale anchoring changed; old adjustments are not silently reused.
- Page preview displays the selected artwork rectangle and opposite-side reference. It is a diagram, not a physical-size screen proof.
- Paper alignment pages include a 100 mm ruler outside the artwork. Never send the paper alignment page to the PVC tray; normal ID jobs omit the ruler and labels.
- Browser print CSS uses A4 landscape and zero page margins. It cannot force the Epson driver, disable driver expansion, select the physical slot, or silently bypass the print dialog.

## Printer test procedure

1. Print the calibration PDF on ordinary A4 paper at Actual size / 100%, landscape. Do not use fit/shrink-to-page or automatic duplex. Measure the 100 mm ruler. If it is not 100 mm, fix scaling before adjusting positions.
2. Compare the PSD output and application output using the same printer/driver settings. Measure left/top and width/height. Use X/Y for a position error, size for a size error. The saved PSD driver source is not a verified PVC profile.
3. On the L8050, select Disc/ID Card Tray and compatible PVC ID Card media. Check the driver preview retains the intended page and orientation. If A4 landscape is unavailable or remapped, stop using this profile and use Epson Photo+; coordinate translation for that driver mode must first be established.
4. Use one test card in the corresponding slot. Confirm which physical slot matches the chosen template position. Print the front, reload the same card for the back, and establish flip orientation. Enable 180-degree back rotation only if needed. Do not print both sides simultaneously into two slots.
5. Check all edges, photo, signature, small text and QR scanning. Confirm both sides and both positions separately. Do not mark an issued ID ready for claim based only on opening a print dialog.

## Sources

- [Adobe PSD specification](https://www.adobe.com/devnet-apps/photoshop/fileformatashtml/): resource records and relative-distance units (72 units/inch).
- [Microsoft DEVMODE](https://learn.microsoft.com/en-us/windows/win32/api/wingdi/ns-wingdi-devmodew): printer settings record.
- [Epson L8050 PVC printing instructions](https://download4.epson.biz/sec_pubs/l8050_series/useg/en/GUID-616CE2FC-0C0E-4626-B3B1-39FB8B2823C3.htm): Disc/ID Card Tray and PVC ID Card media.
