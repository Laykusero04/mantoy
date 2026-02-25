# UI Layout & Wireframe Descriptions

## Global Layout

All pages share a consistent layout using Bootstrap 5:

```
┌─────────────────────────────────────────────────────────┐
│  NAVBAR (top, fixed)                                    │
│  [Logo] Barangay Dev Project Tracker      [Backup] [⚙]  │
├──────────┬──────────────────────────────────────────────┤
│          │                                              │
│ SIDEBAR  │  MAIN CONTENT AREA                           │
│ (left,   │                                              │
│  fixed)  │  Breadcrumb: Home > Projects > View          │
│          │                                              │
│ Dashboard│  ┌──────────────────────────────────────┐    │
│ Projects │  │                                      │    │
│  > All   │  │   Page-specific content here         │    │
│  > New   │  │                                      │    │
│  > Archive│ │                                      │    │
│ Reports  │  │                                      │    │
│ Settings │  │                                      │    │
│  > Info  │  └──────────────────────────────────────┘    │
│  > Cats  │                                              │
│ Backup   │  ┌──────────────────────────────────────┐    │
│          │  │  FOOTER                              │    │
│          │  │  Barangay Dev Project Tracker v1.0    │    │
│          │  └──────────────────────────────────────┘    │
└──────────┴──────────────────────────────────────────────┘
```

**Navbar:** Dark-themed top bar with app name, backup button, and settings gear icon.

**Sidebar:** Left-side vertical navigation (collapsible on smaller screens). Uses Bootstrap Icons for menu items.

**Main Content:** White background card-based layout. Includes breadcrumbs at top.

**Footer:** Simple bottom bar with app version.

---

## Color Scheme

| Element            | Color                    | Bootstrap Class       |
|--------------------|--------------------------|-----------------------|
| Navbar             | Dark blue                | `bg-dark` or custom   |
| Sidebar            | Light gray               | `bg-light`            |
| Primary buttons    | Blue                     | `btn-primary`         |
| Success/Completed  | Green                    | `btn-success`         |
| Warning/On Hold    | Yellow                   | `btn-warning`         |
| Danger/Cancelled   | Red                      | `btn-danger`          |
| Info/In Progress   | Cyan                     | `btn-info`            |
| Not Started        | Secondary gray           | `btn-secondary`       |
| Cards              | White with light border  | `card`                |

---

## Page: Dashboard (`index.php`)

```
┌──────────────────────────────────────────────────────────┐
│  Dashboard                                    FY: [2026 ▼]│
├──────────────────────────────────────────────────────────┤
│                                                          │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐    │
│  │ TOTAL    │ │ NOT      │ │ IN       │ │ COMPLETED│    │
│  │ PROJECTS │ │ STARTED  │ │ PROGRESS │ │          │    │
│  │   24     │ │    8     │ │    10    │ │    6     │    │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘    │
│                                                          │
│  ┌──────────┐ ┌──────────┐ ┌──────────────────────────┐  │
│  │ ON HOLD  │ │ BUDGET   │ │ UTILIZATION             │  │
│  │    2     │ │ ₱1.5M    │ │ ████████░░ 78%          │  │
│  └──────────┘ └──────────┘ └──────────────────────────┘  │
│                                                          │
│  ┌─────────────────────────┐ ┌────────────────────────┐  │
│  │  PIE CHART              │ │  BAR CHART             │  │
│  │  Projects by Status     │ │  Budget by Type        │  │
│  │                         │ │                        │  │
│  │     ██████              │ │  ▓▓▓   ▓▓  ▓▓▓▓       │  │
│  │   ████████              │ │  Root  SEF  Special    │  │
│  └─────────────────────────┘ └────────────────────────┘  │
│                                                          │
│  ┌─────────────────────────────────────────────────────┐  │
│  │  ⚠ OVERDUE PROJECTS                                │  │
│  │  ┌─────────────────────────────────────────────────┐│  │
│  │  │ Road Repair Phase 2    │ Due: Jan 15 │ [View]  ││  │
│  │  │ Drainage System        │ Due: Dec 30 │ [View]  ││  │
│  │  └─────────────────────────────────────────────────┘│  │
│  └─────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌─────────────────────────────────────────────────────┐  │
│  │  RECENT PROJECTS                                    │  │
│  │  ┌─────────────────────────────────────────────────┐│  │
│  │  │ Title          │ Type   │ Status      │ Action ││  │
│  │  │ Health Center  │ Root   │ In Progress │ [View] ││  │
│  │  │ SEF Supplies   │ SEF    │ Not Started │ [View] ││  │
│  │  └─────────────────────────────────────────────────┘│  │
│  └─────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌─────────────────────────────────────────────────────┐  │
│  │  UPCOMING DEADLINES (next 30 days)                  │  │
│  │  Feb 10 - Basketball Court Renovation               │  │
│  │  Feb 20 - Tree Planting Phase 1                     │  │
│  └─────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────┘
```

---

## Page: Project List (`pages/projects/list.php`)

```
┌──────────────────────────────────────────────────────────┐
│  All Projects                              [+ New Project]│
├──────────────────────────────────────────────────────────┤
│                                                          │
│  FILTERS ROW:                                            │
│  [Type ▼] [Status ▼] [Category ▼] [Year ▼] [Priority ▼] │
│  [🔍 Search keyword...                        ] [Filter] │
│                                                          │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ # │ Title           │ Type    │ Status      │ Budget │ │
│  │   │                 │         │             │        │ │
│  │ 1 │ Road Repair     │ Root    │ ●In Progress│ ₱500K  │ │
│  │ 2 │ School Supplies │ SEF     │ ●Completed  │ ₱120K  │ │
│  │ 3 │ Flood Wall      │ Special │ ●Not Started│ ₱800K  │ │
│  │ 4 │ Health Center   │ Root    │ ●On Hold    │ ₱1.2M  │ │
│  │   │                 │         │             │        │ │
│  │   │    [< Prev]  Page 1 of 3  [Next >]             │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                          │
│  Showing 1-10 of 24 projects   [Export PDF] [Export XLSX] │
└──────────────────────────────────────────────────────────┘
```

**Table columns:** #, Title, Type (badge), Category, Priority (badge), Status (colored dot + label), Budget, Start Date, Actions (View / Edit / Delete).

**Status badges use colors:**
- Not Started: `badge bg-secondary`
- In Progress: `badge bg-info`
- On Hold: `badge bg-warning`
- Completed: `badge bg-success`
- Cancelled: `badge bg-danger`

**Pagination:** 10 projects per page, standard Bootstrap pagination.

---

## Page: Create/Edit Project (`pages/projects/create.php` / `edit.php`)

```
┌──────────────────────────────────────────────────────────┐
│  New Project  (or "Edit Project: Road Repair Phase 2")   │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  ┌─ PROJECT INFORMATION ─────────────────────────────┐   │
│  │                                                    │   │
│  │  Project Title*:    [________________________]     │   │
│  │  Project Type*:     [Root Project        ▼]        │   │
│  │  Category:          [Infrastructure      ▼]        │   │
│  │  Priority:          [Medium              ▼]        │   │
│  │  Status:            [Not Started         ▼]        │   │
│  │                                                    │   │
│  │  Description:                                      │   │
│  │  ┌──────────────────────────────────────────┐      │   │
│  │  │                                          │      │   │
│  │  │  (textarea)                              │      │   │
│  │  │                                          │      │   │
│  │  └──────────────────────────────────────────┘      │   │
│  └────────────────────────────────────────────────────┘   │
│                                                          │
│  ┌─ DATES & BUDGET ──────────────────────────────────┐   │
│  │                                                    │   │
│  │  Start Date:        [____-__-__]                   │   │
│  │  Target End Date:   [____-__-__]                   │   │
│  │  Fiscal Year*:      [2026]                         │   │
│  │  Budget Allocated:  [₱ __________]                 │   │
│  │                                                    │   │
│  └────────────────────────────────────────────────────┘   │
│                                                          │
│  ┌─ ADDITIONAL INFO ─────────────────────────────────┐   │
│  │                                                    │   │
│  │  Funding Source:    [________________________]     │   │
│  │  Resolution No.:   [________________________]     │   │
│  │  Location:         [________________________]     │   │
│  │  Contact Person:   [________________________]     │   │
│  │                                                    │   │
│  │  Remarks:                                          │   │
│  │  ┌──────────────────────────────────────────┐      │   │
│  │  │  (textarea)                              │      │   │
│  │  └──────────────────────────────────────────┘      │   │
│  └────────────────────────────────────────────────────┘   │
│                                                          │
│                            [Cancel]  [Save Project]      │
└──────────────────────────────────────────────────────────┘
```

---

## Page: Project Detail (`pages/projects/view.php`)

Uses a tabbed interface to organize information:

```
┌──────────────────────────────────────────────────────────┐
│  ← Back to Projects                                      │
│                                                          │
│  Road Repair Phase 2                                     │
│  [Root Project]  [●In Progress]  [Medium Priority]       │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  [Overview] [Budget] [Milestones] [Action Log] [Files]   │
│  ─────────────────────────────────────────────────────── │
│                                                          │
│  TAB: OVERVIEW                                           │
│  ┌────────────────────────┬──────────────────────────┐   │
│  │ Category:              │ Infrastructure           │   │
│  │ Funding Source:        │ IRA                      │   │
│  │ Resolution No.:        │ RES-2026-015             │   │
│  │ Location:              │ Purok 3, Sitio Malaya    │   │
│  │ Contact Person:        │ Juan Dela Cruz           │   │
│  │ Start Date:            │ Jan 10, 2026             │   │
│  │ Target End Date:       │ Mar 30, 2026             │   │
│  │ Budget Allocated:      │ ₱500,000.00              │   │
│  │ Actual Cost:           │ ₱320,000.00              │   │
│  │ Utilization:           │ ████████░░ 64%           │   │
│  │ Fiscal Year:           │ 2026                     │   │
│  ├────────────────────────┴──────────────────────────┤   │
│  │ Description:                                      │   │
│  │ Repair of main barangay road from Purok 1 to      │   │
│  │ Purok 5 including drainage improvements...        │   │
│  ├───────────────────────────────────────────────────┤   │
│  │ Remarks:                                          │   │
│  │ Awaiting additional materials delivery.           │   │
│  └───────────────────────────────────────────────────┘   │
│                                                          │
│  [Edit Project]  [Print Report]  [Archive]  [Delete]     │
└──────────────────────────────────────────────────────────┘
```

### Tab: Budget

```
│  TAB: BUDGET                                             │
│  ┌───────────────────────────────────────────────────┐   │
│  │ Budget Breakdown                    [+ Add Item]  │   │
│  │ ┌────┬──────────┬──────┬────┬───────┬───────┬──┐  │   │
│  │ │ #  │ Item     │ Cat  │Qty │ Unit  │ Cost  │  │  │   │
│  │ ├────┼──────────┼──────┼────┼───────┼───────┼──┤  │   │
│  │ │ 1  │ Cement   │ MOOE │ 50 │ bags  │₱15K   │🗑│  │   │
│  │ │ 2  │ Gravel   │ MOOE │ 10 │ cu.m  │₱25K   │🗑│  │   │
│  │ │ 3  │ Labor    │ Pers │ 30 │ days  │₱45K   │🗑│  │   │
│  │ ├────┴──────────┴──────┴────┴───────┼───────┼──┤  │   │
│  │ │                         TOTAL:    │₱85K   │  │  │   │
│  │ └──────────────────────────────────────────────┘  │   │
│  │                                                    │   │
│  │ Allocated: ₱500,000  │ Estimated: ₱85,000         │   │
│  │ Variance: ₱415,000 (under budget)                 │   │
│  └───────────────────────────────────────────────────┘   │
```

### Tab: Milestones

```
│  TAB: MILESTONES                                         │
│  ┌───────────────────────────────────────────────────┐   │
│  │ Progress: ██████░░░░ 2/5 milestones (40%)         │   │
│  │                                    [+ Add Milestone]│  │
│  │ ┌─────────────────────────────────────────────────┐│  │
│  │ │ ✅ Site Clearing        │ Target: Jan 15 │ Done ││  │
│  │ │ ✅ Foundation Work      │ Target: Jan 30 │ Done ││  │
│  │ │ ⬜ Road Base Layer      │ Target: Feb 15 │ Pend ││  │
│  │ │ ⬜ Paving               │ Target: Mar 01 │ Pend ││  │
│  │ │ ⬜ Final Inspection     │ Target: Mar 30 │ Pend ││  │
│  │ └─────────────────────────────────────────────────┘│  │
│  └───────────────────────────────────────────────────┘   │
```

### Tab: Action Log

```
│  TAB: ACTION LOG                                         │
│  ┌───────────────────────────────────────────────────┐   │
│  │                                    [+ Add Entry]  │   │
│  │                                                    │   │
│  │  Feb 3, 2026 - Juan Dela Cruz                     │   │
│  │  ┌───────────────────────────────────────────┐    │   │
│  │  │ Action: Completed foundation pouring       │    │   │
│  │  │ Notes: Weather delayed work by 2 days.     │    │   │
│  │  │ Used 20 bags of cement for this phase.     │    │   │
│  │  └───────────────────────────────────────────┘    │   │
│  │                                                    │   │
│  │  Jan 20, 2026 - Juan Dela Cruz                    │   │
│  │  ┌───────────────────────────────────────────┐    │   │
│  │  │ Action: Started excavation work            │    │   │
│  │  │ Notes: Mobilized 10 workers on site.       │    │   │
│  │  └───────────────────────────────────────────┘    │   │
│  └───────────────────────────────────────────────────┘   │
```

### Tab: Attachments

```
│  TAB: FILES                                              │
│  ┌───────────────────────────────────────────────────┐   │
│  │                                    [+ Upload File]│   │
│  │                                                    │   │
│  │  ┌────────┐  ┌────────┐  ┌────────┐               │   │
│  │  │ 📷     │  │ 📷     │  │ 📄     │               │   │
│  │  │ IMG    │  │ IMG    │  │ PDF    │               │   │
│  │  │site.jpg│  │work.png│  │plan.pdf│               │   │
│  │  │ 1.2MB  │  │ 800KB  │  │ 2.5MB  │               │   │
│  │  │ [🗑]   │  │ [🗑]   │  │ [🗑]   │               │   │
│  │  └────────┘  └────────┘  └────────┘               │   │
│  │                                                    │   │
│  │  Total: 3 files (4.5 MB)                          │   │
│  └───────────────────────────────────────────────────┘   │
```

---

## Page: Reports (`pages/reports/index.php`)

```
┌──────────────────────────────────────────────────────────┐
│  Generate Reports                                        │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  ┌─ INDIVIDUAL PROJECT REPORT ───────────────────────┐   │
│  │                                                    │   │
│  │  Select Project: [Road Repair Phase 2         ▼]   │   │
│  │  Format:         ◉ PDF  ○ DOCX                     │   │
│  │                                                    │   │
│  │  Include:                                          │   │
│  │  ☑ Project Details                                 │   │
│  │  ☑ Budget Breakdown                                │   │
│  │  ☑ Milestones                                      │   │
│  │  ☑ Action Log                                      │   │
│  │  ☑ Attachment List                                 │   │
│  │                                                    │   │
│  │                            [Generate Report]       │   │
│  └────────────────────────────────────────────────────┘   │
│                                                          │
│  ┌─ SUMMARY REPORTS ─────────────────────────────────┐   │
│  │                                                    │   │
│  │  Fiscal Year: [2026 ▼]                             │   │
│  │                                                    │   │
│  │  [All Projects Summary - PDF]                      │   │
│  │  [Budget Utilization Report - PDF]                 │   │
│  │  [Project Status Report - PDF]                     │   │
│  │  [Overdue Projects Report - PDF]                   │   │
│  └────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────┘
```

---

## Page: Settings (`pages/settings/index.php`)

```
┌──────────────────────────────────────────────────────────┐
│  Settings                                                │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  ┌─ BARANGAY INFORMATION ────────────────────────────┐   │
│  │                                                    │   │
│  │  Barangay Name:     [________________________]     │   │
│  │  Municipality/City: [________________________]     │   │
│  │  Province:          [________________________]     │   │
│  │                                                    │   │
│  └────────────────────────────────────────────────────┘   │
│                                                          │
│  ┌─ REPORT SETTINGS ─────────────────────────────────┐   │
│  │                                                    │   │
│  │  Prepared By:       [________________________]     │   │
│  │  Designation:       [________________________]     │   │
│  │  Fiscal Year:       [2026]                         │   │
│  │                                                    │   │
│  └────────────────────────────────────────────────────┘   │
│                                                          │
│                                        [Save Settings]   │
└──────────────────────────────────────────────────────────┘
```

---

## Page: Backup & Restore (`pages/backup/index.php`)

```
┌──────────────────────────────────────────────────────────┐
│  Backup & Restore                                        │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  ┌─ BACKUP ──────────────────────────────────────────┐   │
│  │                                                    │   │
│  │  Last backup: Jan 28, 2026 (8 days ago)  ⚠        │   │
│  │                                                    │   │
│  │  [Export Database (SQL)]  [Export Files (ZIP)]      │   │
│  │                                                    │   │
│  └────────────────────────────────────────────────────┘   │
│                                                          │
│  ┌─ RESTORE ─────────────────────────────────────────┐   │
│  │                                                    │   │
│  │  ⚠ Warning: Restoring will overwrite all current  │   │
│  │  data. Make sure to backup first.                  │   │
│  │                                                    │   │
│  │  Upload SQL file: [Choose File]  [Restore]         │   │
│  │                                                    │   │
│  └────────────────────────────────────────────────────┘   │
│                                                          │
│  ┌─ BACKUP HISTORY ──────────────────────────────────┐   │
│  │                                                    │   │
│  │  bdpt_backup_2026-01-28.sql    (245 KB)  [⬇]      │   │
│  │  bdpt_backup_2026-01-15.sql    (230 KB)  [⬇]      │   │
│  │  bdpt_backup_2026-01-01.sql    (210 KB)  [⬇]      │   │
│  │                                                    │   │
│  └────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────┘
```

---

## Modal: Add Budget Item

```
┌─────────────────────────────────────┐
│  Add Budget Item              [X]   │
├─────────────────────────────────────┤
│                                     │
│  Item Name*:  [________________]    │
│  Category:    [MOOE           ▼]    │
│  Quantity:    [____]                 │
│  Unit:        [pcs            ▼]    │
│  Unit Cost:   [₱ _________]         │
│                                     │
│  Total: ₱0.00 (auto-computed)       │
│                                     │
│              [Cancel] [Add Item]    │
└─────────────────────────────────────┘
```

## Modal: Add Action Log Entry

```
┌─────────────────────────────────────┐
│  Log Progress Update          [X]   │
├─────────────────────────────────────┤
│                                     │
│  Action Taken*:                     │
│  [_______________________________]  │
│                                     │
│  Notes:                             │
│  ┌───────────────────────────────┐  │
│  │                               │  │
│  └───────────────────────────────┘  │
│                                     │
│  Logged By:  [________________]     │
│  Date:       [____-__-__]           │
│                                     │
│              [Cancel] [Save Entry]  │
└─────────────────────────────────────┘
```

## Modal: Confirm Delete

```
┌─────────────────────────────────────┐
│  Confirm Delete               [X]   │
├─────────────────────────────────────┤
│                                     │
│  Are you sure you want to delete    │
│  "Road Repair Phase 2"?            │
│                                     │
│  This action can be undone from     │
│  the archive.                       │
│                                     │
│          [Cancel] [Delete]          │
└─────────────────────────────────────┘
```
