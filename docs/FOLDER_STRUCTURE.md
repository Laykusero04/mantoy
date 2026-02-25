# Folder Structure

## Complete Directory Layout

```
mantoy/
│
├── index.php                        # Dashboard (entry point)
│
├── config/
│   ├── database.php                 # PDO database connection
│   └── constants.php                # App-wide constants (paths, limits, etc.)
│
├── includes/
│   ├── header.php                   # HTML head, navbar, sidebar start
│   ├── footer.php                   # Footer, closing tags, JS includes
│   ├── sidebar.php                  # Sidebar navigation component
│   └── functions.php                # Shared helper functions
│
├── pages/
│   ├── projects/
│   │   ├── list.php                 # All projects table (with filters)
│   │   ├── create.php               # New project form
│   │   ├── edit.php                 # Edit project form
│   │   ├── view.php                 # Single project detail (tabbed view)
│   │   └── archive.php             # Archived projects list
│   │
│   ├── reports/
│   │   ├── index.php                # Report selection page
│   │   ├── generate_pdf.php         # PDF report generation
│   │   └── generate_docx.php        # DOCX report generation
│   │
│   ├── settings/
│   │   ├── index.php                # Settings form (barangay info)
│   │   └── categories.php           # Manage custom categories
│   │
│   └── backup/
│       └── index.php                # Backup & restore page
│
├── actions/
│   ├── project_actions.php          # Create, update, delete, archive projects
│   ├── budget_actions.php           # Add, update, delete budget items
│   ├── milestone_actions.php        # Add, update, delete milestones
│   ├── action_log_actions.php       # Add, delete action log entries
│   ├── attachment_actions.php       # Upload, delete attachments
│   ├── category_actions.php         # Add, edit, delete categories
│   ├── settings_actions.php         # Save settings
│   └── backup_actions.php           # Export/import database, zip attachments
│
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css        # Bootstrap 5 (local copy)
│   │   └── custom.css               # Custom styles & overrides
│   │
│   ├── js/
│   │   ├── bootstrap.bundle.min.js  # Bootstrap 5 JS (local copy)
│   │   ├── chart.min.js             # Chart.js (local copy)
│   │   └── custom.js                # Custom JavaScript
│   │
│   ├── fonts/                       # Bootstrap Icons or local fonts
│   │   └── bootstrap-icons.css
│   │
│   └── img/
│       └── logo.png                 # Barangay logo (optional)
│
├── uploads/                         # Uploaded project attachments
│   ├── 1/                           # Folder per project ID
│   │   ├── document.pdf
│   │   └── photo.jpg
│   ├── 2/
│   └── ...
│
├── backups/                         # Database backup SQL files
│   ├── bdpt_backup_2026-01-15.sql
│   └── ...
│
├── vendor/                          # Third-party PHP libraries
│   ├── tcpdf/                       # PDF generation library
│   └── phpword/                     # DOCX generation library
│
└── docs/                            # Project documentation
    ├── PROJECT_DOCUMENTATION.md
    ├── DATABASE_SCHEMA.md
    ├── FOLDER_STRUCTURE.md
    ├── UI_WIREFRAMES.md
    └── ROUTES.md
```

---

## Directory Descriptions

| Directory       | Purpose                                                    |
|-----------------|------------------------------------------------------------|
| `config/`       | Database connection and app constants                      |
| `includes/`     | Reusable PHP components (header, footer, sidebar, helpers) |
| `pages/`        | All user-facing pages grouped by feature                   |
| `actions/`      | Form handlers and POST request processors                  |
| `assets/`       | Static files: CSS, JS, fonts, images (all local)           |
| `uploads/`      | User-uploaded files organized by project ID                |
| `backups/`      | SQL dump files from database export                        |
| `vendor/`       | Third-party libraries (TCPDF, PhpWord)                     |
| `docs/`         | Project documentation files                                |

---

## Key Files Explained

### `config/database.php`
- Creates a PDO connection to MySQL
- Sets error mode to exceptions
- Sets charset to utf8mb4
- Returns `$pdo` object used by all pages

### `config/constants.php`
- `BASE_URL` = `http://localhost/mantoy`
- `UPLOAD_DIR` = `__DIR__ . '/../uploads/'`
- `BACKUP_DIR` = `__DIR__ . '/../backups/'`
- `MAX_FILE_SIZE` = `10485760` (10MB)
- `ALLOWED_EXTENSIONS` = `['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx']`

### `includes/functions.php`
- `redirect($url)` -- header redirect helper
- `sanitize($input)` -- htmlspecialchars wrapper
- `formatCurrency($amount)` -- format as PHP currency
- `formatDate($date)` -- format date for display
- `getSettings($pdo)` -- fetch all settings as key-value array
- `flashMessage($type, $message)` -- session-based flash messages
- `getProjectCounts($pdo, $fiscalYear)` -- dashboard statistics
- `isOverdue($project)` -- check if project is past deadline

### `includes/header.php`
- Opens HTML document
- Includes Bootstrap CSS (local)
- Includes custom CSS
- Opens `<body>` and renders navbar
- Includes sidebar

### `includes/footer.php`
- Closes main content container
- Includes Bootstrap JS (local)
- Includes Chart.js (local)
- Includes custom JS
- Closes `</body></html>`

### `actions/*.php`
- All action files handle POST requests only
- Validate inputs and sanitize data
- Execute prepared PDO statements
- Set flash messages for success/error
- Redirect back to the referring page
