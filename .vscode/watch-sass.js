#!/usr/bin/env node
/**
 * watch-sass.js — VS Code equivalent of the PhpStorm "SCSS (Pika build)" file watcher.
 *
 * Replaces the three-stage Less pipeline (watch-less.js → watch-postcss.js →
 * watch-cleancss.js) that Pika used before Bootstrap 5. dart-sass writes both
 * main.css and main.min.css in one pass, so there is nothing left to autoprefix
 * or minify afterwards.
 *
 * Scope: any *.scss file under vufind/web/interface/themes.
 *
 * Per-file command (run at the repository root):
 *   node build/css/build.mjs --file=<path>
 *
 * build.mjs resolves the theme that owns the saved partial and rebuilds that
 * theme's main.css and main.min.css. Shared partials of the responsive theme are
 * inherited by every other theme, so a change to one of those still needs a full
 * `npm run build` before committing.
 *
 * Requires: chokidar  →  npm install --save-dev chokidar
 */

const chokidar = require('chokidar');
const { execSync } = require('child_process');
const path = require('path');

// ─── Scope filter ────────────────────────────────────────────────────────────

const THEMES_ROOT = path.resolve('vufind/web/interface/themes');

function shouldCompile(filePath) {
  if (!filePath.endsWith('.scss')) return false;
  return path.resolve(filePath).startsWith(THEMES_ROOT);
}

// ─── Compiler ────────────────────────────────────────────────────────────────

function compile(filePath) {
  if (!shouldCompile(filePath)) return;

  const absPath = path.resolve(filePath);
  console.log(`[sass] compiling ${absPath}`);
  try {
    execSync(`node build/css/build.mjs --file="${absPath}"`, { stdio: 'inherit' });
    console.log(`[sass]  → theme rebuilt`);
  } catch (err) {
    console.error(`[sass]  ✗ failed: ${filePath}`);
  }
}

// ─── Watcher ─────────────────────────────────────────────────────────────────

const watcher = chokidar.watch(THEMES_ROOT, {
  ignoreInitial: true,
  ignored: /node_modules/,
  awaitWriteFinish: { stabilityThreshold: 200, pollInterval: 50 },
});

watcher.on('add', compile);
watcher.on('change', compile);

console.log('[sass] Watching theme scss files for changes...');
