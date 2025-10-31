# VS Code Configuration

This directory contains workspace settings for Visual Studio Code. These files let the project share a consistent debugging experience across the team.

## `launch.json`

- Provides two PHP debug configurations for the VS Code PHP Debug extension.
- `Listen for Xdebug` waits for incoming browser or web requests over port `9003`, logging Xdebug activity and mapping the container paths `/usr/local/pika` and `/usr/local/pika/vufind/web` to the workspace.
- `Debug current file (CLI)` runs the currently open PHP script from VS Code with Xdebug enabled for command-line scenarios, setting `client_host`, `client_port`, and `idekey` via `XDEBUG_CONFIG`.
