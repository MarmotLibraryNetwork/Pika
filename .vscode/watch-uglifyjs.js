#!/usr/bin/env node
/**
 * watch-uglifyjs.js — VS Code equivalent of a PhpStorm "UglifyJS" file watcher.
 *
 * Scope: any *.js file directly in vufind/web/interface/themes/responsive/js/pika,
 *        excluding files already ending in .min.js (its own output).
 *
 * Per-file command (run in the file's own directory):
 *   uglifyjs <filename>.js -c arrows=false -o <filename>.min.js
 *
 * arrows=false and no mangler match the style already committed for the existing
 * min.js files in this directory (compressed but not mangled, no arrow functions).
 *
 * After minifying, also re-runs merge_javascript.php (in the parent js/ directory)
 * to rebuild the bundled js/pika.min.js from javascript_files.txt, since that
 * bundle includes the pika/*.min.js file that was just regenerated.
 *
 * Requires: chokidar   →  npm install --save-dev chokidar
 *           uglify-js  →  npm install --save-dev uglify-js
 *           php        →  must be on PATH
 */

const chokidar = require('chokidar');
const { execSync } = require('child_process');
const path = require('path');

// ─── Scope filter ────────────────────────────────────────────────────────────

function shouldProcess(filePath) {
  const base = path.basename(filePath);
  return base.endsWith('.js') && !base.endsWith('.min.js');
}

// ─── Minifier ────────────────────────────────────────────────────────────────

function minify(filePath) {
  if (!shouldProcess(filePath)) return;

  const absPath = path.resolve(filePath);
  const dir     = path.dirname(absPath);
  const input   = path.basename(absPath);
  const output  = path.basename(absPath, '.js') + '.min.js';

  console.log(`[uglifyjs] minifying ${filePath}`);
  try {
    execSync(
      `uglifyjs "${input}" -c arrows=false -o "${output}"`,
      { cwd: dir, stdio: 'inherit' }
    );
    console.log(`[uglifyjs]  → ${path.join(dir, output)}`);
  } catch (err) {
    console.error(`[uglifyjs]  ✗ failed: ${filePath}`);
    return;
  }

  mergeJavascript(dir);
}

// ─── Merge ───────────────────────────────────────────────────────────────────
// Rebuilds js/pika.min.js from javascript_files.txt, which includes the
// pika/*.min.js file that was just regenerated above.

function mergeJavascript(pikaDir) {
  const jsDir = path.dirname(pikaDir); // .../responsive/js

  console.log(`[uglifyjs] merging javascript bundle`);
  try {
    execSync(
      `php merge_javascript.php`,
      { cwd: jsDir, stdio: 'inherit' }
    );
    console.log(`[uglifyjs]  → ${path.join(jsDir, 'pika.min.js')}`);
  } catch (err) {
    console.error(`[uglifyjs]  ✗ merge failed`);
  }
}

// ─── Watcher ─────────────────────────────────────────────────────────────────

const watcher = chokidar.watch('vufind/web/interface/themes/responsive/js/pika', {
  ignored: /(^|[/\\])\../,   // ignore dotfiles/dot-dirs
  persistent: true,
  ignoreInitial: true,        // don't process everything on startup
  awaitWriteFinish: {         // wait for file write to stabilise before firing
    stabilityThreshold: 300,
    pollInterval: 50
  }
});

watcher
  .on('add',    minify)
  .on('change', minify);

console.log('[uglifyjs] Watching pika js files for changes...');
