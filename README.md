# nuvis

A modern, responsive rebuild of the nuvis low-code platform — PHP/MySQL, fully self-hosted.

>  **Host:** A2Hosting (cPanel) · **Stack:** PHP8.1+, MySQL 5.7+, Apache

---

## Architecture

```
nuvis/
├── config.php                    # Global configuration
├── config.local.php.example      # Local override template
├── config.user_fields.php.example
├── index.php                     # App shell (login + SPA dashboard)
├── setup.php                     # Web-based installer
├── migrate.php                   # Migration runner from nuBuilder Forte 4.9
├── manifest.json                 # PWA manifest
├── sw.js                         # PWA service worker
├── .htaccess                     # Apache rewrite + security rules
│
├── install.sql                   # Base schema + seed data
├── install_phase3.sql            # Phase 3 schema additions
├── install_phase4.sql
├── install_phase5.sql
├── install_phase6.sql
├── install_phase7.sql
├── install_appcloner.sql         # App Cloner module schema
├── install_dashboard_widgets.sql # Dashboard widget schema
├── install_email.sql             # Email system schema
├── install_email_forms.sql       # Email-forms link schema
├── install_errorlog.sql          # Error log schema
├── install_menus_seed.sql        # Menu seed data
├── migrate_phase6_patch1.sql     # Phase 6 hotfix
├── migrate_phase8.sql            # Phase 8 migration
├── migrate_widget_icon.sql       # Widget icon migration
│
├── core/
│   ├── Database.php              # PDO database abstraction layer
│   ├── Auth.php                  # Session auth, lockout, 2FA hooks, CSRF, RBAC
│   ├── Audit.php                 # Audit trail logging
│   ├── Api.php                   # REST API foundation (token auth + rate limiting)
│   ├── FormRenderer.php          # Metadata-to-form renderer
│   ├── ReportRenderer.php        # SQL report renderer (tables + Chart.js)
│   ├── QueryExecutor.php         # Safe SELECT-only SQL executor
│   ├── MenuRenderer.php          # Dynamic menu renderer
│   ├── EmailService.php          # SMTP email service (full)
│   ├── Emailer.php               # Lightweight emailer wrapper
│   ├── ErrorLogger.php           # Structured error logging
│   ├── AppCloner.php             # App template clone engine (38KB)
│   ├── Workflow.php              # Workflow engine core
│   ├── PluginManager.php         # Plugin registration + loader
│   ├── module_bootstrap.php      # Module bootstrap helper
│   └── oldApi.php                # Legacy API shim (compatibility)
│
├── api/
│   ├── auth.php                  # Login / logout / me
│   ├── crud.php                  # Generic CRUD for any table
│   ├── form.php                  # Form runtime API
│   ├── report.php                # Report runtime API
│   ├── query.php                 # Query runtime API
│   ├── upload.php                # File upload handler
│   ├── export.php                # CSV / JSON export
│   └── import.php                # CSV import
│
├── modules/
│   ├── dashboard/                # KPI cards, activity feed, quick actions
│   ├── forms/                    # Form builder + runtime preview
│   ├── reports/                  # Report builder + table/chart output
│   ├── queries/                  # Query builder + parameter forms
│   ├── users/                    # User CRUD with roles
│   ├── roles/                    # Role/permission matrix
│   ├── menus/                    # Menu builder
│   ├── audit/                    # Audit trail viewer
│   ├── files/                    # File manager
│   ├── import_export/            # Data import/export tools
│   ├── appcloner/                # App template cloner UI
│   ├── calendar/                 # Calendar / scheduler module
│   ├── documents/                # Document management module
│   ├── errorlog/                 # Error log viewer
│   ├── inspector/                # Schema/data inspector
│   ├── integrations/             # External integrations
│   ├── password/                 # Password change UI
│   ├── password_policy/          # Password policy manager
│   ├── plugins/                  # Plugin management UI
│   ├── widgets/                  # Dashboard widget builder
│   ├── workflow/                 # Workflow engine UI
│   ├── ai/                       # AI integration module
│   ├── updater/                  # System Updater (Git auto-update & config manager)
│   └── email-settings.html       # Standalone email settings UI
│
└── assets/
    ├── css/nubuilder-next.css    # Design system (CSS custom properties)
    └── js/nubuilder-next.js      # App framework + utilities
```

---

## Requirements

- PHP 8.1+ (Fully compatible up to PHP 8.3)
- MySQL 5.7+ or MariaDB 10.3+ (Fully compatible with MySQL 8.0+)
- Apache with `mod_rewrite` enabled
- Modern browser (Chrome, Firefox, Safari, Edge)

---

## Quick Start

1. Upload all files to your web server
2. Visit `setup.php` in your browser
3. Follow the environment check and database installation steps
4. Login with `globeadmin` / `password` — **change this immediately**

## Manual Installation

```bash
# Import base schema
mysql -u root -p < install.sql

# Set directory permissions
chmod 755 uploads/ logs/

# Copy and edit config
cp config.local.php.example config.local.php
```

---


## Core Features

### Design System
- Fully responsive — mobile to desktop
- Dark / Light / Auto theme support
- Card-based UI with CSS custom properties
- Accessible keyboard navigation
- PWA ready (service worker + web manifest)

### Security
- Password hashing (bcrypt)
- Login attempt lockout
- Session timeout + regeneration
- 2FA hooks (TOTP ready)
- CSRF token generation
- Role-based permissions (RBAC)
- Full audit trail

### API Layer
- Token-based authentication with rate limiting
- Generic CRUD endpoints for any table
- Form / Report / Query runtime APIs
- File upload with type/size validation
- CSV / JSON import and export

### Runtime Renderers
- **FormRenderer** — turns JSON metadata into live data-entry forms with validation
- **ReportRenderer** — executes SQL safely, renders tables or Chart.js charts
- **QueryExecutor** — parameter binding, SELECT-only validation, CSV export

### Builders
- Visual form builder with drag-and-drop canvas, multi-level layout flattening, and instant rendering
- Report builder with SQL + column definitions + chart support
- Query builder with parameter definitions
- Menu builder for navigation management
- Embedded PHP code editor (Ace) for advanced browse/query customization
- Signature pad / picture canvas fields for drawn signatures and images

### Business Modules
- Dashboard with KPI cards and activity feed
- User / Role / Permission management
- Audit trail with pagination
- File manager with upload/delete
- Import / Export tools (CSV, JSON)
- Email system (SMTP, templates, form-triggered emails)
- Error log viewer with structured output
- Password management + configurable password policies
- App Cloner — clone and template entire applications
- Dashboard widgets (configurable, icon support)
- System Updater — Git auto-update & config manager (restricted to globeadmin/admin)

---

## Completed Extended Modules

### 🔄 Workflow Engine & Simulator
An advanced, production-ready business process automation module comprising:
- **Visual Builder:** Powered by Mermaid.js integration for beautiful, stage-colored flowchart representations.
- **Workflow Simulator:** Sandbox simulator where users run mock data JSON objects against defined transition conditional rules (`wft_condition`), dynamically highlighting the evaluated path on the flowchart in real time.
- **Kanban Board:** Dynamic, stage-colored drag-and-drop style Kanban board mapping active workflow instances across standard stages (Active, Completed, Rejected, Cancelled).
- **Transition Action Hooks:** Fully integrated action triggers (`wft_hook`) executing automatic emails (`send_email`), invoking external webhooks (`call_webhook`), or updating linked record database values (`update_record`) upon successful advances.
- **Robust Schema:** Implemented definitions, stage pipelines, instances, transition condition strings, and history trackers across `nu_workflows`, `nu_workflow_stages`, `nu_workflow_transitions`, `nu_workflow_instances`, and `nu_workflow_history`.

### 🌐 Outgoing Webhooks & Integrations
A highly configurable and secure external notification hub:
- **Customizable Requests:** Fully customizable outgoing webhook dispatchers with supports for distinct HTTP methods (GET, POST, PUT, PATCH) and dynamic key-value HTTP headers.
- **JSON Payload Templates:** Custom JSON or text template rendering with deep nested dot-notation placeholder resolution (e.g., `{{data.field}}`).
- **Trigger Actions:** Hooked directly into standard application saves, dynamic form builder saves, REST API CRUD events, successful logins (`Auth.php`), and workflow engine stage transitions.
- **Interactive Verification:** Live testing console equipped with a local mock webhook receiver (`api/webhook_demo_listener.php`) displaying delivery payloads and logs (`webhook_demo_logs.json`) interactively.

### 📥 Import / Export Wizard
A modern data-import pipeline built for rapid, reliable bulk uploads:
- **Interactive Wizard:** Multi-step wizard supporting file selection, CSV parsing on the client, and mapping.
- **Fuzzy Auto-Mapping:** Automatically analyzes CSV headers and matches them to physical database columns using an intuitive name-based fuzzy logic algorithm.
- **Flexible Parser & Type Casts:** Intelligent backend importing (`api/import.php`) featuring automatic date-parsing, numeric formatting cleanup (stripping symbols/currency), boolean normalization, and NULL-handling.
- **Security Checkpoints:** Strictly limits bulk-write operations to authorized tables, preventing overwrite attempts on sensitive core metadata tables (e.g. `nu_users`, `nu_forms`) by non-globeadmin users.

### ✉️ Email Administration & Send Log
A fully managed SMTP system and notification tracker:
- **Tabbed Settings UI:** Dynamic email configuration panel integrating SMTP credentials management, Email Template creation, and a centralized Send Log.
- **Send Log Popup Overlay:** Fully integrated modal detailed popup viewer presenting complete email transmission metadata (recipients, subjects, templates, errors, and actual raw mail headers) instantly upon row selection.
- **Transactional Hooks:** Mail automation bound directly to form submissions, custom scripts, and transition workflows.

### ⚙️ System Updater & Git Manager
The official system administrative terminal (locked to `globeadmin` or `admin` roles):
- **Git Connection Tester:** Visual interface equipped with an interactive **⚡ Test Git Connection** verification mechanism validating local executable paths and working directories.
- **Interactive Initialization:** Automates Git setups through an in-place "Initialize & Link Git Repository" command in instances where standard `.git` directories are missing.
- **Remote Update Panel:** Select, fetch, and pull commits directly from remote branches on the configured repository root, with strict overrides bypasses targeting modern web hosting permissions (e.g. `www-data` context safe directory checks).

---

## Technical Developments & Changelog

Over the course of recent developments, we have performed deep architectural refactoring to secure compatibility, clean developer experience, and robustness:

### 🛠️ Compatibility & Engine Hardening
- **PHP 8.1 - 8.3 Support:** Resolved string type-casting discrepancies within core PHP modules. Fixed built-in string functions (`strlen()`, `str_replace()`, `explode()`, `stripos()`) encountering null inputs by explicitly casting all nullable runtime variables to standard strings (e.g. `(string)$val`).
- **MySQL 8.0+ Integration:** Adapted JSON column handling and DDL schema scripts to comply with strict MySQL 5.7+ and 8.0+ rules. Escaped raw JSON strings inside the consolidated database installer by doubling single quotes (`''`) rather than standard backslash escaping (`\'`) to resolve JSON syntax issues during table seeding.
- **Dynamic Auto-Migrations:** Added robust self-healing try-catch DDL functions (`nu_ensure_custom_user_columns()` and menu columns check helpers) ensuring system tables (`nu_menus`, `nu_users`) dynamically adapt on-the-fly and prevent schema-related 500 errors on fresh installations or upgrades.
- **Safe SQL Splitter Parser:** Re-implemented the database installer parser (`setup.php`) to utilize a robust character-by-character SQL parsing algorithm. This correctly distinguishes semicolons inside single or double-quoted string literals (like inline CSS style statements in HTML templates) from statement delimiters, avoiding syntax crashes during database setup.

### 🖥️ Form Builder & Property Panel Enhancements
- **Recursive Layout Parsing:** Enhanced the form builder parsing utility (`_readFieldCard` in `assets/js/nb-form-builder.js`) to support recursive parsing on nested container elements (`group` and `tab` cards), ensuring deeply nested fields are correctly mapped, loaded, and persisted upon form save.
- **Properties Panel Synchronizer:** Nested advanced field types ('uploadbutton', 'signaturepad', 'picturecanvas', 'customnumber') inside the custom layout grid structure so that properties mirror and synchronized smoothly in the Right Properties Panel.
- **Quiet Field Evaluations:** Silenced unneeded and noisy client-side developer console errors stemming from blank optional properties or missing placeholders in form inputs to ensure clean browser error logging.

### 🛡️ Core Security & Performance
- **Menu Security Fallbacks:** Enhanced `NuMenuRenderer` to retrieve menu listings via `SELECT * FROM nu_menus` and dynamically default missing database schema values at runtime. This prevents strict PHP 8.1+ Undefined Array Key exceptions and safeguards sidebar layout rendering.
- **Double-Sync Menu Role Column:** Simultaneously writes role configuration constraints across both modern `menu_role_access` (JSON) and legacy `menu_roles` (comma-separated string) schemas during Menu updates, securing backward compatibility without code regression.
- **User Custom Fields Schema:** Structured custom user fields to write as JSON sequences directly inside the `usr_custom_fields` column of the `nu_users` table, with support for customizable dynamic database lookups (`lookup_table` and `lookup_field`).
- **JS Dynamic Script Scope Security:** Changed system JavaScript module definitions (such as Roles, Users, Webhooks) from block-scoped `const` to global-scoped `var` definitions to prevent `Uncaught SyntaxError` redeclaration errors when modules are dynamically loaded or re-evaluated inside the SPA application shell.
- **Inline UUID Handlers:** Enforced strict string wrapping (using quotes) when rendering Hyphenated UUIDs inside inline HTML event handlers (such. as `onclick="Users.openEdit('<?php echo $u['usr_id']; ?>')"`), preventing Javascript syntax crashes.

---

## Database Schema

### Core Tables
- `nu_users` — User accounts with roles (includes JSON `usr_custom_fields`)
- `nu_roles` — Role definitions
- `nu_permissions` — Permission catalog
- `nu_role_permissions` — Role-permission mapping

### Builder Tables
- `nu_forms` — Form metadata (JSON layout supporting nested layers, custom timestamps)
- `nu_reports` — Report metadata (SQL + columns)
- `nu_queries` — Query metadata (SQL + parameters)
- `nu_menus` — Navigation structure (role JSON access)

### System Tables
- `nu_api_tokens` — API keys for incoming syncs
- `nu_api_usage` — Rate limiting logs
- `nu_audit_log` — Core activity logging
- `nu_files` — Upload tracking
- `nu_email_*` — Email config, templates, queue, send logs
- `nu_error_log` — Structured error log
- `nu_widgets` — Dashboard widget definitions
- `nu_app_templates` — App Cloner templates
- `nu_webhooks` — Outgoing webhook routes and parameters

---


## Development Roadmap

### Phase 1 — Foundation ✅ Complete
- [x] App shell + responsive design system
- [x] Authentication + security layer (bcrypt, lockout, CSRF, RBAC)
- [x] Database abstraction (PDO)
- [x] Audit trail
- [x] REST API foundation (token auth, rate limiting)
- [x] Dashboard module (KPI cards, activity feed)
- [x] User / Role management
- [x] File uploads

### Phase 2 — Builders & Runtime ✅ Complete
- [x] Runtime form renderer (JSON → form, validation)
- [x] Runtime report renderer (tables + Chart.js)
- [x] Safe query executor with SELECT-only validation
- [x] Advanced field types (lookup, subform, repeater)
- [x] Menu builder UI
- [x] Import / Export (CSV, JSON)
- [x] Chart.js integration
- [x] SQL security hardening

### Phase 3 — Extended Modules ✅ Complete
- [x] Email system (SMTP, templates, form-triggered, settings UI, detailed log popup overlays)
- [x] Error log module (structured logging, viewer)
- [x] Password management + policy module
- [x] App Cloner (clone/template entire apps)
- [x] Dashboard widget builder (configurable, icon support)
- [x] MenuRenderer core class
- [x] Workflow engine core (`Workflow.php` with Mermaid.js visualizations, simulator, action hooks, Kanban boards)
- [x] Plugin manager core (`PluginManager.php` and UI)
- [x] System Updater & Config Manager (Git connection tester, in-place init, remote fetch/pull)
- [x] Layout flattening helpers + JSON response updates for forms
- [x] Embedded PHP code editor (Ace)
- [x] Signature pad / picture canvas field support
- [x] External integrations (Outgoing Webhooks, REST connectors, local mock receiver)
- [x] Import/Export (Interactive wizard, fuzzy column auto-mapping, parsing)

### Phase 4 — Polish & Scale 🔲 Planned
- [ ] Real-time collaboration (WebSockets or polling)
- [ ] OpenAPI / Swagger documentation
- [ ] Mobile PWA — offline support + push notifications
- [ ] Multi-language / i18n support
- [ ] MSSQL database compatibility
- [ ] Performance profiling + query optimization tools

---

## Security Notes

- All query executor SQL is validated as SELECT-only
- Dangerous keywords blocked at parser level
- Parameters bound with type sanitization
- File uploads restricted by type and size
- Sessions regenerate on login
- Passwords hashed with bcrypt
- `.htaccess` blocks direct access to `config.php`, `core/`, `sessions/`

---

## License

Open Source — MIT License
