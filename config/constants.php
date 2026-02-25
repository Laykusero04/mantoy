<?php
// Base URL
define('BASE_URL', 'http://localhost/mantoy');

// Directory paths
define('ROOT_DIR', dirname(__DIR__));
define('UPLOAD_DIR', ROOT_DIR . '/uploads/');
define('BACKUP_DIR', ROOT_DIR . '/backups/');

// File upload limits
define('MAX_FILE_SIZE', 52428800); // 50MB in bytes (supports video uploads)
define('MAX_IMAGE_SIZE', 10485760); // 10MB for images
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'dwg', 'dxf', 'mp4', 'avi', 'mov', 'skp', 'zip', 'rar']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_VIDEO_EXTENSIONS', ['mp4', 'avi', 'mov']);

// Pagination
define('ITEMS_PER_PAGE', 10);

// App info
define('APP_NAME', 'Barangay Dev Project Tracker');
define('APP_VERSION', '1.0');
