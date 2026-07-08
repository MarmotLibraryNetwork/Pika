#!/usr/bin/env node
/**
 * watch-less.js — VS Code equivalent of the PhpStorm "Less (no minification) without auto-prefixing" file watcher.
 *
 * Scope logic (mirrors PhpStorm scope "LESS Scripts"):
 *   INCLUDE  all *.less files
 *   EXCLUDE  vufind/web/interface/themes/responsive/css/bootstrap/**
 *   EXCLUDE  vufind/web/interface/themes/responsive/css/lib/**
 *   RE-INCLUDE  vufind/web/interface/themes/responsive/css/lib/tablesorter/**
 *
 * Command per file (run in the file's own directory):
 *   lessc <filename>.less <filename>.tmpcss --no-color --strict-imports
 *
 * Requires: chokidar  →  npm install --save-dev chokidar
 */

const chokidar = require('chokidar');
const { execSync } = require('child_process');
const path = require('path');

// ─── Scope filter ────────────────────────────────────────────────────────────

const EXCLUDE_BOOTSTRAP = normSep('vufind/web/interface/themes/responsive/css/bootstrap/');
const EXCLUDE_LIB       = normSep('vufind/web/interface/themes/responsive/css/lib/');
const INCLUDE_TABLESORTER = normSep('vufind/web/interface/themes/responsive/css/lib/tablesorter/');

function normSep(p) {
  return p.replace(/\\/g, '/');
}

function shouldCompile(filePath) {
  const fp = normSep(filePath);

  if (!fp.endsWith('.less')) return false;

  // Excluded: bootstrap (all descendants)
  if (fp.includes(EXCLUDE_BOOTSTRAP)) return false;

  // Excluded: lib (all descendants) — EXCEPT tablesorter
  if (fp.includes(EXCLUDE_LIB) && !fp.includes(INCLUDE_TABLESORTER)) return false;

  return true;
}

// ─── Compiler ────────────────────────────────────────────────────────────────

function compile(filePath) {
  if (!shouldCompile(filePath)) return;

  const absPath = path.resolve(filePath);
  const dir     = path.dirname(absPath);
  const input   = path.basename(absPath);                   // filename.less
  const output  = path.basename(absPath, '.less') + '.tmpcss'; // filename.tmpcss

  console.log(`[less] compiling ${filePath}`);
  try {
    execSync(
      `lessc "${input}" "${output}" --no-color --strict-imports`,
      { cwd: dir, stdio: 'inherit' }
    );
    console.log(`[less]  → ${path.join(dir, output)}`);
  } catch (err) {
    // lessc already printed the error via stdio: 'inherit'
    console.error(`[less]  ✗ failed: ${filePath}`);
  }
}

// ─── Watcher ─────────────────────────────────────────────────────────────────

const watcher = chokidar.watch('**/*.less', {
  ignored: /(^|[/\\])\../,   // ignore dotfiles/dot-dirs
  persistent: true,
  ignoreInitial: true,        // don't compile everything on startup
  awaitWriteFinish: {         // wait for the file to finish writing before firing
    stabilityThreshold: 200,
    pollInterval: 50
  }
});

watcher
  .on('add',    compile)
  .on('change', compile);

console.log('[less] Watching Less files for changes...');
