# VS Code Configuration

This directory contains workspace settings for Visual Studio Code. These files let developers setup a debuging enviroment for VS Code when working in WSL on Windows.

## `launch.json`

- Provides two PHP debug configurations for the VS Code PHP Debug extension.
- `Listen for Xdebug` waits for incoming browser or web requests over port `9003`, logging Xdebug activity and mapping the container paths `/usr/local/pika` and `/usr/local/pika/vufind/web` to the workspace.
- `Debug current file (CLI)` runs the currently open PHP script from VS Code with Xdebug enabled for command-line scenarios, setting `client_host`, `client_port`, and `idekey` via `XDEBUG_CONFIG`.

## `tasks.json`

Tasks that run the scripts in the root `package.json` (Terminal → Run Task…). The background file
watchers mirror the PhpStorm file watchers so the two editors produce the same build output. They
are started manually, either individually or together with **Watch All**; they do not run on
folder open. Each can also be run from a terminal with `npm run watch:sass` / `npm run watch:js`.

One-shot tasks: **Build all theme CSS** (`npm run build`, the default build task, Ctrl+Shift+B),
**Build core theme CSS** (`npm run build:core`) and **Vendor frontend assets** (`npm run vendor`).

- **Watch SASS** (`watch-sass.js`) — recompiles a theme's `main.css` and `main.min.css` when any
  `.scss` under `vufind/web/interface/themes` is saved, by calling `node build/css/build.mjs
  --file=<path>`. Only the theme that owns the saved file is rebuilt, so a change to a shared
  `responsive` partial still needs a full `npm run build` before committing.
- **Watch UglifyJS** (`watch-uglifyjs.js`) — minifies a saved `js/pika/*.js` to its `.min.js` and
  then remerges `pika.min.js`.

Both need `npm install` to have been run (they use `chokidar`).

The Less watchers that preceded Watch SASS (`watch-less.js`, `watch-postcss.js`,
`watch-cleancss.js`) were removed with the Less pipeline: dart-sass writes both the expanded and
the minified stylesheet in one pass, so there is nothing left to autoprefix or minify afterwards.
