/**
 * Extract green brand mark from a photo on black background,
 * then build a matching white mark for dark UI.
 *
 * Usage: node scripts/import-nav-logo.mjs <source.jpg>
 */
import sharp from 'sharp';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const brand = path.join(__dirname, '..', 'public', 'brand');
const src = process.argv[2];

if (!src || !fs.existsSync(src)) {
  console.error('Usage: node scripts/import-nav-logo.mjs <source.jpg>');
  process.exit(1);
}

const { data, info } = await sharp(src)
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

let minX = info.width;
let minY = info.height;
let maxX = 0;
let maxY = 0;

for (let y = 0; y < info.height; y++) {
  for (let x = 0; x < info.width; x++) {
    const i = (y * info.width + x) * 4;
    const lum = (data[i] + data[i + 1] + data[i + 2]) / 3;
    // Black studio background -> transparent
    if (lum < 35) {
      data[i + 3] = 0;
    } else {
      // Keep green ink as-is; tighten alpha for anti-aliased edges
      if (data[i + 3] < 255) data[i + 3] = 255;
      if (x < minX) minX = x;
      if (y < minY) minY = y;
      if (x > maxX) maxX = x;
      if (y > maxY) maxY = y;
    }
  }
}

const pad = 16;
minX = Math.max(0, minX - pad);
minY = Math.max(0, minY - pad);
maxX = Math.min(info.width - 1, maxX + pad);
maxY = Math.min(info.height - 1, maxY + pad);

const cropped = await sharp(data, { raw: info })
  .extract({
    left: minX,
    top: minY,
    width: maxX - minX + 1,
    height: maxY - minY + 1,
  })
  .resize(512, 512, {
    fit: 'contain',
    background: { r: 0, g: 0, b: 0, alpha: 0 },
  })
  .png()
  .toBuffer();

const greenPath = path.join(brand, 'neatmeet-mark.png');
const whitePath = path.join(brand, 'neatmeet-mark-white.png');
const navPath = path.join(brand, 'neatmeet-nav.png');

await sharp(cropped).png().toFile(greenPath);
await sharp(cropped).png().toFile(navPath);

const { data: wData, info: wInfo } = await sharp(cropped)
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

for (let i = 0; i < wData.length; i += 4) {
  if (wData[i + 3] > 20) {
    wData[i] = 255;
    wData[i + 1] = 255;
    wData[i + 2] = 255;
  }
}

await sharp(wData, { raw: wInfo }).png().toFile(whitePath);

console.log('wrote', greenPath);
console.log('wrote', whitePath);
console.log('wrote', navPath);
