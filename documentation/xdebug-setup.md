# Xdebug Setup

This guide explains how to add [Xdebug](https://xdebug.org/) to a Pika developer workstation so you can debug PHP code in Visual Studio Code. The steps below assume you are running PHP 8 on a Linux host or in a WSL instance on Windows running a Linux (CentOS, Rocky, Alma, Ubuntu, etc.) with Apache serving the site from `/usr/local/pika/vufind/web`. Adjust paths as needed if your environment differs.

## 1. Install the Xdebug extension

1. Confirm the PHP binary you use for Pika (both Apache **and** CLI) so that the extension is compiled for the correct version:

   ```bash
   php -v
   php --ini
   php-config --extension-dir
   ```

2. Install Xdebug for that PHP build. The PECL installer works on all distros:

   ```bash
   sudo pecl install xdebug
   ```

   *If the PECL command is unavailable, install the `php-pear`/`phpX.Y-pear` package first. On Debian/Ubuntu you can alternatively use `sudo apt install php-xdebug`; on RHEL-compatible distros you can use `sudo dnf install php-pecl-xdebug` when available.*

The command prints the path to the compiled `xdebug.so`—note that location.

## 2. Enable Xdebug in PHP

Create an Xdebug configuration file in your PHP configuration directory (one of the `Scan for additional .ini files` paths reported by `php --ini`). For most setups a file like `/etc/php.d/40-xdebug.ini` (RHEL/Rocky) or `/etc/php/8.0/mods-available/xdebug.ini` (Debian/Ubuntu) works well.

```ini
; /etc/php.d/40-xdebug.ini
zend_extension = "xdebug.so"

xdebug.mode = debug,develop
xdebug.start_with_request = yes
xdebug.idekey = VSCODE

; Make sure this machine can talk back to VS Code.
; If Apache/PHP runs inside a VM or container, set this to the host IP
; (for Docker on Mac/Windows you can use host.docker.internal).
xdebug.client_host = 127.0.0.1
xdebug.client_port = 9003

xdebug.log = /var/log/php/xdebug.log
xdebug.log_level = 0
xdebug.max_nesting_level = 512
```

Key points:

- `xdebug.mode` includes `debug` for step-by-step debugging and `develop` for better stack traces.
- `xdebug.start_with_request = yes` activates Xdebug on every request so VS Code can attach immediately. Switch to `trigger` if you prefer to use the `XDEBUG_SESSION` cookie/header instead.
- `xdebug.client_host` must resolve to the machine running VS Code. When debugging a remote server, set this to your workstation’s IP address or DNS name.

After creating the file, enable it for Apache:

- **Debian/Ubuntu:** `sudo phpenmod xdebug`
- **RHEL/Rocky/Alma:** nothing extra is needed if the file lives in `/etc/php.d`.

Restart Apache (and PHP-FPM if you use it):

```bash
sudo systemctl restart httpd   # or apache2
```

## 3. Verify the installation

1. Run `php -m | grep xdebug` and ensure `xdebug` appears.
2. Load `phpinfo()` (you can temporarily add `phpinfo(); exit;` near the top of `vufind/web/index.php`) and confirm the Xdebug section shows your settings.
3. Check the log at `/var/log/php/xdebug.log` after the first debug session—it should contain connection attempts from Apache.

## 4. Prepare Visual Studio Code

1. Install the official **PHP Debug** VS Code extension (by Xdebug).
2. Copy the repository-provided `.vscode/launch.json` (committed with this guide) or create one with the following configuration. It listens for Xdebug on port `9003` and maps server paths back to the checkout (`/usr/local/pika` → workspace root):

   ```json
   {
     "version": "0.2.0",
     "configurations": [
       {
         "name": "Listen for Xdebug",
         "type": "php",
         "request": "launch",
         "port": 9003,
         "log": true,
         "xdebugSettings": {
           "max_children": 128,
           "max_data": 1024
         },
         "pathMappings": {
           "/usr/local/pika": "${workspaceFolder}",
           "/usr/local/pika/vufind/web": "${workspaceFolder}/vufind/web"
         }
       }
     ]
   }
   ```

3. Start the **Listen for Xdebug** configuration in VS Code (Run and Debug panel). VS Code will wait for incoming connections.
4. Visit a page in your Pika instance or run a CLI entry point (see below). Execution should pause on your breakpoints.

### CLI debugging

Xdebug also works for CLI scripts (e.g., `php vufind/web/index.php ...`). Add the following environment variables before launching the script so the CLI process connects back to VS Code:

```bash
export XDEBUG_MODE=debug
export XDEBUG_CONFIG="client_host=127.0.0.1 client_port=9003 idekey=VSCODE"
php path/to/script.php
```

## 5. Troubleshooting tips

- If VS Code never receives a connection, check the Xdebug log for connection errors and confirm `client_host` is reachable.
- Ensure firewalls (local or network) allow traffic on TCP port `9003`.
- Verify you are editing the same PHP configuration used by Apache: compare `Loaded Configuration File` from `phpinfo()` inside the browser to the one from `php --ini` in the terminal.
- When debugging requests served through HTTPS offloaders or proxies, propagate the `XDEBUG_SESSION=1` cookie to force-enable Xdebug when `start_with_request=trigger`.

You’re now ready to debug the Pika Discovery Layer with breakpoints, watches, and call stacks directly from Visual Studio Code.
