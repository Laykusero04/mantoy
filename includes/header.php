<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$settings = getSettings($pdo);
$currentFiscalYear = $settings['fiscal_year'] ?? date('Y');
$currentPath = getCurrentPath();
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? 'Dashboard') ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/fonts/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm">
        <div class="container-fluid">
            <button class="btn btn-link text-white me-2 sidebar-toggle d-lg-none" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
            </button>
            <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/">
                <i class="bi bi-building me-2"></i>
                <span class="fw-bold"><?= APP_NAME ?></span>
            </a>
            <div class="d-flex align-items-center ms-auto">
                <a href="<?= BASE_URL ?>/pages/backup/index.php" class="btn btn-outline-light btn-sm me-2" title="Backup & Restore">
                    <i class="bi bi-cloud-download"></i>
                    <span class="d-none d-md-inline ms-1">Backup</span>
                </a>
                <a href="<?= BASE_URL ?>/pages/settings/index.php" class="btn btn-outline-light btn-sm" title="Settings">
                    <i class="bi bi-gear"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="d-flex" id="wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content -->
        <div id="main-content" class="flex-grow-1">
            <div class="container-fluid p-4">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show" role="alert">
                        <?= sanitize($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
