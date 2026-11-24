<?php
require 'config/database.php';
require 'config/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$slug = $_POST['slug'] ?? '';

if ($action === 'add_user') {
    $docId = $_POST['document_id'];
    $email = trim($_POST['email']);
    $permission = $_POST['permission'] === 'editor' ? 'editor' : 'viewer';

    // Pastikan yang melakukan share adalah pemilik dokumen
    $checkOwner = $pdo->prepare("SELECT id FROM documents WHERE id = ? AND owner_id = ?");
    $checkOwner->execute([$docId, $userId]);
    if (!$checkOwner->fetch()) die("Unauthorized");

    // Cari target user berdasarkan email
    $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmtUser->execute([$email]);
    $targetUser = $stmtUser->fetch();

    if ($targetUser && $targetUser['id'] != $userId) {
        $stmt = $pdo->prepare("
            INSERT INTO document_shares (document_id, user_id, permission) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE permission = VALUES(permission)
        ");
        $stmt->execute([$docId, $targetUser['id'], $permission]);
    }
}

if ($action === 'remove_user') {
    $shareId = $_POST['share_id'];
    $stmt = $pdo->prepare("
        DELETE ds FROM document_shares ds 
        JOIN documents d ON ds.document_id = d.id 
        WHERE ds.id = ? AND d.owner_id = ?
    ");
    $stmt->execute([$shareId, $userId]);
}

if ($action === 'public_link') {
    $docId = $_POST['document_id'];
    $publicAccess = in_array($_POST['public_access'], ['none', 'viewer', 'editor']) ? $_POST['public_access'] : 'none';
    $token = ($publicAccess === 'none') ? null : bin2hex(random_bytes(16));

    $stmt = $pdo->prepare("UPDATE documents SET public_access = ?, share_token = COALESCE(share_token, ?) WHERE id = ? AND owner_id = ?");
    $stmt->execute([$publicAccess, $token, $docId, $userId]);
}

header("Location: view.php?slug=" . urlencode($slug));
exit;