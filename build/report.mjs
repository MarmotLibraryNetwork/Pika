/**
 * Post-conversion verification report (HP-134, Phase 6 gate).
 * For each theme with css/main.scss: compare compiled main.min.css size against
 * the committed version at HEAD, and scan the compiled CSS for leftover LESS
 * syntax that would indicate a bad conversion.
 *
 * Usage: node build/report.mjs
 */
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const themesDir = path.join(repoRoot, 'vufind', 'web', 'interface', 'themes');

let bad = 0;
const rows = [];
for (const theme of fs.readdirSync(themesDir).sort()) {
	const cssDir = path.join(themesDir, theme, 'css');
	if (!fs.existsSync(path.join(cssDir, 'main.scss'))) continue;
	const minPath = path.join(cssDir, 'main.min.css');
	const css = fs.readFileSync(minPath, 'utf8');
	let oldSize = 0;
	try {
		oldSize = execSync(`git cat-file -s "HEAD:vufind/web/interface/themes/${theme}/css/main.min.css"`, { cwd: repoRoot }).toString().trim();
	} catch { oldSize = '(new)'; }
	const leftovers = css.match(/@\{|~"|(^|\})\s*\.[\w-]+\s*;/g);
	if (leftovers) { bad++; console.error(`${theme}: LEFTOVER LESS SYNTAX: ${leftovers.slice(0, 3).join(' | ')}`); }
	rows.push(`${theme}\t${oldSize}\t${css.length}`);
}
console.log('theme\told-bytes\tnew-bytes');
console.log(rows.join('\n'));
if (bad) { console.error(`${bad} theme(s) with leftover LESS syntax`); process.exitCode = 1; }
else console.log('No leftover LESS syntax detected in compiled output.');
