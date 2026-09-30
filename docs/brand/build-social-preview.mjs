// Regenerates social-preview.png (1280x640) from connie.svg.
//
//   npm install --no-save @resvg/resvg-js
//   node docs/brand/build-social-preview.mjs
//
// Then upload the PNG, since GitHub's social preview accepts raster only:
//   gh api -X PATCH repos/contactout/campaigns -F 'source=@social-preview.png'
import fs from 'node:fs';
import { Resvg } from '@resvg/resvg-js';

// GitHub social preview: 1280x640. Must be raster (PNG/JPG) — GitHub does not
// accept SVG for the social preview image.
//
// Layout: dark charcoal field, Connie centred-left, project name + tagline right.
const W = 1280;
const H = 640;

// Verbatim Connie geometry from connie.svg alongside this file (viewBox 0 0 20 16).
const connie = fs.readFileSync(
    new URL('./connie.svg', import.meta.url),
    'utf8',
);
const inner = connie
    .replace(/^[\s\S]*?<svg[^>]*>/, '')
    .replace(/<\/svg>\s*$/, '')
    .trim();

// Connie sits optically centred against the text block: her 300x240 box is
// centred on the 640 canvas height, nudged up slightly to match the cap-height
// of "Campaigns" rather than the full text column.
const connieGroup = `<g transform="translate(132 176) scale(15)">${inner}</g>`;

const svg = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" fill="none" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#16191B"/>
      <stop offset="1" stop-color="#0A0C0D"/>
    </linearGradient>
  </defs>
  <rect width="${W}" height="${H}" fill="url(#bg)"/>
  ${connieGroup}
  <text x="640" y="272" font-family="Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif" font-size="80" font-weight="700" fill="#FFFFFF" letter-spacing="-1.5">Campaigns</text>
  <text x="640" y="326" font-family="Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif" font-size="29" font-weight="500" fill="#28E06E">Self-hosted email outreach</text>
  <text x="640" y="384" font-family="Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif" font-size="24" font-weight="400" fill="#9AA3A6">Multi-step sequences from your own inboxes,</text>
  <text x="640" y="418" font-family="Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif" font-size="24" font-weight="400" fill="#9AA3A6">with replies and bounces detected automatically.</text>
  <rect x="640" y="452" width="56" height="3" rx="1.5" fill="#28E06E" fill-opacity="0.55"/>
  <text x="640" y="500" font-family="Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif" font-size="21" font-weight="600" fill="#5A6366">contactout.github.io/campaigns</text>
</svg>`;

fs.writeFileSync(new URL('./social-preview.svg', import.meta.url), svg);

const r = new Resvg(svg, {
    fitTo: { mode: 'width', value: W },
    background: '#0A0C0D',
    font: { loadSystemFonts: true },
});
const img = r.render();
fs.writeFileSync(new URL('./social-preview.png', import.meta.url), img.asPng());
console.log('social-preview.png', img.width + 'x' + img.height);
