#!/usr/bin/env node
/**
 * watch-cleancss.js — VS Code equivalent of the PhpStorm "CleanCSS Minifier" file watcher.
 *
 * This is STAGE 3 (final) of the Less → CSS pipeline:
 *   Stage 1 (watch-less.js):      *.less       →  *.tmpcss
 *   Stage 2 (watch-postcss.js):   main.tmpcss  →  main.css    (autoprefixer)
 *   Stage 3 (watch-cleancss.js):  main.css     →  main.min.css  (minified, -O2)
 *
 * Scope: any file named exactly "main.css" in any directory.
 *
 * Per-file command (run in the file's own directory):
 *   cleancss -O2 main.css -o main.min.css
 *
 * "Trigger on external changes" is ON — picks up main.css written by watch-postcss.js.
 *
 * Requires: chokidar       →  npm install --save-dev chokidar
 *           clean-css-cli  →  npm install --save-dev clean-css-cli
 */

const chokidar = require('chokidar');
const { execSync } = require('child_process');
const path = require('path');

// ─── Scope filter ────────────────────────────────────────────────────────────
// Only files named exactly "main.css" anywhere in the tree.

function shouldProcess(filePath) {
  return path.basename(filePath) === 'main.css';
}

// ─── Minifier ────────────────────────────────────────────────────────────────

function minify(filePath) {
  if (!shouldProcess(filePath)) return;

  const absPath = path.resolve(filePath);
  const dir     = path.dirname(absPath);
  const input   = 'main.css';
  const output  = 'main.min.css';

  console.log(`[cleancss] minifying ${filePath}`);
  try {
    execSync(
      `cleancss -O2 "${input}" -o "${output}"`,
      { cwd: dir, stdio: 'inherit' }
    );
    console.log(`[cleancss]  → ${path.join(dir, output)}`);
  } catch (err) {
    console.error(`[cleancss]  ✗ failed: ${filePath}`);
  }
}

// ─── Watcher ─────────────────────────────────────────────────────────────────
// awaitWriteFinish ensures we wait for watch-postcss.js to finish writing
// main.css before cleancss reads it.

const watcher = chokidar.watch('**/main.css', {
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

console.log('[cleancss] Watching main.css files for changes...');
