/**
 * Scripted Bootstrap 3 -> 5 template migration passes (HP-134).
 *
 * Operates on every .tpl under vufind/web/interface/themes/ plus the three PHP
 * files that emit Bootstrap markup. One pass per run; commit each pass
 * separately so reviewers can re-run the script and diff.
 *
 * Usage: node build/migrate-templates.mjs --pass=<name> [--dry-run]
 *   data-attrs   data-toggle/dismiss/target/parent -> data-bs-* (skips data-toggle="buttons")
 *   grid         col tier shift: tn->base, xs->sm, sm->md, md->lg, lg->xl (+offsets)
 *   visibility   hidden-N / visible-N -> d-* utilities at the shifted tiers
 *   renames      pull/text/img/sr-only/btn-default/well/label/badge/input-group-addon/collapse-in
 *   icons        glyphicon glyphicon-X -> bi bi-Y (validated against bootstrap-icons.json)
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const themesDir = path.join(repoRoot, 'vufind', 'web', 'interface', 'themes');
const phpEmitters = [
	'vufind/web/sys/Pager.php',
	'vufind/web/services/Admin/Variables.php',
	'vufind/web/services/Admin/BlockPatronAccountLinks.php',
].map(p => path.join(repoRoot, p));

const passArg = process.argv.find(a => a.startsWith('--pass='))?.split('=')[1];
const dryRun = process.argv.includes('--dry-run');

// glyphicon name -> bootstrap-icons name (complete census of this codebase)
const ICON_MAP = {
	'chevron-right': 'chevron-right',
	'chevron-left': 'chevron-left',
	'chevron-down': 'chevron-down',
	'chevron-up': 'chevron-up',
	'eye-open': 'eye',
	'eye-close': 'eye-slash',
	'calendar': 'calendar3',
	'duplicate': 'copy',
	'question-sign': 'question-circle-fill',
	'time': 'clock',
	'search': 'search',
	'pencil': 'pencil',
	'remove-circle': 'x-circle',
	'trash': 'trash',
	'th': 'grid-3x3-gap-fill',
	'resize-vertical': 'arrows-vertical',
	'envelope': 'envelope',
	'plus': 'plus-lg',
	'link': 'link-45deg',
	'inbox': 'inbox',
	'sunglasses': 'sunglasses',
	'home': 'house',
	'list-alt': 'card-list',
	'floppy-disk': 'floppy',
	'fast-backward': 'skip-backward-fill',
	'exclamation-sign': 'exclamation-circle-fill',
	'edit': 'pencil-square',
	'pause': 'pause-fill',
	'ok': 'check-lg',
	'fast-forward': 'skip-forward-fill',
	'arrow-up': 'arrow-up',
	'arrow-down': 'arrow-down',
	'minus-sign': 'dash-circle-fill',
	'plus-sign': 'plus-circle-fill',
	'warning-sign': 'exclamation-triangle-fill',
	'refresh': 'arrow-clockwise',
};

// Visibility utilities at the shifted tiers. Old ranges (customized BS3 grid):
// tn <480, xs 480-767, sm 768-991, md 992-1199, lg >=1200.
// New BS5 names after the sm:480px override: base/sm/md/lg/xl.
// BS3 visible-* forced display:block; the d-*-block equivalents keep that behavior.
const VISIBILITY_MAP = {
	'visible-tn': 'd-block d-sm-none',
	'hidden-tn': 'd-none d-sm-block',
	'visible-xs': 'd-none d-sm-block d-md-none',
	'hidden-xs': 'd-sm-none d-md-block',
	'visible-sm': 'd-none d-md-block d-lg-none',
	'hidden-sm': 'd-md-none d-lg-block',
	'visible-md': 'd-none d-lg-block d-xl-none',
	'hidden-md': 'd-lg-none d-xl-block',
	'visible-lg': 'd-none d-xl-block',
	'hidden-lg': 'd-xl-none',
	// stock-BS3 inline variant (from bootstrap.inline-responsive.less): hide below 768
	'hidden-inline-xs': 'd-none d-md-inline',
};

const SIMPLE_RENAMES = [
	[/\bpull-right\b/g, 'float-end'],
	[/\bpull-left\b/g, 'float-start'],
	[/\btext-right\b/g, 'text-end'],
	[/\btext-left\b/g, 'text-start'],
	[/\bimg-responsive\b/g, 'img-fluid'],
	[/\bcenter-block\b/g, 'd-block mx-auto'],
	[/\bsr-only\b/g, 'visually-hidden'],
	[/\bbtn-default\b/g, 'btn-outline-secondary'],
	[/\binput-group-addon\b/g, 'input-group-text'],
	// wells: `well well-sm` composes to `card card-body p-2`
	[/\bwell\b/g, 'card card-body'],
	[/\bwell-sm\b/g, 'p-2'],
	[/\bwell-lg\b/g, 'p-4'],
	// BS3 labels became badges
	[/\blabel label-default\b/g, 'badge text-bg-secondary'],
	[/\blabel label-primary\b/g, 'badge text-bg-primary'],
	[/\blabel label-success\b/g, 'badge text-bg-success'],
	[/\blabel label-info\b/g, 'badge text-bg-info'],
	[/\blabel label-warning\b/g, 'badge text-bg-warning'],
	[/\blabel label-danger\b/g, 'badge text-bg-danger'],
	// open collapse state
	[/\bcollapse in\b/g, 'collapse show'],
];

function targetFiles() {
	const files = [];
	const walk = (dir) => {
		for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
			const full = path.join(dir, e.name);
			if (e.isDirectory()) walk(full);
			else if (e.name.endsWith('.tpl')) files.push(full);
		}
	};
	walk(themesDir);
	return files.concat(phpEmitters.filter(f => fs.existsSync(f)));
}

const passes = {
	'data-attrs': (c, warn) => {
		c = c.replace(/\bdata-toggle=(["'])(collapse|dropdown|tab|modal)\1/g, 'data-bs-toggle=$1$2$1');
		c = c.replace(/\bdata-dismiss=/g, 'data-bs-dismiss=');
		c = c.replace(/\bdata-target=/g, 'data-bs-target=');
		c = c.replace(/\bdata-parent=/g, 'data-bs-parent=');
		if (/\bdata-toggle=(?!["']buttons)/.test(c)) warn('data-toggle left unconverted (non-standard value)');
		return c;
	},
	grid: (c) => {
		// offsets first (from original tier names), descending to avoid double-shifts
		c = c.replace(/\bcol-lg-offset-(\d+)\b/g, 'offset-xl-$1');
		c = c.replace(/\bcol-md-offset-(\d+)\b/g, 'offset-lg-$1');
		c = c.replace(/\bcol-sm-offset-(\d+)\b/g, 'offset-md-$1');
		c = c.replace(/\bcol-xs-offset-(\d+)\b/g, 'offset-sm-$1');
		c = c.replace(/\bcol-tn-offset-(\d+)\b/g, 'offset-$1');
		// then column widths (also carries push/pull names along; those 8 get manual order-* work)
		c = c.replace(/\bcol-lg-/g, 'col-xl-');
		c = c.replace(/\bcol-md-/g, 'col-lg-');
		c = c.replace(/\bcol-sm-/g, 'col-md-');
		c = c.replace(/\bcol-xs-/g, 'col-sm-');
		c = c.replace(/\bcol-tn-/g, 'col-');
		return c;
	},
	visibility: (c, warn) => {
		for (const [from, to] of Object.entries(VISIBILITY_MAP)) {
			c = c.replaceAll(new RegExp(`\\b${from}\\b`, 'g'), to);
		}
		// flag combos whose merged d-* classes now conflict (e.g. d-md-block + d-md-none)
		for (const m of c.matchAll(/class="[^"]*"/g)) {
			const cls = m[0];
			const seen = new Map();
			for (const dm of cls.matchAll(/\bd-(sm|md|lg|xl)-(block|none|inline)\b/g)) {
				if (seen.has(dm[1]) && seen.get(dm[1]) !== dm[2]) warn(`conflicting visibility combo: ${cls}`);
				seen.set(dm[1], dm[2]);
			}
		}
		return c;
	},
	renames: (c) => {
		for (const [re, to] of SIMPLE_RENAMES) c = c.replace(re, to);
		return c;
	},
	forms: (c, warn) => {
		// form-group wrappers that directly contain grid columns must become rows
		// (BS3 floated col-* anywhere; BS5 columns need a flex .row parent)
		c = c.replace(/class="form-group([^"]*)">(\s*(?:\{[^}]*\}\s*)*<(?:div|label|span)[^>]*class="[^"]*\bcol-)/g,
			'class="row mb-3$1">$2');
		// remaining form-groups just carry the margin
		c = c.replace(/\bform-group\b/g, 'mb-3');
		// labels: col-form-label when the label itself sits in/next to grid columns
		c = c.replace(/class="([^"]*\bcol-[^"]*)\bcontrol-label\b/g, 'class="$1col-form-label');
		c = c.replace(/class="\bcontrol-label\b([^"]*\bcol-[^"]*)"/g, 'class="col-form-label$1"');
		c = c.replace(/\bcontrol-label\b/g, 'form-label');
		// selects use form-select in BS5 (arrow + padding)
		c = c.replace(/(<select\b[^>]*?class="[^"]*?)\bform-control\b/g, '$1form-select');
		// input help text
		c = c.replace(/\bhelp-block\b/g, 'form-text');
		if (/form-horizontal/.test(c)) warn('form-horizontal present (inert in BS5) — verify layout');
		return c;
	},
	icons: (c, warn) => {
		c = c.replace(/\bglyphicon-([\w-]+)\b/g, (m, name) => {
			if (!ICON_MAP[name]) { warn(`unmapped glyphicon: ${m}`); return m; }
			return `bi-${ICON_MAP[name]}`;
		});
		c = c.replace(/\bglyphicon\b(?!-)/g, 'bi');
		return c;
	},
};

if (!passes[passArg]) {
	console.error(`Usage: node build/migrate-templates.mjs --pass=<${Object.keys(passes).join('|')}> [--dry-run]`);
	process.exit(1);
}

// validate icon map against the shipped icon manifest
if (passArg === 'icons') {
	const manifest = JSON.parse(fs.readFileSync(path.join(repoRoot, 'node_modules', 'bootstrap-icons', 'font', 'bootstrap-icons.json'), 'utf8'));
	const bad = Object.values(ICON_MAP).filter(n => !(n in manifest));
	if (bad.length) { console.error(`ICON_MAP entries missing from bootstrap-icons: ${bad.join(', ')}`); process.exit(1); }
}

let changed = 0, warned = 0;
for (const file of targetFiles()) {
	const before = fs.readFileSync(file, 'utf8');
	const warnings = [];
	const after = passes[passArg](before, (msg) => warnings.push(msg));
	if (warnings.length) {
		warned++;
		for (const w of warnings) console.error(`[${path.relative(repoRoot, file)}] ${w}`);
	}
	if (after !== before) {
		changed++;
		if (!dryRun) fs.writeFileSync(file, after);
	}
}
console.log(`${passArg}: ${changed} file(s) ${dryRun ? 'would change' : 'changed'}${warned ? `, ${warned} file(s) flagged` : ''}`);
