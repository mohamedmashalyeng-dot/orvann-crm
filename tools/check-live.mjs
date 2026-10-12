// Checks a deployed CRM from the outside. Run by deploy.ps1 after every upload.
//
//   node tools/check-live.mjs <site-url> <project-folder> [--write-test]
//
// Read-only by default. With --write-test it also registers one test customer (a unique
// email that cannot match a real one) and deletes it again. The password and key are read
// from public/app/settings.php and never printed.
import fs from 'node:fs';
import crypto from 'node:crypto';

const [site, root, ...flags] = process.argv.slice(2);
const B = site.replace(/\/$/, '');
const writeTest = flags.includes('--write-test');

const settings = fs.readFileSync(`${root}/public/app/settings.php`, 'utf8');
const setting = (name) => new RegExp(`'${name}' => '([^']*)'`).exec(settings)[1];

let failed = 0;
const ok = (cond, msg) => { if (!cond) failed++; console.log((cond ? '  ok    ' : '  FAIL  ') + msg); return cond; };
const note = (msg) => console.log('        ' + msg);

async function request(path, opts = {}) {
  const res = await fetch(B + path, { redirect: 'manual', ...opts });
  const bytes = Buffer.from(await res.arrayBuffer());
  const text = bytes.toString('utf8');
  let json = null;
  try { json = JSON.parse(text); } catch {}
  return { status: res.status, headers: res.headers, bytes, text, json };
}
const post = (path, body, headers = {}) =>
  request(path, { method: 'POST', headers: { 'Content-Type': 'application/json', ...headers }, body: JSON.stringify(body) });
const sha = (buffer) => crypto.createHash('sha256').update(buffer).digest('hex');

await main();
// Set the exit code and let Node finish by itself: an abrupt process.exit() can crash it on
// Windows while a connection is still closing.
console.log(failed ? `\n${failed} check(s) failed` : '\nAll checks passed');
process.exitCode = failed ? 1 : 0;

async function main() {
// 1. The server can run it.
let r = await request('/api/index.php?r=health');
if (!ok(r.json?.ok === true, 'server is ready (PHP, SQLite, settings file, writable data folder)')) {
  const reasons = {
    settings: 'app/settings.php is missing on the server',
    pdo_sqlite: 'the SQLite extension is off: hPanel > PHP Configuration > Extensions > pdo_sqlite',
    data_dir: 'the server cannot write to the data folder',
  };
  note(r.json ? 'problem: ' + (reasons[r.json.problem] ?? r.json.problem) : `no answer from the API (HTTP ${r.status})`);
  return;
}
r = await request('/api/options');
ok(r.status === 200 && r.json?.regions?.length > 0, 'clean address /api/leads is available for the website (URL rewriting on)');

// 2. It is the version on this computer.
const pages = ['index.html', 'form/index.html', 'admin/index.html', 'admin/app.js', 'js/form.js', 'embed.js', 'css/style.css'];
const stale = [];
for (const file of pages) {
  const live = await request('/' + file);
  if (live.status !== 200 || sha(live.bytes) !== sha(fs.readFileSync(`${root}/public/${file}`))) stale.push(file);
}
ok(stale.length === 0, stale.length ? 'live pages differ from this computer: ' + stale.join(', ') : 'live pages are the same as on this computer');

// 3. Nothing private is served.
const open = [];
for (const path of ['/app/settings.php', '/app/config.php', '/app/bootstrap.php', '/data/', '/data/access-check.txt']) {
  const res = await request(path);
  if (res.status !== 403 && res.status !== 404) open.push(`${path} -> ${res.status}`);
}
ok(open.length === 0, open.length ? 'PRIVATE FILES ARE REACHABLE: ' + open.join(', ') : 'settings, code and data folder are closed to browsers');

// 4. Login.
ok((await request('/api/leads')).status === 401, 'customer list refuses visitors who are not logged in');
r = await post('/api/login', { username: setting('admin_user'), password: setting('admin_password') });
if (!ok(r.status === 200, 'login with the password in settings.php' + (r.status === 200 ? '' : ` (HTTP ${r.status}: ${r.json?.error ?? ''})`))) return;
const cookie = (r.headers.get('set-cookie') || '').split(';')[0];
const auth = { Cookie: cookie };
const me = (await request('/api/me', { headers: auth })).json;
const stats = (await request('/api/stats', { headers: auth })).json;
ok(Number.isInteger(stats?.totals?.total), 'dashboard data loads');
note('customers on the server: ' + stats?.totals?.total);
note('database kept ' + (me?.data_outside_web_folder ? 'OUTSIDE the website folder (emptying the CRM folder cannot delete customers)' : 'inside the CRM folder, in data/ (never delete that folder)'));

// 5. Optional: one customer in and out, exactly as the website sends it.
if (writeTest) {
  const email = `deploy-check-${Date.now()}@example.com`;
  const lead = { full_name: 'Deploy Check', email, company: 'Deploy Check', job_title: 'owner', region: 'cairo', service: 'branding', timeline: 'asap', budget: 'gt-200k', message: 'Automatic check after upload. Safe to delete.', source: 'deploy-check' };
  r = await post('/api/leads', lead, { 'X-CRM-Key': setting('integration_key'), Accept: 'application/json' });
  if (ok(r.status === 201 && r.json?.ok === true, 'a customer sent the way the website sends it is accepted')) {
    const id = r.json.id;
    const saved = (await request('/api/leads/' + id, { headers: auth })).json;
    ok(saved?.lead?.email === email && saved.lead.region === 'القاهرة' && saved.lead.priority === 'high', 'stored with region, zone and automatic priority');
    // Only ever delete the record that carries this run's unique email.
    if (saved?.lead?.email === email) {
      r = await post(`/api/leads/${id}/delete`, {}, auth);
      ok(r.status === 200 && (await request('/api/leads/' + id, { headers: auth })).status === 404, 'test customer deleted again');
    }
  } else {
    note('nothing was created, so nothing was deleted');
  }
}

await post('/api/logout', {}, auth);
}
