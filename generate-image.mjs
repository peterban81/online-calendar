import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const baseUrl = process.env.POSTER_URL ||
  'https://eventi.impegnopercampoformido.it/calendario-eventi/index.php?render=1';

const output = process.env.OUTPUT_JPG ||
  path.resolve(process.cwd(), 'output/prossimi-eventi.jpg');

const quality = Number(process.env.JPG_QUALITY || '96');
const scale = Number(process.env.DEVICE_SCALE_FACTOR || '2');

fs.mkdirSync(path.dirname(output), { recursive: true });

const browser = await chromium.launch({
  headless: true,
  args: [
    '--disable-dev-shm-usage',
    '--no-sandbox',
    '--font-render-hinting=none'
  ]
});

try {
  const page = await browser.newPage({
    viewport: { width: 1080, height: 1600 },
    deviceScaleFactor: scale
  });

  await page.goto(baseUrl, {
    waitUntil: 'networkidle',
    timeout: 60000
  });

  await page.emulateMedia({ media: 'screen' });

  await page.waitForFunction(() => {
    const poster = document.querySelector('#poster');
    const cards = document.querySelectorAll('.event-card');
    return Boolean(poster) && cards.length > 0;
  }, null, { timeout: 30000 });

  await page.evaluate(async () => {
    if (document.fonts?.ready) {
      await document.fonts.ready;
    }
  });

  const poster = page.locator('#poster');
  await poster.screenshot({
    path: output,
    type: 'jpeg',
    quality,
    animations: 'disabled'
  });

  console.log(`JPG generato: ${output}`);
} finally {
  await browser.close();
}
