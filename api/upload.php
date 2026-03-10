<?php
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$template_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['template'] ?? '');
if ($template_id === '') {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing or invalid template parameter']));
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'No file uploaded']));
}

// Check for upload errors with detailed messages
$upload_errors = [
    UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
    UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
    UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
];

if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error_msg = $upload_errors[$_FILES['file']['error']] ?? 'Unknown upload error';
    http_response_code(400);
    exit(json_encode(['error' => $error_msg]));
}

$file = $_FILES['file'];
$file_size = $file['size'];
$file_tmp = $file['tmp_name'];
$file_name = $file['name'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Validate file type
$allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowed_videos = ['mp4', 'webm', 'ogg', 'mov'];
$allowed_extensions = array_merge($allowed_images, $allowed_videos);

if (!in_array($file_ext, $allowed_extensions)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions)]));
}

// Validate file size (max 50MB)
$max_size = 50 * 1024 * 1024;
if ($file_size > $max_size) {
    http_response_code(400);
    exit(json_encode(['error' => 'File too large. Maximum size: 50MB']));
}

// Create upload directory if not exists
$upload_dir = __DIR__ . '/../uploads/userwork/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$unique_name = $template_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
$upload_path = $upload_dir . $unique_name;
$relative_path = 'uploads/userwork/' . $unique_name;

// Move uploaded file
if (!move_uploaded_file($file_tmp, $upload_path)) {
    http_response_code(500);
    exit(json_encode(['error' => 'Failed to save file']));
}

// Determine file type
$file_type = in_array($file_ext, $allowed_images) ? 'image' : 'video';

// Return success with file info
echo json_encode([
    'success' => true,
    'path' => $relative_path,
    'url' => '/' . $relative_path,  // URL for preview (relative to template folder)
    'type' => $file_type,
    'filename' => $unique_name
]);
