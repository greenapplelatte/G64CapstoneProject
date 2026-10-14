<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$userId = $_SESSION['user_id'];
$dbPath = __DIR__ . '/../../database/sdpbus.db';

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle Profile Picture Upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file format. Only JPG, PNG, and WEBP allowed.']);
            exit;
        }

        if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
            echo json_encode(['success' => false, 'message' => 'File size exceeds maximum limit of 2MB.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../../uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_user_' . $userId . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $filename;
        $dbRelativePath = '/ojt-sdpbus/uploads/avatars/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $stmt = $pdo->prepare("UPDATE users SET profile_image = :image WHERE id = :id");
            $stmt->execute([':image' => $dbRelativePath, ':id' => $userId]);

            $_SESSION['profile_image'] = $dbRelativePath;

            echo json_encode([
                'success' => true,
                'message' => 'Profile picture updated successfully.',
                'image_url' => $dbRelativePath
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'No image file uploaded.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}