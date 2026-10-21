/* Read-only source analysis. Writes Markdown inventories beside this script.
 * Run from the repository: node docs/rebuild/tools/generate-inventories.cjs
 * Does not load Laravel, read .env, execute PHP, query a DB, or read SQL seed data.
 */
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '../../..');
const output = path.join(root, 'docs/rebuild/inventories');
fs.mkdirSync(output, { recursive: true });
const read = p => fs.readFileSync(path.join(root, p), 'utf8').replace(/^\uFEFF/, '');
const link = p => `[${p}](../../../${p})`;
const cell = s => String(s).replace(/\|/g, '\\|').replace(/\r?\n/g, ' ');
const files = dir => fs.readdirSync(path.join(root, dir), { withFileTypes: true })
  .flatMap(e => e.isDirectory() ? files(`${dir}/${e.name}`) : [`${dir}/${e.name}`]).sort();
// Keep quoted literals intact and preserve offsets and line numbers while erasing comments.
function clean(source) {
  return source.replace(/'(?:\\[\s\S]|[^'\\])*'|"(?:\\[\s\S]|[^"\\])*"|\/\*[\s\S]*?\*\/|\/\/[^\r\n]*|#[^\r\n]*/g,
    token => token.startsWith("'") || token.startsWith('"') ? token : token.replace(/[^\r\n]/g, ' '));
}
function closing(s, start, open = '(', close = ')') {
  let depth = 0, quote = null;
  for (let i = start; i < s.length; i++) {
    const c = s[i];
    if (quote) { if (c === '\\') i++; else if (c === quote) quote = null; continue; }
    if (c === "'" || c === '"') { quote = c; continue; }
    if (c === open) depth++;
    else if (c === close && --depth === 0) return i;
  }
  return s.length - 1;
}
const line = (s, offset) => s.slice(0, offset).split('\n').length;
let revision = 'unavailable';
try { revision = execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim(); } catch {}
const preface = title => `# ${title}\n\nSource revision: \`${revision}\`. Generated from the working tree by [generate-inventories.cjs](../tools/generate-inventories.cjs). This is static source evidence, not a running application or live database inspection. Re-run after source changes.\n\n`;
const controllers = new Map();
for (const p of files('app/Http/Controllers').filter(p => p.endsWith('.php'))) {
  const code = clean(read(p));
  const name = p.slice('app/Http/Controllers/'.length, -4).replaceAll('/', '\\');
  const methods = [...code.matchAll(/\bfunction\s+(\w+)\s*\(/g)].map(m => {
    const start = code.indexOf('{', m.index);
    return { name: m[1], line: line(code, m.index), body: code.slice(start + 1, closing(code, start, '{', '}')) };
  });
  const constructor = methods.find(m => m.name === '__construct');
  const middleware = [...(constructor?.body || '').matchAll(/\$this->middleware\s*\(\s*['"]([^'"]+)['"]\s*\)([^;]*)/g)]
    .map(m => m[1] + m[2].trim());
  controllers.set(name, { p, methods, middleware });
}
const routes = [];
const authRegistrations = [];
for (const p of ['routes/web.php', 'routes/api.php']) {
  const code = clean(read(p));
  const groups = [...code.matchAll(/Route::middleware\([^;]*?->group\(function\s*\([^)]*\)\s*\{/g)]
    .map(m => ({ start: m.index + m[0].length - 1, end: closing(code, m.index + m[0].length - 1, '{', '}'),
      prefix: m[0].match(/->prefix\(\s*['"]([^'"]+)['"]\s*\)/)?.[1] || '',
      middleware: m[0].match(/Route::middleware\(([^)]*)\)/)?.[1].replace(/[\[\]'"\s]/g, '') || '' }));
  for (const m of code.matchAll(/(?:Route::|->)(get|post|put|patch|delete|options|any|match)\s*\(/gi)) {
    const start = m.index + m[0].length - 1;
    const end = closing(code, start);
    const args = code.slice(start + 1, end);
    const uri = args.match(/^\s*['"]([^'"]*)['"]/);
    if (!uri) throw new Error(`Unsupported route syntax: ${p}:${line(code, m.index)}`);
    const old = args.match(/,\s*['"]([^'"]+@[^'"]+)['"]/);
    const modern = args.match(/,\s*\[\s*([\w\\]+)::class\s*,\s*['"]([^'"]+)['"]\s*\]/);
    const target = old?.[1] || (modern ? `${modern[1]}@${modern[2]}` : '(closure or unresolved)');
    const group = groups.filter(g => m.index > g.start && m.index < g.end);
    const prefix = [p.endsWith('api.php') ? 'api' : '', ...group.map(g => g.prefix)].filter(Boolean).join('/');
    const uriFull = '/' + [prefix, uri[1].replace(/^\//, '')].filter(Boolean).join('/');
    const chain = code.slice(end + 1, code.indexOf(';', end));
    let direct = chain.match(/->middleware\(\s*['"]([^'"]+)['"]\s*\)/)?.[1];
    if (m[0].startsWith('->')) {
      const builder = code.slice(code.lastIndexOf(';', m.index) + 1, m.index);
      direct = builder.match(/Route::middleware\(([^)]*)\)/)?.[1].replace(/[\[\]'"\s]/g, '') || direct;
    }
    const [controller, method] = target.split('@');
    const known = controllers.get(controller.replace(/^\\?App\\Http\\Controllers\\/, ''));
    routes.push({ p, line: line(code, m.index), verb: m[1].toUpperCase(), uri: uriFull, target,
      middleware: [p.endsWith('api.php') ? 'api (throttle:60,1)' : 'web', ...group.map(g => g.middleware), direct].filter(Boolean).join('; '),
      constructor: known?.middleware.join('; ') || 'none declared',
      missing: !!known && !known.methods.some(m => m.name.toLowerCase() === method?.toLowerCase()) });
  }
  for (const m of code.matchAll(/Auth::routes\s*\([^;]*;/g)) authRegistrations.push(`${link(p)}:${line(code, m.index)} registers framework auth routes; inspect actual route list for expansions and overrides.`);
}
let routeDoc = preface('Legacy route inventory');
routeDoc += `Explicit routes: **${routes.length}**. GET entries implicitly also support HEAD in Laravel. Framework-generated auth routes are excluded from this count. Constructor middleware below is extracted text; method-level authorization, trait methods and effective middleware exceptions require source review. The web group supplies sessions and CSRF, not authentication.\n\n`;
routeDoc += authRegistrations.join('\n\n') + '\n\n';
routeDoc += '| Method | Path | Handler | Route middleware | Constructor middleware | Source |\n| --- | --- | --- | --- | --- | --- |\n';
for (const r of routes) routeDoc += `| ${r.verb} | \`${r.uri}\` | \`${r.target}\`${r.missing ? ' **MISSING explicit method**' : ''} | ${cell(r.middleware)} | ${cell(r.constructor)} | ${link(r.p)}:${r.line} |\n`;
const routeGroups = new Map();
for (const r of routes) {
  const key = `${r.verb} ${r.uri}`;
  if (!routeGroups.has(key)) routeGroups.set(key, []);
  routeGroups.get(key).push(r);
}
const duplicates = [...routeGroups].filter(([, rows]) => rows.length > 1);
routeDoc += '\n## Repeated method and path declarations\n\n';
for (const [key, rows] of duplicates) routeDoc += `- \`${key}\`: ${rows.map(r => `\`${r.target}\` (${r.p}:${r.line})`).join('; ')}. Reconcile intended handler before porting.\n`;
routeDoc += '\n## Routes with missing explicit controller methods\n\n';
for (const r of routes.filter(r => r.missing)) routeDoc += `- \`${r.verb} ${r.uri}\` -> \`${r.target}\` (${r.p}:${r.line}). Check trait inheritance before treating as broken.\n`;
fs.writeFileSync(path.join(output, 'ROUTES.md'), routeDoc);
let controllerDoc = preface('Legacy controller inventory');
controllerDoc += `Controllers: **${controllers.size}**. Methods include stubs and non-routed helpers; a method name is not evidence that a feature is complete.\n\n`;
for (const [name, controller] of controllers) {
  controllerDoc += `## ${name}\n\nSource: ${link(controller.p)}. Constructor middleware: ${controller.middleware.length ? controller.middleware.map(cell).join('; ') : 'none declared'}.\n\n`;
  controllerDoc += controller.methods.map(m => `- \`${m.name}\` (line ${m.line})`).join('\n') + '\n\n';
}
fs.writeFileSync(path.join(output, 'CONTROLLERS.md'), controllerDoc);
let modelDoc = preface('Legacy model inventory');
modelDoc += 'Entries marked implicit show the model class label when no $table override is declared; inferred framework table names are not computed here. Confirm those names against the schema. Fillable attributes describe mass assignment, not the full schema. Relationships are literal declarations; they may use unusual foreign keys or be incorrect.\n\n';
modelDoc += '| Model | Table | Fillable attributes | Declared relationships |\n| --- | --- | --- | --- |\n';
let modelCount = 0;
for (const p of files('app').filter(p => /^app\/[^/]+\.php$/.test(p))) {
  const code = clean(read(p));
  const cls = code.match(/class\s+(\w+)\s+extends\s+(Model|Authenticatable)\b/);
  if (!cls) continue;
  modelCount++;
  const table = code.match(/\$table\s*=\s*['"]([^'"]+)['"]/)?.[1] || `${cls[1]} (implicit table)`;
  const fill = code.match(/\$fillable\s*=\s*\[([\s\S]*?)\]/)?.[1] || '';
  const attributes = [...fill.matchAll(/['"]([^'"]+)['"]/g)].map(m => m[1]).join(', ');
  const relations = [...code.matchAll(/\$this->(hasOne|hasMany|belongsTo|belongsToMany|morphOne|morphMany|morphTo)\s*\(([^;\n]*)/g)]
    .map(m => `${m[1]}(${m[2].replace(/\)\s*$/, '')})`).join('; ');
  modelDoc += `| ${link(p)} | \`${table}\` | ${cell(attributes || '(not declared)')} | ${cell(relations || '(none declared)')} |\n`;
}
fs.writeFileSync(path.join(output, 'MODELS.md'), modelDoc);
let schemaDoc = preface('Legacy migration and field inventory');
schemaDoc += 'This is an ordered migration ledger of up() operations, not a reconstructed final schema. Later operations may change or remove fields/tables. Raw SQL, conditional code, computed column names and model-only fields require manual review. Only names, types and modifiers are included; seed records and credentials are excluded. Obtain a schema-only live database export before building the importer.\n\n';
const migrationFiles = files('database/migrations').filter(p => p.endsWith('.php'));
schemaDoc += `Migration files: **${migrationFiles.length}**.\n\n`;
for (const p of migrationFiles) {
  const code = clean(read(p));
  const up = code.match(/function\s+up\s*\([^)]*\)[^{]*\{/);
  if (!up) continue;
  const start = up.index + up[0].length - 1;
  const body = code.slice(start + 1, closing(code, start, '{', '}'));
  schemaDoc += `## ${path.basename(p)}\n\n${link(p)}\n\n`;
  const operations = [...body.matchAll(/Schema::(create|table|dropIfExists|drop|rename)\s*\(\s*['"]([^'"]+)['"]/g)];
  if (!operations.length) schemaDoc += 'No literal Schema operation detected; inspect source for raw SQL or no-op migration.\n\n';
  for (const [i, operation] of operations.entries()) {
    const segment = body.slice(operation.index, operations[i + 1]?.index ?? body.length);
    const columns = [...segment.matchAll(/\$table->(\w+)\s*\(\s*(?:['"]([^'"]+)['"])?([^;]*);/g)]
      .map(m => {
        const modifiers = [...m[3].matchAll(/->(nullable|unique|index|unsigned|change|after|constrained|cascadeOnDelete|cascadeOnUpdate|default)\b/g)].map(x => x[1]);
        const numeric = m[3].match(/^\s*,\s*(\d+(?:\s*,\s*\d+)?)/)?.[1];
        return `\`${m[2] || '(implicit/multiple columns)'}\`: ${m[1]}${numeric ? `(${numeric.replace(/\s/g, '')})` : ''}${modifiers.length ? ` [${modifiers.join(', ')}]` : ''}`;
      });
    schemaDoc += `- **${operation[1]}** \`${operation[2]}\`${columns.length ? ': ' + columns.join('; ') : ''}.\n`;
  }
  if (/DB::(statement|unprepared)|->renameColumn|->dropColumn/.test(body)) schemaDoc += '\nIncludes raw SQL or rename/drop columns; read the migration before mapping final fields.\n';
  schemaDoc += '\n';
}
fs.writeFileSync(path.join(output, 'SCHEMA.md'), schemaDoc);
const summary = { revision, routes: routes.length, duplicateRouteKeys: duplicates.length, missingExplicitMethods: routes.filter(r => r.missing).map(r => `${r.verb} ${r.uri} -> ${r.target}`), controllers: controllers.size, models: modelCount, migrations: migrationFiles.length };
fs.writeFileSync(path.join(output, 'summary.json'), JSON.stringify(summary, null, 2) + '\n');
console.log(JSON.stringify(summary, null, 2));
