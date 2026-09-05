/**
 * Extract transparent brand marks from photo exports (black background).
 * Usage:
 *   node scripts/extract-brand-marks.mjs <green.jpg> <white.jpg>
 */
import sharp from 'sharp';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const brand = path.join(__dirname, '..', 'public', 'brand');

async function extractMark(src, dest, mode) {
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
      if (lum < 28) {
        data[i + 3] = 0;
      } else {
        if (mode === 'white') {
          data[i] = 255;
          data[i + 1] = 255;
          data[i + 2] = 255;
        }
        if (x < minX) minX = x;
        if (y < minY) minY = y;
        if (x > maxX) maxX = x;
        if (y > maxY) maxY = y;
      }
    }
  }

  const pad = 12;
  minX = Math.max(0, minX - pad);
  minY = Math.max(0, minY - pad);
  maxX = Math.min(info.width - 1, maxX + pad);
  maxY = Math.min(info.height - 1, maxY + pad);

  await sharp(data, { raw: info })
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
    .toFile(dest);

  console.log('wrote', dest);
}

const green = process.argv[2];
const white = process.argv[3];
if (!green || !white) {
  console.error('Usage: node scripts/extract-brand-marks.mjs <green.jpg> <white.jpg>');
  process.exit(1);
}

await extractMark(green, path.join(brand, 'neatmeet-mark.png'), 'green');
await extractMark(white, path.join(brand, 'neatmeet-mark-white.png'), 'white');
