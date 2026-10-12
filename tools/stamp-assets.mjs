// Gives every script and stylesheet link in the pages a version taken from the file's
// content: <script src="app.js?v=1a2b3c4d5e">. The host's network keeps copies of .js and
// .css files; without this, a browser can get a new page together with an old script.
// The version only changes when the file does, so running this twice changes nothing.
//
//   node tools/stamp-assets.mjs <project-folder>
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const root = path.join(process.argv[2] ?? '.', 'public');
const pages = ['index.html', 'admin/index.html', 'form/index.html'];

/** The version of one file: the first 10 hex characters of its SHA-256. */
function assetVersion(file) {
  return crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex').slice(0, 10);
}

let changed = 0;
for (const page of pages) {
  const file = path.join(root, page);
  const html = fs.readFileSync(file, 'utf8');
  const stamped = html.replace(/\b(href|src)="((?!https?:|\/\/)[^"?#]+\.(?:css|js))(?:\?v=[0-9a-f]*)?"/g, (whole, attr, url) => {
    const asset = path.join(path.dirname(file), url);
    return fs.existsSync(asset) ? `${attr}="${url}?v=${assetVersion(asset)}"` : whole;
  });
  if (stamped !== html) {
    fs.writeFileSync(file, stamped);
    changed++;
    console.log('stamped ' + page);
  }
}
console.log(changed ? `${changed} page(s) updated` : 'asset versions are up to date');
