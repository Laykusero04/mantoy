# Barangay Development Project Tracker (BDPT)

## System Documentation v1.0

---

## 1. Project Overview

**System Name:** Barangay Development Project Tracker (BDPT)

**Technology Stack:**
- **Backend:** PHP 8.x (Procedural / PDO)
- **Frontend:** Bootstrap 5, HTML5, CSS3, JavaScript
- **Database:** MySQL 8.x (via phpMyAdmin / XAMPP)
- **Server:** Apache (XAMPP localhost)
- **Reports:** TCPDF (PDF generation), PhpWord (DOCX generation)

**Deployment:** Single-user, offline, browser-based (`http://localhost/mantoy`)

**Purpose:**
A lightweight, offline project management system designed specifically for Barangay-level development planning. It tracks Root Projects, SEF (Special Education Fund) Projects, and Special Projects -- including budgets, progress, attachments, and generates printable reports for documentation and compliance.

---

## 2. Target Users

| Aspect         | Detail                                      |
|----------------|----------------------------------------------|
| User Count     | Single user (no login/authentication needed) |
| User Role      | Barangay Staff / Planner / Secretary         |
| Access Method  | Browser on local PC                          |
| Network        | No internet required (fully offline)         |

---

## 3. Core Features

### 3.1 Project Management (CRUD)

| Field              | Type         | Description                                       |
|--------------------|--------------|---------------------------------------------------|
| Project Title      | VARCHAR(255) | Name of the project                               |
| Project Type       | ENUM         | Root Project / SEF Project / Special Project       |
| Category           | VARCHAR(100) | Predefined + custom categories                    |
| Description        | TEXT         | Detailed project description                      |
| Priority           | ENUM         | High / Medium / Low                               |
| Status             | ENUM         | Not Started / In Progress / On Hold / Completed / Cancelled |
| Funding Source     | VARCHAR(150) | IRA, SEF, Donations, LGU Aid, etc.                |
| Resolution No.     | VARCHAR(50)  | Barangay resolution reference number              |
| Budget Allocated   | DECIMAL      | Approved budget amount                            |
| Actual Cost        | DECIMAL      | Running actual expenditure                        |
| Start Date         | DATE         | Planned start date                                |
| Target End Date    | DATE         | Planned completion date                           |
| Actual End Date    | DATE         | Actual completion date (nullable)                 |
| Fiscal Year        | YEAR         | Year the project belongs to                       |
| Location           | VARCHAR(255) | Sitio / Purok / area description                  |
| Contact Person     | VARCHAR(150) | Person in charge or project lead                  |
| Remarks            | TEXT         | General notes                                     |

**Operations:**
- Create new project with all fields
- Edit project details at any time
- Soft-delete projects (mark as archived, not permanently removed)
- Duplicate a project as a template for similar future projects
- Filter projects by type, status, fiscal year, category, priority

**Predefined Categories:**
- Infrastructure (Roads, Bridges, Buildings)
- Health & Sanitation
- Education
- Livelihood & Agriculture
- Peace & Order
- Disaster Preparedness
- Environmental Protection
- Social Welfare
- Sports & Youth Development
- Other / Custom

### 3.2 Budget Breakdown

Each project can have an itemized budget with line items:

| Field          | Description                            |
|----------------|----------------------------------------|
| Item Name      | e.g., "Cement", "Labor", "Equipment"   |
| Category       | MOOE / Capital Outlay / Personnel      |
| Quantity       | Number of units                        |
| Unit           | pcs, bags, days, lot, etc.             |
| Unit Cost      | Cost per unit                          |
| Total Cost     | Auto-computed (Quantity x Unit Cost)    |

- The sum of all line items = Total Estimated Cost
- Compare against Budget Allocated for variance tracking
- Budget utilization percentage displayed per project

### 3.3 Project Milestones

Break large projects into trackable milestones:

| Field            | Description                          |
|------------------|--------------------------------------|
| Milestone Title  | e.g., "Foundation Complete"          |
| Target Date      | When it should be done               |
| Completed Date   | When it was actually completed       |
| Status           | Pending / Done                       |
| Notes            | Additional details                   |

- Visual progress bar based on completed milestones vs total
- Milestones displayed in timeline view on project detail page

### 3.4 Action Log / Progress Tracking

Manual progress entries per project:

| Field          | Description                          |
|----------------|--------------------------------------|
| Action Taken   | What was done                        |
| Notes          | Additional details                   |
| Date/Time      | When the action was recorded         |
| Logged By      | Name of person (free text)           |

- Entries displayed in reverse chronological order
- Serves as an audit trail of project activity
- Exportable as part of the project report

### 3.5 File Attachments

| Field          | Description                          |
|----------------|--------------------------------------|
| File Name      | Original name of the uploaded file   |
| File Type      | Image / PDF / Document               |
| File Size      | Size in KB/MB                        |
| Date Uploaded  | Timestamp                            |
| Description    | Optional label/note for the file     |

- Supported formats: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX
- Max file size: 10MB per file
- Files stored in `/uploads/{project_id}/` folder
- File metadata stored in database
- Image preview thumbnails in the project detail page
- PDF files openable in browser

### 3.6 Dashboard

The landing page provides a quick overview:

**Summary Cards:**
- Total Projects (all time)
- Projects This Fiscal Year
- Not Started count
- In Progress count
- Completed count
- On Hold count
- Total Budget Allocated (current fiscal year)
- Total Actual Cost (current fiscal year)
- Budget Utilization % (current fiscal year)

**Charts (using Chart.js):**
- Pie chart: Projects by Status
- Bar chart: Projects by Type (Root / SEF / Special)
- Bar chart: Budget Allocated vs Actual Cost per Project Type
- Line chart: Monthly project completions (current year)

**Quick Access:**
- Recent projects list (last 5 updated)
- Overdue projects (past target end date but not completed)
- Upcoming deadlines (projects ending within 30 days)

### 3.7 Search & Filter

Global search bar and advanced filters:

| Filter          | Options                                         |
|-----------------|--------------------------------------------------|
| Project Type    | Root / SEF / Special / All                       |
| Status          | Not Started / In Progress / Completed / On Hold / Cancelled / All |
| Fiscal Year     | Dropdown of available years                      |
| Category        | Dropdown of categories                           |
| Priority        | High / Medium / Low / All                        |
| Date Range      | Start date - End date                            |
| Keyword Search  | Searches title, description, remarks             |

- Filters persist during the session
- Results displayed in a sortable table
- Export filtered results to PDF or Excel

### 3.8 Printable Reports

**Per-Project Report (PDF/DOCX):**
- Project header (title, type, resolution no.)
- Full project details (dates, budget, status, location, etc.)
- Budget breakdown table
- Milestones table with status
- Action log / progress history
- List of attachments (file names only, not embedded)
- Generated date and "Prepared by" field

**Summary Reports:**
- All Projects Summary (filtered by fiscal year)
- Budget Utilization Report
- Project Status Report (grouped by type)
- Overdue Projects Report

### 3.9 Data Backup & Restore

Since this is an offline system, data safety is critical:

- **Export Database:** One-click SQL dump of the entire database saved to `/backups/` folder with timestamp
- **Restore Database:** Upload a previously exported SQL file to restore data
- **Export Attachments:** Zip all uploaded files for backup
- Backup reminder shown on dashboard if last backup is older than 7 days

### 3.10 Archive System

- Completed projects from previous fiscal years can be archived
- Archived projects are hidden from the main list but accessible via "Archives" page
- Archived projects are read-only (no edits allowed)
- Unarchive option available if needed

### 3.11 Settings

| Setting              | Description                                  |
|----------------------|----------------------------------------------|
| Barangay Name        | Used in report headers                       |
| Municipality/City    | Used in report headers                       |
| Province             | Used in report headers                       |
| Prepared By (Name)   | Default name for report signatures           |
| Position/Designation | Title of the user for reports                |
| Fiscal Year Default  | Current active fiscal year                   |
| Currency Symbol      | Default: PHP                                  |
| Custom Categories    | Add/edit/remove project categories           |

---

## 4. Workflow

### Step 1: Initial Setup
1. Configure barangay info in Settings
2. Set the current fiscal year
3. Add custom categories if needed

### Step 2: Add New Project
1. Click "New Project" from sidebar or dashboard
2. Select project type (Root / SEF / Special)
3. Fill in title, description, category, location
4. Set priority, status, dates
5. Enter funding source and resolution number
6. Set budget allocation
7. Save -- project is created and stored in MySQL

### Step 3: Add Budget Breakdown (Optional)
1. Open project detail page
2. Go to "Budget" tab
3. Add line items (name, category, qty, unit, cost)
4. System auto-computes totals and utilization %

### Step 4: Add Milestones (Optional)
1. Open project detail page
2. Go to "Milestones" tab
3. Add milestones with target dates
4. Mark milestones as done when completed
5. Progress bar updates automatically

### Step 5: Upload Attachments
1. Open project detail page
2. Go to "Attachments" tab
3. Click "Upload File" and select files
4. Files stored in `/uploads/{project_id}/`
5. File metadata saved in database

### Step 6: Log Progress / Actions
1. Open project detail page
2. Go to "Action Log" tab
3. Enter action taken, notes, and date
4. Entry saved in reverse-chronological list

### Step 7: Monitor via Dashboard
1. Visit dashboard (home page)
2. Review summary cards and charts
3. Check overdue and upcoming deadlines
4. Click on any project for quick access

### Step 8: Generate Reports
1. Open project detail page OR go to Reports page
2. Select report type
3. Choose format (PDF or DOCX)
4. Download or print the report

### Step 9: End of Fiscal Year
1. Review all projects for the year
2. Archive completed projects
3. Update fiscal year in settings
4. Backup database and attachments

---

## 5. Non-Functional Requirements

| Requirement        | Detail                                        |
|--------------------|------------------------------------------------|
| Performance        | Page load < 2 seconds (local)                 |
| Browser Support    | Chrome, Edge, Firefox (latest)                |
| Responsive         | Desktop-first, tablet-friendly                |
| Data Integrity     | Foreign key constraints, input validation     |
| File Security      | File type validation, size limits enforced     |
| Backup             | Manual SQL export + file zip                  |
| Offline            | 100% offline, no external CDN dependencies    |
| Scalability        | Designed for hundreds of projects (barangay-level) |

---

## 6. Technology Decisions

| Decision                        | Rationale                                      |
|---------------------------------|------------------------------------------------|
| PHP Procedural (not framework)  | Simple deployment on XAMPP, no Composer needed for core |
| PDO for database                | Prepared statements prevent SQL injection      |
| Bootstrap 5 (local files)       | Offline-compatible, responsive, professional UI|
| Chart.js (local files)          | Lightweight charts, no internet needed         |
| TCPDF                           | Pure PHP PDF generation, no external tools     |
| PhpWord                         | DOCX generation for Word reports               |
| No login system                 | Single user, localhost only -- no auth needed  |
| Soft delete                     | Prevent accidental data loss                   |

---

## 7. Folder Structure Overview

See `FOLDER_STRUCTURE.md` for the complete directory layout.

## 8. Database Schema

See `DATABASE_SCHEMA.md` for the complete database design.

## 9. Page Routes & Navigation

See `ROUTES.md` for all pages and URL patterns.

## 10. UI Layout & Wireframes

See `UI_WIREFRAMES.md` for page layout descriptions.
