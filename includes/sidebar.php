<?php
$currentPath = getCurrentPath();
$isProjectsSection = strpos($currentPath, '/pages/projects/') !== false;
$isSettingsSection = strpos($currentPath, '/pages/settings/') !== false;
$isDashboardPrivate = strpos($currentPath, '/pages/dashboard/private') !== false;
$isDashboardPublic = strpos($currentPath, '/pages/dashboard/public') !== false;
?>
<!-- Sidebar -->
<nav id="sidebar" class="bg-light border-end shadow-sm">
    <div class="sidebar-content pt-3">
        <ul class="nav flex-column">
            <!-- Private Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?= $isDashboardPrivate ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/dashboard/private.php">
                    <i class="bi bi-speedometer2 me-2"></i> Private Dashboard
                </a>
            </li>
            <!-- Public Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?= $isDashboardPublic ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/dashboard/public.php">
                    <i class="bi bi-globe me-2"></i> Public Dashboard
                </a>
            </li>

            <!-- Calendar -->
            <li class="nav-item">
                <a class="nav-link <?= strpos($currentPath, '/pages/calendar/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/calendar/index.php">
                    <i class="bi bi-calendar-event me-2"></i> Calendar
                </a>
            </li>

            <!-- Projects Section -->
            <li class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center <?= $isProjectsSection ? '' : 'collapsed' ?>"
                   data-bs-toggle="collapse" href="#projectsSubmenu" role="button"
                   aria-expanded="<?= $isProjectsSection ? 'true' : 'false' ?>">
                    <span><i class="bi bi-folder me-2"></i> Projects</span>
                    <i class="bi bi-chevron-down small"></i>
                </a>
                <div class="collapse <?= $isProjectsSection ? 'show' : '' ?>" id="projectsSubmenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($currentPath, 'list.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/projects/list.php">
                                <i class="bi bi-list-ul me-2"></i> All Projects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($currentPath, 'create.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/projects/create.php">
                                <i class="bi bi-plus-circle me-2"></i> Incoming Project
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($currentPath, 'archive.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/projects/archive.php">
                                <i class="bi bi-archive me-2"></i> Archived
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link <?= strpos($currentPath, '/pages/reports/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/reports/index.php">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i> Reports
                </a>
            </li>

            <!-- Settings Section -->
            <li class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center <?= $isSettingsSection ? '' : 'collapsed' ?>"
                   data-bs-toggle="collapse" href="#settingsSubmenu" role="button"
                   aria-expanded="<?= $isSettingsSection ? 'true' : 'false' ?>">
                    <span><i class="bi bi-gear me-2"></i> Settings</span>
                    <i class="bi bi-chevron-down small"></i>
                </a>
                <div class="collapse <?= $isSettingsSection ? 'show' : '' ?>" id="settingsSubmenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($currentPath, 'settings/index.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/settings/index.php">
                                <i class="bi bi-info-circle me-2"></i> Barangay Info
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($currentPath, 'categories.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/settings/categories.php">
                                <i class="bi bi-tags me-2"></i> Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($currentPath, 'holidays.php') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/settings/holidays.php">
                                <i class="bi bi-calendar-x me-2"></i> Holidays (No-Work)
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Backup & Restore -->
            <li class="nav-item">
                <a class="nav-link <?= strpos($currentPath, '/pages/backup/') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/backup/index.php">
                    <i class="bi bi-cloud-download me-2"></i> Backup & Restore
                </a>
            </li>
        </ul>
    </div>
</nav>
