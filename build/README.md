# Pika Frontend Build

Toolchain for compiling theme stylesheets (SCSS → CSS) and vendoring frontend
assets (Bootstrap JS bundle, Bootstrap Icons fonts). This replaces the old
JetBrains file-watcher chain (`lessc` → `.tmpcss` → `postcss autoprefixer` →
`.css` → `cleancss` → `.min.css`).

**Servers never need Node.** Compiled `main.css` / `main.min.css` and vendored
assets are committed to git, so deploys are unchanged. Node (>= 18) and
`npm install` are only needed on developer machines that edit stylesheets.

## Commands

| Command | What it does |
|---|---|
| `npm install` | One-time setup (and after dependency bumps) |
| `npm run build` | Compile every theme that has `css/main.scss` → `main.css` + `main.min.css` |
| `npm run build:core` | Compile only the `responsive` core theme |
| `npm run build -- --theme=marmot` | Compile a single library theme |
| `npm run watch` | Watch all theme `css/` dirs; recompile a theme when one of its `.scss` files changes |
| `npm run vendor` | Copy `bootstrap.bundle.min.js` and Bootstrap Icons fonts from `node_modules` into the web tree |

Note: editing a file under `responsive/css/` only recompiles `responsive` in
watch mode. Library themes import the responsive base, so run a full
`npm run build` before committing CSS that follows a change to responsive SCSS.

## Pipeline

`vufind/web/interface/themes/<theme>/css/main.scss`
→ dart-sass (`loadPaths: node_modules`, so `@import "bootstrap/scss/..."` resolves)
→ postcss + autoprefixer (targets from `.browserslistrc`)
→ `main.css` (debug mode, `$debugCss`)
→ + cssnano → `main.min.css` (production)

Output filenames are unchanged from the LESS era on purpose — the `{css}`
Smarty plugin (`vufind/web/interface/plugins/function.css.php`) walks the theme
chain looking for `css/main.min.css`.

## Commit convention

Stylesheet changes are committed in pairs: SCSS source first with the subject
prefixed `SASS - `, then the compiled output with `CSS - ` and an otherwise
identical message.

## IDE file watchers (optional)

`npm run watch` is the supported path, but a JetBrains file watcher can invoke
the same compiler if you prefer save-triggered builds:

- Program: `node`
- Arguments: `build/css/build.mjs --theme=$FileParentDirName$` *(for a file at
  `themes/<theme>/css/foo.scss`, `$FileParentDirName$` won't resolve the theme
  name directly — simplest reliable setup is one watcher scoped to the theme
  you're working on with `--theme=<name>` hardcoded, or just use `npm run watch`)*
- Working directory: `$PROJECT_DIR$`

The old lessc / PostCSS Autofixer / CleanCSS watchers should be disabled for
`.scss` work; they only apply to `.less` files and will be removed with the
LESS sources at the end of the Bootstrap 5 migration.

## Known deprecation

Bootstrap 5.x still uses Sass `@import`, which dart-sass deprecates (removal in
dart-sass 3.0). The build silences the `import` deprecation; the `@use`
migration comes with Bootstrap 6.
