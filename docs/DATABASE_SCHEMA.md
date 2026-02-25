# Database Schema

## Database Name: `bdpt_db`

---

## Table: `settings`

Stores barangay information and application settings.

```sql
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Default rows:**

| setting_key        | setting_value          |
|--------------------|------------------------|
| barangay_name      | ''                     |
| municipality       | ''                     |
| province           | ''                     |
| prepared_by        | ''                     |
| designation        | ''                     |
| fiscal_year        | 2026                   |
| currency_symbol    | PHP                    |

---

## Table: `categories`

Predefined and custom project categories.

```sql
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Default rows:**

| name                          | is_default |
|-------------------------------|------------|
| Infrastructure                | 1          |
| Health & Sanitation           | 1          |
| Education                     | 1          |
| Livelihood & Agriculture      | 1          |
| Peace & Order                 | 1          |
| Disaster Preparedness         | 1          |
| Environmental Protection      | 1          |
| Social Welfare                | 1          |
| Sports & Youth Development    | 1          |
| Other                         | 1          |

---

## Table: `projects`

Main projects table.

```sql
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    project_type ENUM('Root Project', 'SEF Project', 'Special Project') NOT NULL,
    category_id INT,
    description TEXT,
    priority ENUM('High', 'Medium', 'Low') DEFAULT 'Medium',
    status ENUM('Not Started', 'In Progress', 'On Hold', 'Completed', 'Cancelled') DEFAULT 'Not Started',
    funding_source VARCHAR(150),
    resolution_no VARCHAR(50),
    budget_allocated DECIMAL(15, 2) DEFAULT 0.00,
    actual_cost DECIMAL(15, 2) DEFAULT 0.00,
    start_date DATE,
    target_end_date DATE,
    actual_end_date DATE NULL,
    fiscal_year YEAR NOT NULL,
    location VARCHAR(255),
    contact_person VARCHAR(150),
    remarks TEXT,
    is_archived TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);
```

**Indexes:**
```sql
CREATE INDEX idx_projects_type ON projects(project_type);
CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_projects_fiscal_year ON projects(fiscal_year);
CREATE INDEX idx_projects_is_deleted ON projects(is_deleted);
CREATE INDEX idx_projects_is_archived ON projects(is_archived);
```

---

## Table: `budget_items`

Itemized budget breakdown per project.

```sql
CREATE TABLE budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    budget_category ENUM('MOOE', 'Capital Outlay', 'Personnel Services') DEFAULT 'MOOE',
    quantity DECIMAL(10, 2) DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'pcs',
    unit_cost DECIMAL(15, 2) DEFAULT 0.00,
    total_cost DECIMAL(15, 2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

**Index:**
```sql
CREATE INDEX idx_budget_items_project ON budget_items(project_id);
```

---

## Table: `milestones`

Project milestones for tracking progress phases.

```sql
CREATE TABLE milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    target_date DATE,
    completed_date DATE NULL,
    status ENUM('Pending', 'Done') DEFAULT 'Pending',
    notes TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

**Index:**
```sql
CREATE INDEX idx_milestones_project ON milestones(project_id);
```

---

## Table: `action_logs`

Progress entries and activity log per project.

```sql
CREATE TABLE action_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    action_taken VARCHAR(500) NOT NULL,
    notes TEXT,
    logged_by VARCHAR(150),
    log_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

**Index:**
```sql
CREATE INDEX idx_action_logs_project ON action_logs(project_id);
CREATE INDEX idx_action_logs_date ON action_logs(log_date);
```

---

## Table: `attachments`

File attachments linked to projects.

```sql
CREATE TABLE attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL,
    description VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

**Index:**
```sql
CREATE INDEX idx_attachments_project ON attachments(project_id);
```

---

## Entity Relationship Summary

```
settings          (standalone key-value config)

categories ──────< projects
                      │
                      ├────< budget_items
                      ├────< milestones
                      ├────< action_logs
                      └────< attachments
```

- One **category** can have many **projects**
- One **project** can have many **budget_items**, **milestones**, **action_logs**, and **attachments**
- Deleting a project cascades to all child records
- Deleting a category sets `category_id` to NULL on related projects

---

## Full Setup SQL

```sql
-- Create Database
CREATE DATABASE IF NOT EXISTS bdpt_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bdpt_db;

-- Settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value) VALUES
('barangay_name', ''),
('municipality', ''),
('province', ''),
('prepared_by', ''),
('designation', ''),
('fiscal_year', '2026'),
('currency_symbol', 'PHP');

-- Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name, is_default) VALUES
('Infrastructure', 1),
('Health & Sanitation', 1),
('Education', 1),
('Livelihood & Agriculture', 1),
('Peace & Order', 1),
('Disaster Preparedness', 1),
('Environmental Protection', 1),
('Social Welfare', 1),
('Sports & Youth Development', 1),
('Other', 1);

-- Projects
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    project_type ENUM('Root Project', 'SEF Project', 'Special Project') NOT NULL,
    category_id INT,
    description TEXT,
    priority ENUM('High', 'Medium', 'Low') DEFAULT 'Medium',
    status ENUM('Not Started', 'In Progress', 'On Hold', 'Completed', 'Cancelled') DEFAULT 'Not Started',
    funding_source VARCHAR(150),
    resolution_no VARCHAR(50),
    budget_allocated DECIMAL(15, 2) DEFAULT 0.00,
    actual_cost DECIMAL(15, 2) DEFAULT 0.00,
    start_date DATE,
    target_end_date DATE,
    actual_end_date DATE NULL,
    fiscal_year YEAR NOT NULL,
    location VARCHAR(255),
    contact_person VARCHAR(150),
    remarks TEXT,
    is_archived TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE INDEX idx_projects_type ON projects(project_type);
CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_projects_fiscal_year ON projects(fiscal_year);
CREATE INDEX idx_projects_is_deleted ON projects(is_deleted);
CREATE INDEX idx_projects_is_archived ON projects(is_archived);

-- Budget Items
CREATE TABLE budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    budget_category ENUM('MOOE', 'Capital Outlay', 'Personnel Services') DEFAULT 'MOOE',
    quantity DECIMAL(10, 2) DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'pcs',
    unit_cost DECIMAL(15, 2) DEFAULT 0.00,
    total_cost DECIMAL(15, 2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE INDEX idx_budget_items_project ON budget_items(project_id);

-- Milestones
CREATE TABLE milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    target_date DATE,
    completed_date DATE NULL,
    status ENUM('Pending', 'Done') DEFAULT 'Pending',
    notes TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE INDEX idx_milestones_project ON milestones(project_id);

-- Action Logs
CREATE TABLE action_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    action_taken VARCHAR(500) NOT NULL,
    notes TEXT,
    logged_by VARCHAR(150),
    log_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE INDEX idx_action_logs_project ON action_logs(project_id);
CREATE INDEX idx_action_logs_date ON action_logs(log_date);

-- Attachments
CREATE TABLE attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL,
    description VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE INDEX idx_attachments_project ON attachments(project_id);
```
