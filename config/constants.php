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
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'dwg', 'dxf', 'mp4', 'avi', 'mov', 'skp', 'rvt', 'mp3', 'pptx', 'ppt', 'zip', 'rar']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_VIDEO_EXTENSIONS', ['mp4', 'avi', 'mov']);
define('ALLOWED_AUDIO_EXTENSIONS', ['mp3']);
define('ALLOWED_DOCUMENT_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'pptx', 'ppt']);
define('ALLOWED_CAD_EXTENSIONS', ['dwg', 'dxf', 'skp', 'rvt']);

// Project status options
define('STATUS_OPTIONS', ['Not Started', 'In Progress', 'On Hold', 'Completed', 'Cancelled']);

// Daily construction log: weather options (shared by backend and calendar modal)
define('WEATHER_OPTIONS', ['Sunny', 'Cloudy', 'Partly Cloudy', 'Rain', 'Overcast', 'Clear', 'Storm', 'Fog', 'Windy', 'Other']);

// Pagination
define('ITEMS_PER_PAGE', 10);

// App info
define('APP_NAME', 'Project Resource & Information System for Management');
define('APP_VERSION', '1.0');
    