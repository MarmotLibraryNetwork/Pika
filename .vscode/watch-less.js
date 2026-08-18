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
 * Deviation from PhpStorm: partials such as responsive/css/repository.less are
 * shared — other themes' main.less files pull them in too (e.g. marmot/css/main.less
 * → responsive/css/responsive_base.less → repository.less). So after compiling the
 * saved file, every theme directory's main.less is also recompiled, cascading the
 * change through to main.tmpcss → main.css → main.min.css (via the other two
 * watchers) for all themes, not just the one that was saved in.
 *
 * Requires: chokidar  →  npm install --save-dev chokidar
 */

const chokidar = require('chokidar');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

// ─── Scope filter ────────────────────────────────────────────────────────────

const EXCLUDE_BOOTSTRAP = normSep('vufind/web/interface/themes/responsive/css/bootstrap/');
const EXCLUDE_LIB       = normSep('vufind/web/interface/themes/responsive/css/lib/');
const INCLUDE_TABLESORTER = normSep('vufind/web/interface/themes/responsive/css/lib/tablesorter/');
const THEMES_ROOT = path.resolve('vufind/web/interface/themes');

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

function compileMain(cssDir) {
  const mainPath = path.join(cssDir, 'main.less');
  console.log(`[less] cascading to ${mainPath}`);
  try {
    execSync(
      `lessc "main.less" "main.tmpcss" --no-color --strict-imports`,
      { cwd: cssDir, stdio: 'inherit' }
    );
    console.log(`[less]  → ${path.join(cssDir, 'main.tmpcss')}`);
  } catch (err) {
    console.error(`[less]  ✗ failed: ${mainPath}`);
  }
}

function cascadeToAllThemes(skipDir) {
  for (const themeName of fs.readdirSync(THEMES_ROOT)) {
    const cssDir = path.join(THEMES_ROOT, themeName, 'css');
    if (cssDir === skipDir) continue;
    if (!fs.existsSync(path.join(cssDir, 'main.less'))) continue;
    compileMain(cssDir);
  }
}

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
    return;
  }

  cascadeToAllThemes(input === 'main.less' ? dir : null);
}

// ─── Watcher ─────────────────────────────────────────────────────────────────

const watcher = chokidar.watch('vufind/web/interface/themes', {
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
