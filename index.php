<?php
require_once __DIR__ . '/config/constants.php';
// Redirect to Private Dashboard (default dashboard view)
$year = isset($_GET['year']) ? '?year=' . (int)$_GET['year'] : '';
header('Location: ' . BASE_URL . '/pages/dashboard/private.php' . $year);
exit;
