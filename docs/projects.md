# Multi-Project Support and Isolation

The Multi-Project module in Nuvis enables creating, isolating, managing, versioning, switching, and exporting independent application projects within a single Nuvis instance.

---

## Key Features

1. **Project Isolation**
   - Metadata tables (`nu_forms`, `nu_menus`, `nu_queries`, `nu_reports`, `nu_procedures`, `nu_workflows`, `nu_workflow_stages`, `nu_workflow_transitions`, `nu_form_versions`) include a `project_id` column.
   - All builder lists, APIs, and runtime renderers strictly filter by the active `project_id` in the user's session (`$_SESSION['nu_project_id']`).
   - Core system users, roles, permissions, global settings, and platform modules remain shared globally.

2. **Project Switcher & UI Context**
   - Header dropdown allows switching the active project on the fly.
   - Authorized users (`globeadmin`) can click `+ New Project` in the header or access `modules/projects/projects.php`.

3. **Access Control & Member Assignment**
   - `nu_project_members` maps users (`usr_id`) to projects (`pm_project_id`) with roles (`owner`, `admin`, `member`).
   - `globeadmin` has global access across all projects. Non-`globeadmin` users are restricted to assigned or default projects.

4. **Single-Project SQL Exports**
   - Integrated into `core/AppCloner.php` (`exportProject()`).
   - Exports project metadata, definitions, and registered physical form tables into a downloadable `.sql` or `.sql.gz` bundle for deployment or archiving.

5. **Project Version Control & Snapshots**
   - Capture point-in-time JSON metadata snapshots of all project definitions (`nu_project_versions`).
   - Supports listing, viewing summaries, and restoring/rolling back project metadata.

---

## Schema & Tables

- `nu_projects`: Stores project code, name, description, settings JSON, active status, default flag, and owner ID.
- `nu_project_members`: Maps user project memberships and project-level roles.
- `nu_project_tables`: Registry mapping form data tables to project IDs.
- `nu_project_versions`: Stores JSON snapshots of project metadata for version rollback.

---

## API Endpoints

The `api/projects.php` endpoint supports the following actions:
- `list`: Returns accessible projects and current project ID.
- `current`: Gets current active project details.
- `switch`: Sets active project ID in session (`project_id`).
- `create`: Creates a new project (Globe Admin only).
- `update`: Updates project name and description (Globe Admin only).
- `soft_delete`: Soft deletes a project.
- `members_list`: Lists project members and candidate users.
- `member_add` / `member_remove`: Assigns or removes user membership.
- `export`: Generates downloadable single-project SQL bundle.
- `version_create` / `version_list` / `version_get` / `version_restore`: Project snapshot versioning.
