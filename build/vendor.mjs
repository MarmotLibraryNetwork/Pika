/**
 * Copies runtime frontend assets from node_modules into the web tree.
 * The copied files are committed to git so servers never need Node.
 *
 * Usage: npm run vendor   (run after npm install / after bumping bootstrap or bootstrap-icons)
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const nm = path.join(repoRoot, 'node_modules');
const webDir = path.join(repoRoot, 'vufind', 'web');

const copies = [
	// Bootstrap 5 JS bundle (includes Popper, required for popovers/dropdowns)
	{
		from: path.join(nm, 'bootstrap', 'dist', 'js', 'bootstrap.bundle.min.js'),
		to: path.join(webDir, 'interface', 'themes', 'responsive', 'js', 'lib', 'bootstrap.bundle.min.js'),
	},
	// Bootstrap Icons font files -> shared fonts dir (same place the glyphicons fonts lived,
	// referenced from SCSS via $bootstrap-icons-font-dir)
	{
		from: path.join(nm, 'bootstrap-icons', 'font', 'fonts', 'bootstrap-icons.woff'),
		to: path.join(webDir, 'fonts', 'bootstrap-icons.woff'),
	},
	{
		from: path.join(nm, 'bootstrap-icons', 'font', 'fonts', 'bootstrap-icons.woff2'),
		to: path.join(webDir, 'fonts', 'bootstrap-icons.woff2'),
	},
];

let failed = false;
for (const { from, to } of copies) {
	if (!fs.existsSync(from)) {
		console.error(`MISSING ${path.relative(repoRoot, from)} — did you run npm install?`);
		failed = true;
		continue;
	}
	fs.mkdirSync(path.dirname(to), { recursive: true });
	fs.copyFileSync(from, to);
	console.log(`${path.relative(repoRoot, from)} -> ${path.relative(repoRoot, to)}`);
}
process.exitCode = failed ? 1 : 0;
