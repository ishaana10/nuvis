# System Updater & Configuration Module

## Overview
The System Updater module manages core Git integrations and handles edits to the local environmental settings file `config.local.php`. It allows developers and admins (restricted to `globeadmin` and `admin` roles) to configure the system environment, fetch git branch repositories, pull/upgrade code files directly from the browser window, view commit histories, edit local properties inside integrated code editors, and backup environmental structures safely before committing writes.

---

## Architecture & Key Files
- **`modules/updater/updater.php`**: Standard tabbed administrator viewport rendering version metrics, remote branches selectors, custom Git settings (Executable path and Directory root), connection tester, fetch/pull action triggers, and integrated Ace editors.
- **`api/updater.php`**: Secure administrative backend executing system CLI wrappers (such as `git status`, `git fetch`, `git pull`, and `git log`), reading configuration payloads, and writing validated code blocks.

---

## Technical Details

### Git CLI Integrations
All operations execute via shell wrapper utilities. By default, Git commands are executed within the specified repository context using the `-C` flag to prevent path issues when PHP runs in a different directory scope (such as `/var/www` or `/`).

The following dynamic parameters can be configured:
- **Git Executable Path** (`git_path`): Path to the `git` binary. Defaults to `git`, but can be absolute (e.g., `/usr/bin/git`).
- **Git Repository Root Directory** (`git_repo_dir`): The working tree folder of the Git repository. Defaults to the relative application root directory.
- **Update Branch** (`update_branch`): The active branch to perform fetch and checkout operations (e.g. `main` or custom branches).

### Safe Directory Config
Commands are verified inside `api/updater.php` to run solely when invoked by valid administrative sessions. They are passed `-c safe.directory=*` to ensure Git can execute operations under different OS users (e.g. `www-data` or a cPanel user context).

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/updater.php?action=git_status` | `GET` | `git_status` | Returns version numbers, active branches, configuration settings, and remote options. |
| `/api/updater.php?action=save_branch` | `POST` | `save_branch` | Saves only the active update branch preference. |
| `/api/updater.php?action=save_git_settings` | `POST` | `save_git_settings` | Persists custom Git paths and repository configuration to database. |
| `/api/updater.php?action=test_git_settings` | `POST` | `test_git_settings` | Validates specified paths and checks if git is executable and repo directory is correct. |
| `/api/updater.php?action=git_fetch` | `GET` | `git_fetch` | Connects to remote origin repositories to index incoming files using configured settings. |
| `/api/updater.php?action=git_pull` | `GET` | `git_pull` | Runs pull sequences to merge latest branch revisions into current directory. |
| `/api/updater.php?action=git_log` | `GET` | `git_log` | Returns a list of the 20 most recent repository commits (hash, author, date, message). |
| `/api/updater.php?action=config_read` | `GET` | `config_read` | Safely retrieves the raw content of `config.local.php`. |
| `/api/updater.php?action=config_write` | `POST` | `config_write` | Backs up the environmental configuration file, then overwrites it with newly edited inputs. |

---

## Configuration Guidelines

### Local Environment Config
To configure your Git integration, go to **Updates** tab under **System Updater & Config**.
1. Set the **Git Executable Path** (usually `git` or `/usr/bin/git`).
2. Set the **Git Repository Root Directory** (the full absolute path to the folder containing `.git`, such as `/home/user/public_html/nbv5`).
3. Click **⚡ Test Git Connection** to verify that both options are valid.
4. Click **Save Connection Settings** to store settings permanently.

### Troubleshooting Common Configuration Errors
* **Error: `gh repo clone ishaana10/nuvis: No such file or directory` or command not found**
  - **Cause:** You pasted the full GitHub CLI command (`gh repo clone...` or `git clone...`) into the **Git Executable Path** field.
  - **Solution:** Change the Git Executable Path back to simply `git`. Do not input clone commands.
* **Error: `fatal: not a git repository (or any of the parent directories): .git`**
  - **Cause:** The **Git Repository Root Directory** is incorrect, or pointing to a directory that doesn't contain the `.git` metadata folder.
  - **Solution:** Verify the full path of your application directory and ensure the `.git` folder exists. Set the field to the exact absolute path (e.g. `/home/user/public_html/nbv5`).

### SSH Keys & Host Permissions
- Ensure the user running the PHP/Apache server process has sufficient permissions to write to the repository files.
- If using a private repository, configure SSH keys for the web server user or store repository credentials securely using the Git credential helper.
