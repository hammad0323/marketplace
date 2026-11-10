// Re-renders PNG logo/favicons/OG image from the SVG sources (requires Node + Playwright).
const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage();
  const dir = '/home/user/marketplace/assets/images/';
  async function shot(svgFile, w, h, out, bg) {
    const svg = fs.readFileSync(dir + svgFile, 'utf8');
    await p.setViewportSize({ width: w, height: h });
    await p.setContent(`<html><body style="margin:0;background:${bg||'transparent'}"><div style="width:${w}px;height:${h}px">${svg.replace('<svg ', `<svg width="${w}" height="${h}" `)}</div></body></html>`);
    await p.screenshot({ path: out, omitBackground: !bg, clip: {x:0,y:0,width:w,height:h} });
  }
  await shot('logo-icon.svg', 512, 512, dir + 'logo-icon.png');
  await shot('logo-icon.svg', 180, 180, dir + 'apple-touch-icon.png');
  await shot('logo-icon.svg', 48, 48, '/tmp/claude-0/fav48.png');
  await shot('logo-icon.svg', 32, 32, '/tmp/claude-0/fav32.png');
  await shot('logo-icon.svg', 16, 16, '/tmp/claude-0/fav16.png');
  await shot('logo.svg', 804, 192, dir + 'logo.png');
  // OG default image
  const logo = fs.readFileSync(dir + 'logo-white.svg', 'utf8').replace('<svg ', '<svg width="670" height="160" ');
  await p.setViewportSize({ width: 1200, height: 630 });
  await p.setContent(`<html><body style="margin:0"><div style="width:1200px;height:630px;background:linear-gradient(135deg,#0f766e,#134e4a);display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:Arial;color:#fff">${logo}<p style="font-size:34px;margin-top:40px;opacity:.95">School Books · Stationery · Monthly Rashan · Home Delivery</p></div></body></html>`);
  await p.screenshot({ path: dir + 'og-default.png' });
  await b.close();
})();
