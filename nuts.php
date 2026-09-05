<?php
// ============================================================
// CONFIGURATION
// ============================================================
define('PASSWORD', 'admin123');
define('BASE_DIR', __DIR__ . '/files');

// Create base directory if not exists
if (!is_dir(BASE_DIR)) {
    mkdir(BASE_DIR, 0755, true);
}

// ============================================================
// AUTHENTICATION
// ============================================================
session_start();

$isAuthenticated = isset($_SESSION['fm_auth']) && $_SESSION['fm_auth'] === true;

// Handle login
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if ($_POST['password'] === PASSWORD) {
        $_SESSION['fm_auth'] = true;
        $isAuthenticated = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'Password salah!';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ============================================================
// FILE SYSTEM FUNCTIONS
// ============================================================
function getCurrentPath() {
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    $path = str_replace('..', '', $path);
    $path = ltrim($path, '/');
    return $path;
}

function getFullPath($relativePath = null) {
    if ($relativePath === null) {
        $relativePath = getCurrentPath();
    }
    return BASE_DIR . '/' . $relativePath;
}

function getDirectoryContent($path) {
    $items = [];
    $fullPath = getFullPath($path);
    
    if (!is_dir($fullPath)) {
        return $items;
    }
    
    $dir = opendir($fullPath);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $fullPath . '/' . $file;
        $relativePath = $path ? $path . '/' . $file : $file;
        
        $items[] = [
            'name' => $file,
            'path' => $relativePath,
            'is_dir' => is_dir($filePath),
            'size' => is_dir($filePath) ? null : filesize($filePath),
            'size_formatted' => is_dir($filePath) ? '' : formatSize(filesize($filePath)),
            'mtime' => filemtime($filePath),
            'mtime_formatted' => date('d M Y H:i', filemtime($filePath)),
            'ext' => is_dir($filePath) ? 'folder' : pathinfo($file, PATHINFO_EXTENSION),
        ];
    }
    closedir($dir);
    
    // Sort: folders first, then files
    usort($items, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $items;
}

function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1073741824, 1) . ' GB';
}

function getFileIcon($ext) {
    $icons = [
        'folder' => 'fa-folder',
        'png' => 'fa-file-image', 'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'gif' => 'fa-file-image', 'svg' => 'fa-file-image',
        'pdf' => 'fa-file-pdf',
        'zip' => 'fa-file-archive', 'rar' => 'fa-file-archive', '7z' => 'fa-file-archive',
        'html' => 'fa-file-code', 'css' => 'fa-file-code', 'js' => 'fa-file-code', 'php' => 'fa-file-code', 'json' => 'fa-file-code',
        'txt' => 'fa-file-alt', 'md' => 'fa-file-alt', 'log' => 'fa-file-alt',
        'mp4' => 'fa-file-video', 'avi' => 'fa-file-video', 'mkv' => 'fa-file-video',
        'mp3' => 'fa-file-audio', 'wav' => 'fa-file-audio', 'flac' => 'fa-file-audio',
        'exe' => 'fa-file', 'msi' => 'fa-file',
    ];
    return $icons[$ext] ?? 'fa-file';
}

function getFileColor($ext) {
    $colors = [
        'folder' => 'folder',
        'png' => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'gif' => 'image', 'svg' => 'image',
        'pdf' => 'pdf',
        'zip' => 'zip', 'rar' => 'zip', '7z' => 'zip',
        'html' => 'code', 'css' => 'code', 'js' => 'code', 'php' => 'code', 'json' => 'code',
        'txt' => 'text', 'md' => 'text', 'log' => 'text',
        'mp4' => 'video', 'avi' => 'video', 'mkv' => 'video',
        'mp3' => 'audio', 'wav' => 'audio', 'flac' => 'audio',
    ];
    return $colors[$ext] ?? '';
}

// ============================================================
// HANDLE ACTIONS
// ============================================================
$actionResult = null;

if ($isAuthenticated) {
    $currentPath = getCurrentPath();
    $fullPath = getFullPath($currentPath);
    
    // Create folder
    if (isset($_POST['action']) && $_POST['action'] === 'create_folder') {
        $name = trim($_POST['name']);
        if ($name && !strpos($name, '/') && !strpos($name, '\\')) {
            $newPath = $fullPath . '/' . $name;
            if (!file_exists($newPath)) {
                mkdir($newPath, 0755);
                $actionResult = ['success' => true, 'message' => 'Folder "' . $name . '" berhasil dibuat'];
            } else {
                $actionResult = ['success' => false, 'message' => 'Folder "' . $name . '" sudah ada'];
            }
        } else {
            $actionResult = ['success' => false, 'message' => 'Nama folder tidak valid'];
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?path=' . urlencode($currentPath) . '&result=' . urlencode(json_encode($actionResult)));
        exit;
    }
    
    // Upload file
    if (isset($_POST['action']) && $_POST['action'] === 'upload') {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $name = $_FILES['file']['name'];
            $tmpName = $_FILES['file']['tmp_name'];
            $targetPath = $fullPath . '/' . $name;
            if (!file_exists($targetPath)) {
                move_uploaded_file($tmpName, $targetPath);
                $actionResult = ['success' => true, 'message' => 'File "' . $name . '" berhasil diupload'];
            } else {
                $actionResult = ['success' => false, 'message' => 'File "' . $name . '" sudah ada'];
            }
        } else {
            $actionResult = ['success' => false, 'message' => 'Gagal upload file'];
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?path=' . urlencode($currentPath) . '&result=' . urlencode(json_encode($actionResult)));
        exit;
    }
    
    // Delete files/folders
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        $deleted = 0;
        foreach ($items as $item) {
            $itemPath = $fullPath . '/' . $item;
            if (file_exists($itemPath)) {
                if (is_dir($itemPath)) {
                    // Recursive delete
                    deleteRecursive($itemPath);
                } else {
                    unlink($itemPath);
                }
                $deleted++;
            }
        }
        $actionResult = ['success' => true, 'message' => $deleted . ' item berhasil dihapus'];
        header('Location: ' . $_SERVER['PHP_SELF'] . '?path=' . urlencode($currentPath) . '&result=' . urlencode(json_encode($actionResult)));
        exit;
    }
    
    // Download file
    if (isset($_GET['download'])) {
        $file = $_GET['download'];
        $filePath = $fullPath . '/' . $file;
        if (file_exists($filePath) && !is_dir($filePath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    }
}

function deleteRecursive($path) {
    if (!file_exists($path)) return;
    if (is_dir($path)) {
        $dir = opendir($path);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            deleteRecursive($path . '/' . $file);
        }
        closedir($dir);
        rmdir($path);
    } else {
        unlink($path);
    }
}

// Handle result messages
$resultMessage = null;
$resultSuccess = null;
if (isset($_GET['result'])) {
    $result = json_decode(urldecode($_GET['result']), true);
    if ($result) {
        $resultMessage = $result['message'];
        $resultSuccess = $result['success'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NUTS File Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            background: #080b12;
            background-image: radial-gradient(circle at 30% 20%, #0d1a2b, #020406 95%);
        }

        .file-card {
            max-width: 1000px;
            width: 100%;
            background: rgba(8, 13, 24, 0.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 3rem;
            padding: 2.5rem 2.8rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.9), 0 0 40px rgba(30, 144, 255, 0.15), 0 0 80px rgba(0, 80, 200, 0.08);
            border: 1px solid rgba(50, 150, 255, 0.12);
            position: relative;
            overflow: hidden;
            transition: box-shadow 0.5s ease;
        }

        .file-card:hover {
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.95), 0 0 60px rgba(30, 144, 255, 0.2), 0 0 100px rgba(0, 80, 200, 0.12);
        }

        .file-card::before {
            content: '';
            position: absolute;
            top: -15%;
            left: -10%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(30, 144, 255, 0.15) 0%, transparent 70%);
            filter: blur(70px);
            z-index: 0;
            animation: glowPulse 8s ease-in-out infinite alternate;
        }

        .file-card::after {
            content: '';
            position: absolute;
            bottom: -20%;
            right: -10%;
            width: 280px;
            height: 280px;
            background: radial-gradient(circle, rgba(0, 120, 255, 0.1) 0%, transparent 70%);
            filter: blur(80px);
            z-index: 0;
            animation: glowPulse 10s ease-in-out infinite alternate-reverse;
        }

        @keyframes glowPulse {
            0% {
                opacity: 0.6;
                transform: scale(1);
            }
            100% {
                opacity: 1;
                transform: scale(1.1);
            }
        }

        .file-card>* {
            position: relative;
            z-index: 2;
        }

        /* ===== PASSWORD OVERLAY ===== */
        .password-overlay {
            position: absolute;
            inset: 0;
            z-index: 10;
            background: rgba(8, 13, 24, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 3rem;
            display: <?php echo $isAuthenticated ? 'none' : 'flex'; ?>;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            transition: opacity 0.5s ease;
        }

        .password-overlay .lock-icon {
            font-size: 4rem;
            color: #4a8eff;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 0 30px #1e90ff);
            animation: iconFloat 4s ease-in-out infinite;
        }

        @keyframes iconFloat {
            0%,
            100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-6px);
            }
        }

        .password-overlay h2 {
            color: #d6e6ff;
            font-weight: 600;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .password-overlay p {
            color: #8aafdf;
            font-size: 0.95rem;
            margin-bottom: 1.8rem;
            text-align: center;
        }

        .password-input-group {
            display: flex;
            gap: 0.8rem;
            width: 100%;
            max-width: 380px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .password-input-group input {
            flex: 1;
            min-width: 200px;
            padding: 0.8rem 1.2rem;
            border-radius: 60px;
            border: 1px solid rgba(30, 144, 255, 0.2);
            background: rgba(0, 18, 40, 0.4);
            color: #d6e6ff;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(2px);
        }

        .password-input-group input::placeholder {
            color: #5a7fa8;
        }

        .password-input-group input:focus {
            border-color: #1e90ff;
            box-shadow: 0 0 25px rgba(30, 144, 255, 0.15);
            background: rgba(0, 18, 40, 0.6);
        }

        .password-input-group .btn-unlock {
            padding: 0.8rem 2rem;
            border-radius: 60px;
            border: none;
            background: linear-gradient(135deg, #1a5bbf, #0b3d8a);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 25px rgba(30, 144, 255, 0.2);
            border: 1px solid rgba(30, 144, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-input-group .btn-unlock:hover {
            transform: scale(1.03);
            box-shadow: 0 0 40px rgba(30, 144, 255, 0.3);
            background: linear-gradient(135deg, #2a6ddb, #0f4a9e);
        }

        .password-error {
            color: #ff6b6b;
            font-size: 0.85rem;
            margin-top: 0.8rem;
            min-height: 1.5rem;
        }

        /* ===== HEADER ===== */
        .file-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-bottom: 1.8rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(30, 144, 255, 0.1);
        }

        .brand {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #b8d8ff, #4a8eff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 0 30px rgba(30, 144, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .brand i {
            color: #4a8eff;
            font-size: 1.8rem;
            filter: drop-shadow(0 0 15px #1e90ff);
        }

        .header-actions {
            display: flex;
            gap: 0.8rem;
            align-items: center;
        }

        .lock-status {
            background: rgba(30, 144, 255, 0.1);
            padding: 0.3rem 1rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #7bbaff;
            border: 1px solid rgba(30, 144, 255, 0.15);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-logout {
            background: rgba(255, 70, 70, 0.08);
            border: 1px solid rgba(255, 70, 70, 0.15);
            color: #ff8a8a;
            padding: 0.3rem 1.2rem;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(2px);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-logout:hover {
            background: rgba(255, 70, 70, 0.15);
            border-color: rgba(255, 70, 70, 0.3);
            transform: scale(1.02);
        }

        .file-content {
            display: <?php echo $isAuthenticated ? 'block' : 'none'; ?>;
        }

        /* ===== TOOLBAR ===== */
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            align-items: center;
            justify-content: space-between;
        }

        .toolbar-left {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }

        .toolbar-btn {
            background: rgba(0, 18, 40, 0.3);
            border: 1px solid rgba(30, 144, 255, 0.08);
            color: #b0ceff;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(2px);
            text-decoration: none;
            border: 1px solid rgba(30, 144, 255, 0.08);
        }

        .toolbar-btn:hover {
            background: rgba(30, 144, 255, 0.08);
            border-color: rgba(30, 144, 255, 0.2);
            transform: translateY(-1px);
        }

        .toolbar-btn.danger:hover {
            background: rgba(255, 70, 70, 0.1);
            border-color: rgba(255, 70, 70, 0.2);
            color: #ff8a8a;
        }

        .toolbar-btn i {
            color: #4a8eff;
        }

        .toolbar-btn.danger i {
            color: #ff6b6b;
        }

        .toolbar-right {
            display: flex;
            gap: 0.6rem;
            align-items: center;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: rgba(0, 18, 40, 0.3);
            border: 1px solid rgba(30, 144, 255, 0.08);
            border-radius: 40px;
            padding: 0.3rem 0.3rem 0.3rem 1rem;
            transition: all 0.3s ease;
        }

        .search-box:focus-within {
            border-color: rgba(30, 144, 255, 0.3);
            box-shadow: 0 0 20px rgba(30, 144, 255, 0.05);
        }

        .search-box input {
            background: transparent;
            border: none;
            color: #d6e6ff;
            font-size: 0.85rem;
            outline: none;
            padding: 0.3rem 0;
            width: 140px;
        }

        .search-box input::placeholder {
            color: #5a7fa8;
        }

        .search-box button {
            background: linear-gradient(135deg, #1a5bbf, #0b3d8a);
            border: none;
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 40px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .search-box button:hover {
            transform: scale(1.03);
            box-shadow: 0 0 20px rgba(30, 144, 255, 0.2);
        }

        /* ===== PATH ===== */
        .path-bar {
            background: rgba(0, 18, 40, 0.25);
            border-radius: 2rem;
            padding: 0.5rem 1.2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            border: 1px solid rgba(30, 144, 255, 0.06);
            min-height: 3rem;
        }

        .path-bar i {
            color: #4a8eff;
            font-size: 0.9rem;
        }

        .path-bar .path-sep {
            color: #4a6a8a;
        }

        .path-part {
            color: #8aafdf;
            font-size: 0.85rem;
            cursor: pointer;
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .path-part:hover {
            color: #b3d9ff;
        }

        .path-part.current {
            color: #b3d9ff;
            font-weight: 500;
        }

        /* ===== FILE GRID ===== */
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            min-height: 200px;
        }

        .file-item {
            background: rgba(0, 18, 40, 0.2);
            border-radius: 1.5rem;
            padding: 1.2rem 1rem;
            text-align: center;
            border: 1px solid rgba(30, 144, 255, 0.06);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            position: relative;
        }

        .file-item:hover {
            background: rgba(30, 144, 255, 0.06);
            border-color: rgba(30, 144, 255, 0.15);
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .file-item .file-icon {
            font-size: 2.8rem;
            margin-bottom: 0.5rem;
            display: block;
            filter: drop-shadow(0 0 10px rgba(30, 144, 255, 0.1));
        }

        .file-item .file-icon.folder {
            color: #f0b34b;
        }

        .file-item .file-icon.image {
            color: #6bcb6b;
        }

        .file-item .file-icon.pdf {
            color: #ff6b6b;
        }

        .file-item .file-icon.zip {
            color: #c084fc;
        }

        .file-item .file-icon.code {
            color: #4fc3f7;
        }

        .file-item .file-icon.text {
            color: #81c784;
        }

        .file-item .file-icon.video {
            color: #ff8a65;
        }

        .file-item .file-icon.audio {
            color: #ce93d8;
        }

        .file-item .file-name {
            color: #cde0ff;
            font-size: 0.85rem;
            font-weight: 500;
            word-break: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .file-item .file-size {
            color: #5a7fa8;
            font-size: 0.7rem;
            margin-top: 0.3rem;
            display: block;
        }

        .file-item .file-check {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 2px solid rgba(30, 144, 255, 0.15);
            background: transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: transparent;
            font-size: 0.7rem;
        }

        .file-item.selected .file-check {
            background: #1e90ff;
            border-color: #1e90ff;
            color: white;
            box-shadow: 0 0 20px rgba(30, 144, 255, 0.3);
        }

        .file-item.selected {
            border-color: #1e90ff;
            background: rgba(30, 144, 255, 0.05);
        }

        .file-item .download-link {
            display: inline-block;
            margin-top: 0.5rem;
            color: #4a8eff;
            font-size: 0.7rem;
            text-decoration: none;
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }

        .file-item .download-link:hover {
            opacity: 1;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem 1rem;
            color: #5a7fa8;
        }

        .empty-state i {
            font-size: 3rem;
            color: #4a6a8a;
            margin-bottom: 1rem;
            display: block;
        }

        /* ===== STATUS BAR ===== */
        .status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.8rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(30, 144, 255, 0.06);
            color: #5a7fa8;
            font-size: 0.8rem;
        }

        .status-bar .items-count i {
            color: #4a8eff;
            margin-right: 4px;
        }

        /* ===== MODAL ===== */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 100;
            padding: 1.5rem;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: rgba(8, 13, 24, 0.95);
            border-radius: 2rem;
            padding: 2rem 2.5rem;
            max-width: 450px;
            width: 100%;
            border: 1px solid rgba(30, 144, 255, 0.15);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.8);
        }

        .modal h3 {
            color: #d6e6ff;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal h3 i {
            color: #4a8eff;
        }

        .modal p {
            color: #8aafdf;
            font-size: 0.9rem;
            margin-bottom: 1.2rem;
        }

        .modal input,
        .modal input[type="file"] {
            width: 100%;
            padding: 0.7rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(30, 144, 255, 0.15);
            background: rgba(0, 18, 40, 0.4);
            color: #d6e6ff;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s ease;
            margin-bottom: 0.8rem;
        }

        .modal input:focus {
            border-color: #1e90ff;
            box-shadow: 0 0 20px rgba(30, 144, 255, 0.1);
        }

        .modal input::placeholder {
            color: #5a7fa8;
        }

        .modal input[type="file"] {
            padding: 0.5rem;
            color: #8aafdf;
        }

        .modal input[type="file"]::-webkit-file-upload-button {
            background: rgba(30, 144, 255, 0.1);
            border: none;
            color: #b0ceff;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            cursor: pointer;
        }

        .modal .modal-actions {
            display: flex;
            gap: 0.8rem;
            justify-content: flex-end;
            margin-top: 0.5rem;
        }

        .modal .modal-actions button,
        .modal .modal-actions .btn-cancel,
        .modal .modal-actions .btn-confirm {
            padding: 0.6rem 1.5rem;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal .modal-actions .btn-cancel {
            background: rgba(255, 255, 255, 0.05);
            color: #8aafdf;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .modal .modal-actions .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .modal .modal-actions .btn-confirm {
            background: linear-gradient(135deg, #1a5bbf, #0b3d8a);
            color: white;
            border: 1px solid rgba(30, 144, 255, 0.3);
            box-shadow: 0 0 25px rgba(30, 144, 255, 0.15);
        }

        .modal .modal-actions .btn-confirm:hover {
            transform: scale(1.03);
            box-shadow: 0 0 35px rgba(30, 144, 255, 0.25);
        }

        .modal .modal-actions .btn-confirm.danger {
            background: linear-gradient(135deg, #c0392b, #962d22);
            border-color: rgba(255, 70, 70, 0.3);
        }

        /* ===== NOTIFICATION ===== */
        .notification {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: rgba(8, 13, 24, 0.95);
            backdrop-filter: blur(12px);
            padding: 1rem 1.8rem;
            border-radius: 2rem;
            border: 1px solid rgba(30, 144, 255, 0.15);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
            color: #d6e6ff;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(120px);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 200;
        }

        .notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        .notification i {
            color: #4a8eff;
            font-size: 1
