/**
 * Build PWA icons from the canonical brand mark.
 * Green rounded tile + cream mark silhouette.
 * Run: node scripts/build-brand-icons.mjs
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import sharp from 'sharp';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const markPath = path.join(root, 'public', 'brand', 'neatmeet-mark.png');
const GREEN = '#2f5a45';

async function makeGreenIcon(outPath, size) {
  const pad = Math.round(size * 0.18);
  const inner = size - pad * 2;
  const radius = Math.round(size * 0.22);

  const { data, info } = await sharp(markPath)
    .resize(inner, inner, {
      fit: 'contain',
      background: { r: 0, g: 0, b: 0, alpha: 0 },
    })
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  for (let i = 0; i < data.length; i += 4) {
    const a = data[i + 3];
    // Keep clearly inked green pixels; drop residual fill / checkerboard
    const isInk = a > 40 && data[i + 1] < 140;
    if (isInk) {
      data[i] = 245;
      data[i + 1] = 241;
      data[i + 2] = 232;
    } else {
      data[i + 3] = 0;
    }
  }

  const creamMark = await sharp(data, { raw: info }).png().toBuffer();
  const svg = Buffer.from(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}">` +
      `<rect width="${size}" height="${size}" rx="${radius}" fill="${GREEN}"/>` +
      `</svg>`,
  );

  await sharp(svg)
    .composite([{ input: creamMark, gravity: 'centre' }])
    .png()
    .toFile(outPath);

  console.log('wrote', path.relative(root, outPath));
}

async function main() {
  if (!fs.existsSync(markPath)) {
    throw new Error(`Missing mark at ${markPath}`);
  }

  const targets = [
    path.join(root, 'public', 'admin-icons'),
    path.join(root, 'public', 'member-icons'),
    path.join(root, 'public', 'brand'),
  ];

  for (const dir of targets) {
    fs.mkdirSync(dir, { recursive: true });
    await makeGreenIcon(path.join(dir, 'icon-192.png'), 192);
    await makeGreenIcon(path.join(dir, 'icon-512.png'), 512);
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
