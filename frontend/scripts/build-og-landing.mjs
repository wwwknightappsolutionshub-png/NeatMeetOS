/**
 * Build WhatsApp/Facebook OG share image (1200×630).
 * Uses Anek Latin VF at weight 700 (Anek Gothic family used on the site)
 * rendered as SVG paths via fontkit, plus the NeatMeet logo mark.
 *
 * Run: node scripts/build-og-landing.mjs
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import * as fontkit from 'fontkit';
import sharp from 'sharp';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const brandDir = path.join(root, 'public', 'brand');
const fontPath = path.join(__dirname, '.fonts', 'AnekLatin-Variable.ttf');
const markPath = path.join(brandDir, 'neatmeet-mark.png');

const W = 1200;
const H = 630;
const GREEN = '#2f5a45';
const CREAM = '#f3f1ec';
const INK = '#1c1917';
const MUTED = '#57534e';
const SUBHEAD = 'Your Daily Beauty & Grooming Operations Made Easier';

function measure(font, text, fontSize) {
  const scale = fontSize / font.unitsPerEm;
  return font.layout(text).advanceWidth * scale;
}

function wrapWords(font, text, maxWidth, fontSize) {
  const words = text.split(/\s+/);
  const lines = [];
  let line = '';
  for (const word of words) {
    const next = line ? `${line} ${word}` : word;
    if (measure(font, next, fontSize) > maxWidth && line) {
      lines.push(line);
      line = word;
    } else {
      line = next;
    }
  }
  if (line) lines.push(line);
  return lines;
}

/** Render text as SVG path elements in Anek (y grows down). */
function textPaths(font, text, x, baselineY, fontSize, fill) {
  const scale = fontSize / font.unitsPerEm;
  const run = font.layout(text);
  let penX = 0;
  const parts = [];

  for (let i = 0; i < run.glyphs.length; i++) {
    const glyph = run.glyphs[i];
    const pos = run.positions[i];
    const d = glyph.path
      .scale(scale, -scale)
      .translate(x + penX + pos.xOffset * scale, baselineY - pos.yOffset * scale)
      .toSVG();
    if (d && d !== 'M0 0Z' && d.length > 2) {
      parts.push(`<path d="${d}" fill="${fill}"/>`);
    }
    penX += pos.xAdvance * scale;
  }

  return parts.join('\n  ');
}

async function main() {
  if (!fs.existsSync(fontPath)) {
    throw new Error(`Missing font at ${fontPath}`);
  }
  if (!fs.existsSync(markPath)) {
    throw new Error(`Missing mark at ${markPath}`);
  }

  const bold = fontkit.openSync(fontPath).getVariation({ wght: 700, wdth: 100 });
  const semi = fontkit.openSync(fontPath).getVariation({ wght: 600, wdth: 100 });
  const medium = fontkit.openSync(fontPath).getVariation({ wght: 500, wdth: 100 });

  const markPng = await sharp(markPath)
    .resize(112, 112, { fit: 'cover' })
    .png()
    .toBuffer();
  const markB64 = markPng.toString('base64');

  const creamW = 680;
  const textMax = creamW - 72 - 40;
  const subSize = 36;
  const subLines = wrapWords(semi, SUBHEAD, textMax, subSize);
  const subStartY = 270;
  const subLineH = 46;
  const subPaths = subLines
    .map((line, i) => textPaths(semi, line, 72, subStartY + i * subLineH, subSize, INK))
    .join('\n  ');

  const wordmark = textPaths(bold, 'NeatMeet OS', 204, 158, 42, INK);
  const trial = textPaths(medium, 'Start a 30-day free trial', 72, 545, 22, MUTED);

  const rightTitle1 = textPaths(bold, 'Salon operating', 740, 270, 38, '#ffffff');
  const rightTitle2 = textPaths(bold, 'system', 740, 322, 38, '#ffffff');
  const rightA = textPaths(medium, 'Bookings · Clients · Till', 740, 400, 22, '#d6e4db');
  const rightB = textPaths(medium, 'Memberships · Follow-up', 740, 436, 22, '#d6e4db');

  const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#1f3d30"/>
      <stop offset="55%" stop-color="${GREEN}"/>
      <stop offset="100%" stop-color="#24352c"/>
    </linearGradient>
  </defs>

  <rect width="${W}" height="${H}" fill="url(#bg)"/>
  <rect x="0" y="0" width="${creamW}" height="${H}" fill="${CREAM}"/>

  <image href="data:image/png;base64,${markB64}" x="72" y="88" width="112" height="112"/>

  ${wordmark}
  ${subPaths}
  <rect x="72" y="490" width="72" height="6" rx="3" fill="${GREEN}"/>
  ${trial}

  ${rightTitle1}
  ${rightTitle2}
  ${rightA}
  ${rightB}
</svg>`;

  const raster = await sharp(Buffer.from(svg))
    .resize(W, H)
    .jpeg({ quality: 86, mozjpeg: true })
    .toBuffer();

  const outJpg = path.join(brandDir, 'og-landing.jpg');
  const outPng = path.join(brandDir, 'og-landing.png');
  fs.writeFileSync(outJpg, raster);
  await sharp(raster).png({ compressionLevel: 9 }).toFile(outPng);

  const meta = await sharp(outJpg).metadata();
  console.log(
    JSON.stringify(
      {
        jpg: { path: outJpg, bytes: fs.statSync(outJpg).size, w: meta.width, h: meta.height },
        pngBytes: fs.statSync(outPng).size,
        subheadLines: subLines,
        font: 'Anek Latin wght 600/700 (Anek Gothic family)',
      },
      null,
      2,
    ),
  );
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
