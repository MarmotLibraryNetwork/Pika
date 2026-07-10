/**
 * Pika theme stylesheet build.
 *
 * Compiles every theme entry point vufind/web/interface/themes/<theme>/css/main.scss to:
 *   main.css      (expanded, autoprefixed)      — served when $debugCss is on
 *   main.min.css  (autoprefixed + cssnano)      — served in production
 *
 * Replaces the old JetBrains file-watcher chain: lessc -> .tmpcss -> postcss autoprefixer -> .css -> cleancss -> .min.css
 * Output filenames are unchanged so the {css} Smarty plugin keeps working.
 *
 * Usage:
 *   node build/css/build.mjs                 # build all themes that have css/main.scss
 *   node build/css/build.mjs --theme=marmot  # build one theme
 *   node build/css/build.mjs --watch         # watch all theme css dirs; recompile the changed
 *                                            # theme on save (a change under responsive/css
 *                                            # recompiles only responsive — run a full build
 *                                            # before committing other themes' CSS)
 */

import * as sass from 'sass';
import postcss from 'postcss';
import autoprefixer from 'autoprefixer';
import cssnano from 'cssnano';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const themesDir = path.join(repoRoot, 'vufind', 'web', 'interface', 'themes');
const loadPaths = [path.join(repoRoot, 'node_modules')];

const args = process.argv.slice(2);
const watchMode = args.includes('--watch');
const themeArg = args.find(a => a.startsWith('--theme='))?.split('=')[1];

const prefixer = postcss([autoprefixer]);
const minifier = postcss([autoprefixer, cssnano({ preset: 'default' })]);

function themeEntryPoints() {
	return fs.readdirSync(themesDir, { withFileTypes: true })
		.filter(d => d.isDirectory())
		.map(d => d.name)
		.filter(name => fs.existsSync(path.join(themesDir, name, 'css', 'main.scss')))
		.sort();
}

async function buildTheme(theme) {
	const cssDir = path.join(themesDir, theme, 'css');
	const entry = path.join(cssDir, 'main.scss');
	const started = Date.now();
	try {
		const compiled = sass.compile(entry, {
			loadPaths,
			style: 'expanded',
			quietDeps: true, // silence deprecation noise from node_modules (bootstrap)
			// Bootstrap 5.x is built on @import; the @use migration (and the module
			// color functions) come with Bootstrap 6.
			silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'slash-div'],
		});
		const from = entry;
		const expanded = await prefixer.process(compiled.css, { from, map: false });
		const minified = await minifier.process(compiled.css, { from, map: false });
		fs.writeFileSync(path.join(cssDir, 'main.css'), expanded.css);
		fs.writeFileSync(path.join(cssDir, 'main.min.css'), minified.css);
		console.log(`  ${theme}: OK (${Date.now() - started}ms, ${(minified.css.length / 1024).toFixed(0)} KB min)`);
		return true;
	} catch (err) {
		console.error(`  ${theme}: FAILED\n${err.message ?? err}`);
		return false;
	}
}

async function buildAll(themes) {
	console.log(`Building ${themes.length} theme(s)...`);
	let failures = 0;
	for (const theme of themes) {
		if (!await buildTheme(theme)) failures++;
	}
	if (failures > 0) {
		console.error(`${failures} theme(s) failed to compile.`);
		process.exitCode = 1;
	} else {
		console.log('All themes compiled cleanly.');
	}
	return failures === 0;
}

const themes = themeArg ? [themeArg] : themeEntryPoints();
if (themeArg && !fs.existsSync(path.join(themesDir, themeArg, 'css', 'main.scss'))) {
	console.error(`Theme "${themeArg}" has no css/main.scss under ${themesDir}`);
	process.exit(1);
}
if (themes.length === 0) {
	console.error(`No theme with a css/main.scss entry point found under ${themesDir}`);
	process.exit(1);
}

await buildAll(themes);

if (watchMode) {
	console.log('Watching for .scss changes (Ctrl+C to stop)...');
	const pending = new Map(); // theme -> debounce timer
	for (const theme of themes) {
		const cssDir = path.join(themesDir, theme, 'css');
		fs.watch(cssDir, { recursive: true }, (event, filename) => {
			if (!filename || !filename.endsWith('.scss')) return;
			clearTimeout(pending.get(theme));
			pending.set(theme, setTimeout(() => {
				console.log(`[${new Date().toLocaleTimeString()}] ${theme}/css/${filename} changed`);
				buildTheme(theme);
			}, 150));
		});
	}
}
