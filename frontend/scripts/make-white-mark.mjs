import sharp from 'sharp';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const brand = path.join(__dirname, '..', 'public', 'brand');
const mark = path.join(brand, 'neatmeet-mark.png');
const whiteOut = path.join(brand, 'neatmeet-mark-white.png');
const preview = path.join(brand, '_preview-white-on-dark.png');

const meta = await sharp(mark).metadata();
console.log('src', meta.width, meta.height, meta.hasAlpha);

const { data, info } = await sharp(mark)
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

let ink = 0;
let clear = 0;
for (let i = 0; i < data.length; i += 4) {
  const a = data[i + 3];
  const lum = (data[i] + data[i + 1] + data[i + 2]) / 3;
  const isInk = a > 40 && lum < 200;
  if (isInk) {
    data[i] = 255;
    data[i + 1] = 255;
    data[i + 2] = 255;
    data[i + 3] = 255;
    ink += 1;
  } else {
    data[i + 3] = 0;
    clear += 1;
  }
}
console.log({ ink, clear });

await sharp(data, { raw: info })
  .resize(512, 512, {
    fit: 'contain',
    background: { r: 0, g: 0, b: 0, alpha: 0 },
  })
  .png()
  .toFile(whiteOut);

const whiteBuf = await sharp(whiteOut)
  .resize(360, 360, {
    fit: 'contain',
    background: { r: 0, g: 0, b: 0, alpha: 0 },
  })
  .png()
  .toBuffer();

const svg = Buffer.from(
  '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512"><rect width="512" height="512" fill="#111111"/></svg>',
);

await sharp(svg)
  .composite([{ input: whiteBuf, gravity: 'centre' }])
  .png()
  .toFile(preview);

console.log('wrote', whiteOut);
console.log('preview', preview);
