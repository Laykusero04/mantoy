# Page Routes & Navigation

## URL Structure

Base URL: `http://localhost/mantoy/`

All navigation uses simple PHP file includes with query parameters. No routing framework needed.

---

## Pages (GET Requests)

| URL                                        | File                          | Description                        |
|--------------------------------------------|-------------------------------|------------------------------------|
| `/`                                        | `index.php`                   | Dashboard with summary & charts    |
| `/pages/projects/list.php`                 | `pages/projects/list.php`     | All projects list with filters     |
| `/pages/projects/list.php?type=Root`       | `pages/projects/list.php`     | Projects filtered by type          |
| `/pages/projects/list.php?status=Completed`| `pages/projects/list.php`     | Projects filtered by status        |
| `/pages/projects/list.php?year=2026`       | `pages/projects/list.php`     | Projects filtered by fiscal year   |
| `/pages/projects/create.php`               | `pages/projects/create.php`   | New project form                   |
| `/pages/projects/edit.php?id=1`            | `pages/projects/edit.php`     | Edit project form                  |
| `/pages/projects/view.php?id=1`            | `pages/projects/view.php`     | Project detail page (tabbed)       |
| `/pages/projects/view.php?id=1&tab=budget` | `pages/projects/view.php`     | Project detail, budget tab         |
| `/pages/projects/view.php?id=1&tab=milestones` | `pages/projects/view.php` | Project detail, milestones tab     |
| `/pages/projects/view.php?id=1&tab=log`    | `pages/projects/view.php`     | Project detail, action log tab     |
| `/pages/projects/view.php?id=1&tab=files`  | `pages/projects/view.php`     | Project detail, attachments tab    |
| `/pages/projects/archive.php`              | `pages/projects/archive.php`  | Archived projects list             |
| `/pages/reports/index.php`                 | `pages/reports/index.php`     | Report generation page             |
| `/pages/settings/index.php`               | `pages/settings/index.php`    | Barangay info settings             |
| `/pages/settings/categories.php`          | `pages/settings/categories.php`| Manage categories                 |
| `/pages/backup/index.php`                 | `pages/backup/index.php`      | Backup & restore page              |

---

## Actions (POST Requests)

All action files process form submissions and redirect back to the referring page.

### Project Actions (`actions/project_actions.php`)

| POST Parameter `action` | Description                    | Redirects To                  |
|--------------------------|--------------------------------|-------------------------------|
| `create`                 | Create a new project           | `view.php?id={new_id}`        |
| `update`                 | Update existing project        | `view.php?id={id}`            |
| `delete`                 | Soft-delete a project          | `list.php`                    |
| `archive`                | Archive a project              | `list.php`                    |
| `unarchive`              | Unarchive a project            | `archive.php`                 |
| `duplicate`              | Duplicate project as template  | `edit.php?id={new_id}`        |

**Required fields for `create`/`update`:**
- `title` (required)
- `project_type` (required)
- `fiscal_year` (required)
- All other fields optional

---

### Budget Actions (`actions/budget_actions.php`)

| POST Parameter `action` | Description                    | Redirects To                       |
|--------------------------|--------------------------------|------------------------------------|
| `add`                    | Add budget line item           | `view.php?id={project_id}&tab=budget` |
| `update`                 | Update budget line item        | `view.php?id={project_id}&tab=budget` |
| `delete`                 | Delete budget line item        | `view.php?id={project_id}&tab=budget` |

**Required fields for `add`/`update`:**
- `project_id` (required)
- `item_name` (required)
- `budget_category` (required)
- `quantity` (required)
- `unit` (required)
- `unit_cost` (required)

---

### Milestone Actions (`actions/milestone_actions.php`)

| POST Parameter `action` | Description                    | Redirects To                            |
|--------------------------|--------------------------------|-----------------------------------------|
| `add`                    | Add milestone                  | `view.php?id={project_id}&tab=milestones` |
| `update`                 | Update milestone               | `view.php?id={project_id}&tab=milestones` |
| `toggle`                 | Toggle milestone status        | `view.php?id={project_id}&tab=milestones` |
| `delete`                 | Delete milestone               | `view.php?id={project_id}&tab=milestones` |

**Required fields for `add`/`update`:**
- `project_id` (required)
- `title` (required)
- `target_date` (optional)

---

### Action Log Actions (`actions/action_log_actions.php`)

| POST Parameter `action` | Description                    | Redirects To                        |
|--------------------------|--------------------------------|-------------------------------------|
| `add`                    | Add log entry                  | `view.php?id={project_id}&tab=log`  |
| `delete`                 | Delete log entry               | `view.php?id={project_id}&tab=log`  |

**Required fields for `add`:**
- `project_id` (required)
- `action_taken` (required)
- `log_date` (required)
- `notes` (optional)
- `logged_by` (optional)

---

### Attachment Actions (`actions/attachment_actions.php`)

| POST Parameter `action` | Description                    | Redirects To                          |
|--------------------------|--------------------------------|---------------------------------------|
| `upload`                 | Upload file to project         | `view.php?id={project_id}&tab=files`  |
| `delete`                 | Delete attachment & file       | `view.php?id={project_id}&tab=files`  |

**Required fields for `upload`:**
- `project_id` (required)
- `file` (required, via `$_FILES`)
- `description` (optional)

**File validation:**
- Max size: 10MB
- Allowed types: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx
- File renamed to: `{timestamp}_{original_name}` to avoid conflicts

---

### Category Actions (`actions/category_actions.php`)

| POST Parameter `action` | Description                    | Redirects To                     |
|--------------------------|--------------------------------|----------------------------------|
| `add`                    | Add custom category            | `settings/categories.php`        |
| `update`                 | Rename category                | `settings/categories.php`        |
| `delete`                 | Delete category (custom only)  | `settings/categories.php`        |

**Notes:**
- Default categories cannot be deleted
- Deleting a category does NOT delete associated projects (sets `category_id` to NULL)

---

### Settings Actions (`actions/settings_actions.php`)

| POST Parameter `action` | Description                    | Redirects To                |
|--------------------------|--------------------------------|-----------------------------|
| `save`                   | Save all settings              | `settings/index.php`        |

**Fields:**
- `barangay_name`
- `municipality`
- `province`
- `prepared_by`
- `designation`
- `fiscal_year`

---

### Backup Actions (`actions/backup_actions.php`)

| POST Parameter `action` | Description                    | Response                         |
|--------------------------|--------------------------------|----------------------------------|
| `export_db`              | Export database as SQL          | File download (SQL)              |
| `export_files`           | Zip uploads folder             | File download (ZIP)              |
| `restore_db`             | Restore from uploaded SQL      | Redirect to `backup/index.php`   |

---

### Report Generation (GET with parameters)

| URL                                                    | Description                     |
|--------------------------------------------------------|---------------------------------|
| `pages/reports/generate_pdf.php?id=1`                  | Single project PDF report       |
| `pages/reports/generate_pdf.php?id=1&sections=all`     | Full project report             |
| `pages/reports/generate_pdf.php?type=summary&year=2026`| All projects summary PDF        |
| `pages/reports/generate_pdf.php?type=budget&year=2026` | Budget utilization report PDF   |
| `pages/reports/generate_pdf.php?type=status&year=2026` | Status report PDF               |
| `pages/reports/generate_pdf.php?type=overdue&year=2026`| Overdue projects report PDF     |
| `pages/reports/generate_docx.php?id=1`                 | Single project DOCX report      |

**Report sections parameter (comma-separated):**
- `details` - Project information
- `budget` - Budget breakdown table
- `milestones` - Milestones table
- `log` - Action log history
- `attachments` - Attachment file list
- `all` - Include everything

---

## Sidebar Navigation Structure

```
Dashboard              → index.php
Projects
  ├── All Projects     → pages/projects/list.php
  ├── New Project      → pages/projects/create.php
  └── Archived         → pages/projects/archive.php
Reports                → pages/reports/index.php
Settings
  ├── Barangay Info    → pages/settings/index.php
  └── Categories       → pages/settings/categories.php
Backup & Restore       → pages/backup/index.php
```

---

## Flash Messages

All action handlers set session flash messages before redirecting:

```php
$_SESSION['flash'] = [
    'type' => 'success',  // success | danger | warning | info
    'message' => 'Project created successfully.'
];
```

The `header.php` checks for flash messages and displays them as Bootstrap alerts at the top of the content area, then clears the session variable.

---

## Query Parameter Conventions

| Parameter   | Type    | Usage                                |
|-------------|---------|--------------------------------------|
| `id`        | INT     | Project ID for view/edit/delete      |
| `tab`       | STRING  | Active tab on project detail page    |
| `type`      | STRING  | Filter by project type               |
| `status`    | STRING  | Filter by project status             |
| `year`      | INT     | Filter by fiscal year                |
| `category`  | INT     | Filter by category ID                |
| `priority`  | STRING  | Filter by priority level             |
| `q`         | STRING  | Search keyword                       |
| `page`      | INT     | Pagination page number (default: 1)  |
| `action`    | STRING  | POST action identifier               |
| `sections`  | STRING  | Report sections to include           |
