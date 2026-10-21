/* Offline documentation checks; no Laravel boot, network, database, or writes.
 * Run: node docs/rebuild/tools/validate-documentation.cjs
 */
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
const base = path.join(root, 'docs/rebuild');
function files(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap(entry => {
    const p = path.join(dir, entry.name);
    return entry.isDirectory() ? files(p) : [p];
  });
}
const markdown = files(base).filter(p => p.endsWith('.md'));
const errors = [];
let linkCount = 0, taskCount = 0;
for (const p of markdown) {
  const content = fs.readFileSync(p, 'utf8');
  taskCount += [...content.matchAll(/^- \[ \]/gm)].length;
  for (const match of content.matchAll(/\[[^\]\n]+\]\(([^)]+)\)/g)) {
    const target = match[1].replace(/^<|>$/g, '').split('#')[0];
    if (!target || /^(?:https?:|mailto:|data:)/.test(target)) continue;
    linkCount++;
    if (!fs.existsSync(path.resolve(path.dirname(p), decodeURIComponent(target)))) {
      errors.push(`Broken link in ${path.relative(root, p)}: ${target}`);
    }
  }
  if ((content.match(/^```/gm) || []).length % 2 !== 0) errors.push(`Unbalanced fences: ${path.relative(root, p)}`);
}
const modules = files(path.join(base, 'modules')).filter(p => p.endsWith('.md'));
if (modules.length !== 22) errors.push(`Expected 22 module guides; found ${modules.length}`);
for (const p of modules) {
  const content = fs.readFileSync(p, 'utf8');
  if (!/^## (Build|Implementation) checklist/im.test(content)) errors.push(`Missing build checklist: ${path.basename(p)}`);
  if (!/^## Acceptance checklist/im.test(content)) errors.push(`Missing acceptance checklist: ${path.basename(p)}`);
  if ((content.match(/^- \[ \]/gm) || []).length < 10) errors.push(`Insufficient checklist coverage: ${path.basename(p)}`);
}
// Match sensitive values without ever printing the values, including on failure.
const candidateSecrets = [];
const envPath = path.join(root, '.env');
if (fs.existsSync(envPath)) {
  for (const row of fs.readFileSync(envPath, 'utf8').split(/\r?\n/)) {
    const entry = row.match(/^\s*([A-Z0-9_]*(?:PASSWORD|SECRET|TOKEN|KEY)[A-Z0-9_]*)\s*=\s*(.*?)\s*$/);
    if (!entry) continue;
    const value = entry[2].replace(/^(['"])([\s\S]*)\1$/, '$2');
    if (value.length >= 8 && !value.includes('${')) candidateSecrets.push({ key: entry[1], value });
  }
}
const mailPath = path.join(root, 'config/mail.php');
for (const entry of fs.readFileSync(mailPath, 'utf8').matchAll(/['"]password['"]\s*=>\s*['"]([^'"]+)['"]/g)) {
  if (entry[1].length >= 8) candidateSecrets.push({ key: 'mail.php literal password', value: entry[1] });
}
for (const p of files(base)) {
  const content = fs.readFileSync(p, 'utf8');
  for (const secret of candidateSecrets) {
    if (content.includes(secret.value)) errors.push(`Sensitive value for ${secret.key} found in ${path.relative(root, p)}`);
  }
}
console.log(JSON.stringify({ markdownFiles: markdown.length, modules: modules.length, localLinksChecked: linkCount, uncheckedTasks: taskCount, sensitiveValueCheck: 'completed without displaying values', errors }, null, 2));
if (errors.length) process.exitCode = 1;
