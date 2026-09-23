<?php
declare(strict_types=1);
session_start();
require_once dirname(__DIR__) . '/api/config.php';

if (empty($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

$db = db();
$action = (string) ($_POST['action'] ?? '');

if ($action === 'reorder') {
    $orderIds = $_POST['order_ids'] ?? [];
    if (is_array($orderIds)) {
        $statement = $db->prepare('UPDATE products SET sort_order = ? WHERE id = ?');
        foreach (array_values($orderIds) as $position => $productId) {
            $statement->execute([$position, (string) $productId]);
        }
    }
    http_response_code(204);
    exit;
}

$id = trim((string) ($_POST['id'] ?? ''));
if ($id === '') {
    header('Location: products.php');
    exit;
}

function uploadedImagePath(): ?string
{
    if (!isset($_FILES['image_upload']) || $_FILES['image_upload']['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES['image_upload']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('The image upload failed.');
    $temporaryPath = (string) $_FILES['image_upload']['tmp_name'];
    $mime = mime_content_type($temporaryPath);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Choose a JPG, PNG, GIF, or WEBP image.');
    $directory = dirname(__DIR__) . '/img/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('The upload directory could not be created.');
    $filename = 'product-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporaryPath, $directory . '/' . $filename)) throw new RuntimeException('The image could not be saved.');
    return 'img/uploads/' . $filename;
}

$uploadedImage = uploadedImagePath();
$selectedImage = trim((string) ($_POST['gallery_image'] ?? ''));
$image = $uploadedImage ?? ($selectedImage !== '' ? $selectedImage : null);

if ($action === 'create') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $oldPrice = filter_var($_POST['old_price'] ?? null, FILTER_VALIDATE_FLOAT);
    $storefront = trim((string) ($_POST['storefront'] ?? 'elys-beauty'));
    $category = trim((string) ($_POST['category'] ?? ''));
    $tag = trim((string) ($_POST['tag'] ?? ''));
    $stockQuantity = max(0, (int) ($_POST['stock_quantity'] ?? 10));
    $lowStockThreshold = max(0, (int) ($_POST['low_stock_threshold'] ?? 3));
    $description = trim((string) ($_POST['short_description'] ?? ''));
    $fullDescription = trim((string) ($_POST['description'] ?? $description));
    if ($name !== '' && $price !== false && $price >= 0 && $image !== null) {
        $statement = $db->prepare('INSERT INTO products (id, storefront, name, short_description, description, benefits, ingredients, price, old_price, category, stock_quantity, low_stock_threshold, image, tag, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$id, $storefront, $name, $description, $fullDescription, '[]', '', $price, $oldPrice === false ? null : $oldPrice, $category, $stockQuantity, $lowStockThreshold, $image, $tag ?: null, isset($_POST['is_active']) ? 1 : 0, 0]);
    }
} elseif ($action === 'delete') {
    $statement = $db->prepare('DELETE FROM products WHERE id = ?');
    $statement->execute([$id]);
} elseif ($action === 'update') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $oldPrice = filter_var($_POST['old_price'] ?? null, FILTER_VALIDATE_FLOAT);
    $storefront = trim((string) ($_POST['storefront'] ?? 'elys-beauty'));
    $category = trim((string) ($_POST['category'] ?? ''));
    $tag = trim((string) ($_POST['tag'] ?? ''));
    $description = trim((string) ($_POST['short_description'] ?? ''));
    $fullDescription = trim((string) ($_POST['description'] ?? $description));
    if ($name !== '' && $price !== false && $price >= 0) {
        $fields = 'storefront = ?, name = ?, price = ?, old_price = ?, category = ?, tag = ?, short_description = ?, description = ?, is_active = ?';
        $values = [$storefront, $name, $price, $oldPrice === false ? null : $oldPrice, $category, $tag ?: null, $description, $fullDescription, isset($_POST['is_active']) ? 1 : 0];
        if ($image !== null) { $fields .= ', image = ?'; $values[] = $image; }
        $values[] = $id;
        $statement = $db->prepare("UPDATE products SET {$fields} WHERE id = ?");
        $statement->execute($values);
    }
}
header('Location: products.php');
exit;
