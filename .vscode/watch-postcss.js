#!/usr/bin/env node
/**
 * watch-postcss.js — VS Code equivalent of the PhpStorm "PostCSS Autofixer" file watcher.
 *
 * This is STAGE 2 of the Less → CSS pipeline:
 *   Stage 1 (watch-less.js):     *.less        →  *.tmpcss
 *   Stage 2 (watch-postcss.js):  main.tmpcss   →  main.css   (autoprefixer, no source map)
 *
 * Scope: any file named exactly "main.tmpcss" in any directory.
 *
 * Per-file command (run in the file's own directory):
 *   postcss main.tmpcss --use autoprefixer -o main.css --no-map
 *
 * "Trigger on external changes" is ON — chokidar's usePolling: false (default) picks up
 * writes from other processes (like watch-less.js) via native fs events, which is correct.
 *
 * Requires: chokidar  →  npm install --save-dev chokidar
 *           postcss + autoprefixer  →  npm install --save-dev postcss postcss-cli autoprefixer
 */

const chokidar = require('chokidar');
const { execSync } = require('child_process');
const path = require('path');

// ─── Scope filter ────────────────────────────────────────────────────────────
// Only files named exactly "main.tmpcss" anywhere in the tree.

function shouldProcess(filePath) {
  return path.basename(filePath) === 'main.tmpcss';
}

// ─── Processor ───────────────────────────────────────────────────────────────

function process(filePath) {
  if (!shouldProcess(filePath)) return;

  const absPath = path.resolve(filePath);
  const dir     = path.dirname(absPath);
  const input   = 'main.tmpcss';
  const output  = 'main.css';

  console.log(`[postcss] processing ${filePath}`);
  try {
    execSync(
      `postcss "${input}" --use autoprefixer -o "${output}" --no-map`,
      { cwd: dir, stdio: 'inherit' }
    );
    console.log(`[postcss]  → ${path.join(dir, output)}`);
  } catch (err) {
    console.error(`[postcss]  ✗ failed: ${filePath}`);
  }
}

// ─── Watcher ─────────────────────────────────────────────────────────────────
// usePolling is NOT used — native fs events catch writes from external processes
// (i.e. files written by watch-less.js) on both Windows and macOS.

const watcher = chokidar.watch('**/main.tmpcss', {
  ignored: /(^|[/\\])\../,   // ignore dotfiles/dot-dirs
  persistent: true,
  ignoreInitial: true,        // don't process everything on startup
  awaitWriteFinish: {         // wait for the file write to stabilise before firing
    stabilityThreshold: 300,
    pollInterval: 50
  }
});

watcher
  .on('add',    process)
  .on('change', process);

console.log('[postcss] Watching main.tmpcss files for changes...');
