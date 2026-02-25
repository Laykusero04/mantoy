<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/settings/categories.php');
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // ── Add Category ────────────────────────────────────────
    case 'add':
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flashMessage('danger', 'Category name is required.');
            redirect(BASE_URL . '/pages/settings/categories.php');
        }

        // Check duplicate
        $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
        $check->execute([$name]);
        if ($check->fetchColumn() > 0) {
            flashMessage('warning', 'Category already exists.');
            redirect(BASE_URL . '/pages/settings/categories.php');
        }

        $stmt = $pdo->prepare("INSERT INTO categories (name, is_default) VALUES (?, 0)");
        $stmt->execute([$name]);
        flashMessage('success', 'Category added.');
        redirect(BASE_URL . '/pages/settings/categories.php');
        break;

    // ── Update Category ─────────────────────────────────────
    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($id <= 0 || $name === '') {
            flashMessage('danger', 'Invalid input.');
            redirect(BASE_URL . '/pages/settings/categories.php');
        }

        // Check duplicate (excluding self)
        $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND id != ?");
        $check->execute([$name, $id]);
        if ($check->fetchColumn() > 0) {
            flashMessage('warning', 'Category name already exists.');
            redirect(BASE_URL . '/pages/settings/categories.php');
        }

        $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        flashMessage('success', 'Category updated.');
        redirect(BASE_URL . '/pages/settings/categories.php');
        break;

    // ── Delete Category ─────────────────────────────────────
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flashMessage('danger', 'Invalid category.');
            redirect(BASE_URL . '/pages/settings/categories.php');
        }

        // Prevent deleting default categories
        $check = $pdo->prepare("SELECT is_default FROM categories WHERE id = ?");
        $check->execute([$id]);
        $cat = $check->fetch();

        if (!$cat) {
            flashMessage('danger', 'Category not found.');
        } elseif ($cat['is_default']) {
            flashMessage('warning', 'Default categories cannot be deleted.');
        } else {
            // Sets category_id to NULL for related projects (via ON DELETE SET NULL)
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND is_default = 0");
            $stmt->execute([$id]);
            flashMessage('success', 'Category deleted.');
        }
        redirect(BASE_URL . '/pages/settings/categories.php');
        break;

    default:
        redirect(BASE_URL . '/pages/settings/categories.php');
}
