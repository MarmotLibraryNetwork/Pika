/**
 * Mechanical LESS -> SCSS converter for the Bootstrap 5 / SASS migration (HP-134).
 *
 * Handles the LESS constructs actually used in Pika themes:
 *   @var            -> $var           (skipping CSS at-rules)
 *   @{var}          -> #{$var}
 *   .mixin(a; b) {} -> @mixin mixin($a, $b) {}     (definition)
 *   .mixin(a; b);   -> @include mixin(a, b);       (call)
 *   &:extend(.x)    -> @extend .x
 *   ~"..." / ~'...' -> #{"..."}
 *   fade(c, n%)     -> rgba(c, n/100)
 *   spin(...)       -> adjust-hue(...)
 *   @import (reference) "x.less" -> @import "x"    (imports are de-.less'd)
 *
 * NOT handled (flagged to stderr for manual follow-up): guards (`when`),
 * `.class;` bare mixin calls, `e()` escapes, JavaScript evaluation.
 *
 * Usage: node build/less2scss.mjs <in.less> <out.scss>
 *        node build/less2scss.mjs <in.less>            # writes to stdout
 */

import fs from 'node:fs';

const CSS_AT_RULES = new Set([
	'media', 'import', 'charset', 'keyframes', '-webkit-keyframes', '-moz-keyframes',
	'font-face', 'supports', 'page', 'document', 'namespace', 'viewport',
	'top-center', 'top-left', 'top-right', 'bottom-center', // @page boxes
]);

export function convert(less, { filename = '(stdin)' } = {}) {
	const warnings = [];
	// Normalize CRLF -> LF so the line-wise regexes below see clean line ends
	// (trailing \r defeated the $ anchors on comment-bearing lines).
	let out = less.replace(/\r\n/g, '\n');

	// 1. Variable interpolation @{name} -> #{$name}
	out = out.replace(/@\{([\w-]+)\}/g, '#{$$$1}');

	// 2. Escaped strings ~"..." / ~'...' -> #{"..."}
	out = out.replace(/~(["'])((?:\\.|(?!\1).)*)\1/g, '#{$1$2$1}');

	// 3. Variables: @name -> $name unless a CSS at-rule
	out = out.replace(/@([\w-]+)/g, (m, name, offset) => {
		if (CSS_AT_RULES.has(name.toLowerCase())) return m;
		// @media queries etc already excluded; also leave `@include`/`@mixin`/`@if`/`@else`
		// alone in case this runs twice over a partially-converted file
		if (['include', 'mixin', 'extend', 'if', 'else', 'each', 'function', 'return', 'use', 'forward', 'content', 'debug', 'warn', 'error'].includes(name)) return m;
		return '$' + name;
	});

	// 4. @import lines: strip (reference) and .less extensions; drop imports of the
	//    vendored Bootstrap 3 LESS entirely (Bootstrap 5 SCSS is imported by
	//    _responsive-base.scss)
	out = out.replace(/@import\s+\(reference\)\s*/g, '@import ');
	out = out.replace(/(@import\s+["'][^"']+?)\.less(["'])/g, '$1$2');
	out = out.replace(/^[ \t]*@import\s+["'][^"']*bootstrap\/less\/[^"']*["'];?[ \t]*\r?\n/gm, '');

	// 5. &:extend(.foo) -> @extend .foo  (with optional `all` keyword dropped)
	out = out.replace(/&:extend\(([^)]+?)(?:\s+all)?\)/g, (m, sel) => `@extend ${sel.trim()}`);

	// 6/7. Mixin definitions and calls. Handled line-wise with paren-balance-aware
	//      argument splitting because default values can contain nested calls
	//      like rgba(...). LESS uses `;` as the arg separator when args contain
	//      commas; both separators appear in this codebase.
	const splitTopLevel = (s) => {
		const parts = [];
		let depth = 0, cur = '';
		const seps = s.includes(';') ? ';' : ',';
		for (const ch of s) {
			if (ch === '(') depth++;
			else if (ch === ')') depth--;
			if (ch === seps && depth === 0) { parts.push(cur); cur = ''; }
			else cur += ch;
		}
		parts.push(cur);
		return parts.map(p => p.trim()).filter(Boolean);
	};
	out = out.split('\n').map(line => {
		// Definition: `.name(<params>) {` or `#name(<params>) {`
		// (LESS also allows #-prefixed mixins; multi-line param lists are joined by callers manually)
		let m = line.match(/^([ \t]*)[.#]([\w-]+)\s*\((.*)\)\s*\{\s*(\/\/.*)?$/);
		if (m) {
			const [, indent, name, params, comment] = m;
			const scssParams = splitTopLevel(params).map(p => p.replace(/^@/, '$')).join(', ');
			return `${indent}@mixin ${name}(${scssParams}) {${comment ? ' ' + comment : ''}`;
		}
		// Call: `.name(<args>);` or `#name(<args>);`
		m = line.match(/^([ \t]*)[.#]([\w-]+)\s*\((.*)\)\s*;\s*(\/\/.*)?$/);
		if (m) {
			const [, indent, name, args, comment] = m;
			return `${indent}@include ${name}(${splitTopLevel(args).join(', ')});${comment ? ' ' + comment : ''}`;
		}
		return line;
	}).join('\n');

	// 8. Color function translations
	out = out.replace(/\bfade\(\s*([^,()]+(?:\([^)]*\))?[^,()]*)\s*,\s*(\d+(?:\.\d+)?)%\s*\)/g,
		(m, color, pct) => `rgba(${color.trim()}, ${(parseFloat(pct) / 100).toString().replace(/^0\./, '.')})`);
	out = out.replace(/\bspin\(/g, 'adjust-hue(');
	out = out.replace(/\bfadeout\(/g, 'transparentize-FIXME(');
	out = out.replace(/\bfadein\(/g, 'opacify-FIXME(');

	// 9. Flag remaining LESS-isms for manual attention
	for (const [pattern, label] of [
		[/\bwhen\s*\(/g, 'LESS guard (when)'],
		[/^\s*\.[\w-]+\s*;\s*$/gm, 'bare mixin call (.class;)'],
		[/\be\(/g, 'e() escape'],
		[/FIXME/g, 'fadeout/fadein needs manual conversion'],
		[/\.loop|-loop\b/g, 'possible LESS loop'],
	]) {
		const hits = out.match(pattern);
		if (hits) warnings.push(`${hits.length}x ${label}`);
	}

	return { scss: out, warnings };
}

// CLI
if (process.argv[1] && import.meta.url.endsWith(process.argv[1].replace(/\\/g, '/').split('/').pop())) {
	const [input, output] = process.argv.slice(2);
	if (!input) {
		console.error('Usage: node build/less2scss.mjs <in.less> [out.scss]');
		process.exit(1);
	}
	const { scss, warnings } = convert(fs.readFileSync(input, 'utf8'), { filename: input });
	for (const w of warnings) console.error(`[${input}] ${w}`);
	if (output) fs.writeFileSync(output, scss);
	else process.stdout.write(scss);
}
