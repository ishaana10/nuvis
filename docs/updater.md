# System Updater & Configuration Module

## Overview
The System Updater module manages core Git integrations and handles edits to the local environmental settings file `config.local.php`. It allows developers and admins (restricted to `globeadmin` and `admin` roles) to fetch git branch repositories, pull/upgrade code files directly from the browser window, view commit histories, edit local properties inside integrated code editors, and backup environmental structures safely before committing writes.

---

## Architecture & Key Files
- **`modules/updater/updater.php`**: Standard tabbed administrator viewport rendering version metrics, remote branches selectors, fetch/pull action triggers, and integrated Ace editors.
- **`api/updater.php`**: Secure administrative backend executing system CLI wrappers (such as `git status`, `git fetch`, `git pull`, and `git log`), reading configuration payloads, and writing validated code blocks.

---

## Technical Details

### Git CLI Integrations
All operations execute via shell wrapper utilities:
- **`git status`**: Maps modified assets, untracked changes, and commits offsets.
- **`git branch`**: Lists both active local branches and origin references.
- **`git pull`**: Dynamically merges code assets, resolving merge histories directly onto the web host.

*Commands are verified inside `api/updater.php` to run solely when invoked by valid administrative sessions, preventing command injection vectors.*

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/updater.php?action=git_status` | `GET` | `git_status` | Returns version numbers, active branches, modified listings, and remote options. |
| `/api/updater.php?action=git_fetch` | `GET` | `git_fetch` | Connects to remote origin repositories to index incoming files. |
| `/api/updater.php?action=git_pull` | `GET` | `git_pull` | Runs pull sequences to merge latest branch revisions. |
| `/api/updater.php?action=git_log` | `GET` | `git_log` | Returns a list of the 20 most recent repository commits (hash, author, date, message). |
| `/api/updater.php?action=config_read` | `GET` | `config_read` | Safely retrieves the raw content of `config.local.php`. |
| `/api/updater.php?action=config_write` | `POST` | `config_write` | Backs up the environmental configuration file, then overwrites it with newly edited inputs. |

---

## Usage Examples

### Executing a Git Pull via fetch API
```javascript
fetch('api/updater.php?action=git_pull', {
  method: 'GET',
  credentials: 'same-origin'
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("Git pull successful! Active on branch:", data.pulled_branch);
      console.log("Git Output console details:", data.output);
  } else {
      console.error("Git pull aborted:", data.error);
  }
});
```

### Overwriting Environmental Properties
```javascript
const newConfigContent = `<?php
// Secure database parameters
define('NU_DB_HOST', '127.0.0.1');
define('NU_DB_NAME', 'nuvis_production');
define('NU_DB_USER', 'db_user_prod');
define('NU_DB_PASS', 'Pr0dUct10n_P@ss!');
`;

fetch('api/updater.php?action=config_write', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ content: newConfigContent })
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("Configuration written. Backup generated successfully.");
  }
});
```
