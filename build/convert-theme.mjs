/**
 * Library theme LESS -> SCSS conversion (HP-134, Phase 6).
 *
 * For every theme dir under vufind/web/interface/themes/ (except responsive):
 *   css/variables.less -> css/_variables.scss   (plain assignments, NO !default —
 *                                                theme values must win over the
 *                                                responsive theme's !default values)
 *   css/main.less      -> css/main.scss         with the import preamble rewritten:
 *       @import "../../responsive/css/variables"  (reference)   -> dropped
 *       @import "variables"                        (reference)   -> @import "variables";
 *       @import "../../responsive/css/responsive_base"           -> @import "../../responsive/css/responsive-base";
 *   any other css/*.less -> css/_<name>.scss    (e.g. clearview fonts.less)
 *
 * Themes with a css/ dir but no main.less (stale relics) and themes without css/
 * are reported and skipped. Original .less files are left in place until the
 * cleanup phase.
 *
 * Usage: node build/convert-theme.mjs [--theme=<name>]
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { convert } from './less2scss.mjs';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const themesDir = path.join(repoRoot, 'vufind', 'web', 'interface', 'themes');
const onlyTheme = process.argv.find(a => a.startsWith('--theme='))?.split('=')[1];

function rewritePreamble(scss, warn) {
	const lines = scss.split('\n');
	const out = [];
	let baseImported = false;
	for (const line of lines) {
		const t = line.trim();
		// responsive variables reference import: drop — theme variables load first and
		// responsive-base pulls in the responsive defaults (!default) afterwards.
		if (/^@import\s+["']\.\.\/\.\.\/responsive\/css\/variables["'];?$/.test(t)) continue;
		// theme's own variables
		if (/^@import\s+["']variables["'];?$/.test(t)) { out.push('@import "variables";'); continue; }
		// the base import (old name responsive_base -> new partial responsive-base)
		if (/^@import\s+["']\.\.\/\.\.\/responsive\/css\/responsive_base["'];?$/.test(t)) {
			out.push('@import "../../responsive/css/responsive-base";');
			baseImported = true;
			continue;
		}
		// any other import of a responsive partial is suspicious — flag it
		if (/^@import\s+["']\.\.\/\.\.\/responsive\//.test(t)) warn(`unusual responsive import kept: ${t}`);
		out.push(line);
	}
	if (!baseImported) warn('main.less did not import responsive_base — converted as-is');
	return out.join('\n');
}

const themes = fs.readdirSync(themesDir, { withFileTypes: true })
	.filter(d => d.isDirectory() && d.name !== 'responsive')
	.map(d => d.name)
	.filter(n => !onlyTheme || n === onlyTheme)
	.sort();

let converted = 0, skipped = 0, flagged = 0;
for (const theme of themes) {
	const cssDir = path.join(themesDir, theme, 'css');
	const warnings = [];
	const warn = (msg) => warnings.push(msg);

	if (!fs.existsSync(cssDir)) { console.log(`SKIP ${theme}: no css dir`); skipped++; continue; }
	const lessFiles = fs.readdirSync(cssDir).filter(f => f.endsWith('.less'));
	if (!lessFiles.includes('main.less')) {
		const relics = fs.readdirSync(cssDir).join(', ') || '(empty)';
		console.log(`SKIP ${theme}: no main.less (has: ${relics})`);
		skipped++;
		continue;
	}

	for (const less of lessFiles) {
		const src = fs.readFileSync(path.join(cssDir, less), 'utf8');
		const { scss, warnings: convWarnings } = convert(src, { filename: `${theme}/${less}` });
		convWarnings.forEach(w => warn(`${less}: ${w}`));
		let outName, outContent;
		if (less === 'main.less') {
			outName = 'main.scss';
			outContent = rewritePreamble(scss, warn);
		} else {
			outName = `_${less.replace(/\.less$/, '')}.scss`;
			outContent = scss;
			// rewrite intra-theme imports of converted siblings is unnecessary: sass
			// resolves @import "fonts" to _fonts.scss automatically.
		}
		fs.writeFileSync(path.join(cssDir, outName), outContent);
	}
	converted++;
	if (warnings.length) {
		flagged++;
		for (const w of warnings) console.error(`[${theme}] ${w}`);
	}
}
console.log(`converted ${converted} theme(s), skipped ${skipped}${flagged ? `, ${flagged} flagged` : ''}`);
