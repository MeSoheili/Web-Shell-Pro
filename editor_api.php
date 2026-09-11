<?php
// editor_api.php - API for file operations with all features
// Version: 2.5.0 - Complete with all features
// ⚠️ Attention: No spaces or characters before this line!

require_once 'auth.php';

// ============================================
// Authentication Check
// ============================================
if (!$auth->isAuthenticated()) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ============================================
// Set JSON Response Header
// ============================================
header('Content-Type: application/json');

// ============================================
// Get Action
// ============================================
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// ============================================
// Get Current Directory from Session
// ============================================
$currentDir = isset($_SESSION['current_dir']) ? $_SESSION['current_dir'] : getcwd();

// ============================================
// Configuration
// ============================================
$allowedExtensions = ['php', 'html', 'htm', 'css', 'js', 'txt', 'json', 'xml', 'sql', 'md', 'py', 'sh', 'bat'];
$forbiddenDirectories = ['logs', 'temp', 'backups', 'assets', 'lib', 'vendor', 'node_modules'];

// ============================================
// Helper Functions
// ============================================

function isEditableFile($filename) {
    global $allowedExtensions;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowedExtensions);
}

function isForbiddenPath($path) {
    global $forbiddenDirectories;
    $pathParts = explode(DIRECTORY_SEPARATOR, $path);
    foreach ($forbiddenDirectories as $forbidden) {
        if (in_array($forbidden, $pathParts)) {
            return true;
        }
    }
    return false;
}

function getFileContent($filepath) {
    if (!file_exists($filepath)) {
        return ['error' => 'File not found'];
    }
    
    if (!isEditableFile($filepath)) {
        return ['error' => 'File type not editable'];
    }
    
    $content = file_get_contents($filepath);
    if ($content === false) {
        return ['error' => 'Cannot read file'];
    }
    
    return ['content' => $content];
}

function saveFileContent($filepath, $content) {
    if (!isEditableFile($filepath)) {
        return ['error' => 'File type not editable'];
    }
    
    // Check if path is forbidden
    if (isForbiddenPath($filepath)) {
        return ['error' => 'Cannot write to protected directory'];
    }
    
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return ['error' => 'Cannot create directory'];
        }
    }
    
    $result = file_put_contents($filepath, $content);
    if ($result === false) {
        return ['error' => 'Cannot write file'];
    }
    
    Logger::log('EDIT', "File saved: {$filepath}");
    return ['success' => true, 'message' => 'File saved successfully'];
}

function deleteFile($filepath) {
    if (!file_exists($filepath)) {
        return ['error' => 'File not found'];
    }
    
    // Check if path is forbidden
    if (isForbiddenPath($filepath)) {
        return ['error' => 'Cannot delete protected directory'];
    }
    
    if (is_dir($filepath)) {
        // Check if directory is empty
        $files = scandir($filepath);
        $files = array_diff($files, ['.', '..']);
        if (!empty($files)) {
            return ['error' => 'Directory is not empty'];
        }
        if (!rmdir($filepath)) {
            return ['error' => 'Cannot delete directory'];
        }
    } else {
        if (!unlink($filepath)) {
            return ['error' => 'Cannot delete file'];
        }
    }
    
    Logger::log('DELETE', "Deleted: {$filepath}");
    return ['success' => true, 'message' => 'Deleted successfully'];
}

function renameFile($oldPath, $newName) {
    if (!file_exists($oldPath)) {
        return ['error' => 'File not found'];
    }
    
    // Validate new name
    if (empty($newName) || preg_match('/[\/\\\:*?"<>|]/', $newName)) {
        return ['error' => 'Invalid file name'];
    }
    
    // Check if path is forbidden
    if (isForbiddenPath($oldPath)) {
        return ['error' => 'Cannot rename protected directory'];
    }
    
    $dir = dirname($oldPath);
    $newPath = $dir . DIRECTORY_SEPARATOR . $newName;
    
    if (file_exists($newPath)) {
        return ['error' => 'A file with this name already exists'];
    }
    
    if (!rename($oldPath, $newPath)) {
        return ['error' => 'Cannot rename file'];
    }
    
    Logger::log('RENAME', "Renamed: {$oldPath} → {$newPath}");
    return ['success' => true, 'message' => 'Renamed successfully', 'new_path' => $newPath];
}

function createFile($filepath) {
    // Check if path is forbidden
    if (isForbiddenPath($filepath)) {
        return ['error' => 'Cannot create file in protected directory'];
    }
    
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return ['error' => 'Cannot create directory'];
        }
    }
    
    if (file_exists($filepath)) {
        return ['error' => 'File already exists'];
    }
    
    $result = file_put_contents($filepath, '');
    if ($result === false) {
        return ['error' => 'Cannot create file'];
    }
    
    Logger::log('CREATE', "File created: {$filepath}");
    return ['success' => true, 'message' => 'File created successfully'];
}

function createFolder($folderpath) {
    // Check if path is forbidden
    if (isForbiddenPath($folderpath)) {
        return ['error' => 'Cannot create folder in protected directory'];
    }
    
    if (file_exists($folderpath)) {
        return ['error' => 'Folder already exists'];
    }
    
    if (!mkdir($folderpath, 0755, true)) {
        return ['error' => 'Cannot create folder'];
    }
    
    Logger::log('CREATE', "Folder created: {$folderpath}");
    return ['success' => true, 'message' => 'Folder created successfully'];
}

function getFileList($dir) {
    $dir = rtrim($dir, '/\\');
    if (!is_dir($dir)) {
        return ['error' => 'Directory not found'];
    }
    
    $files = scandir($dir);
    $result = [];
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        $isDir = is_dir($path);
        $size = is_file($path) ? filesize($path) : 0;
        
        $result[] = [
            'name' => $file,
            'path' => $path,
            'is_dir' => $isDir,
            'size' => $size,
            'modified' => is_file($path) ? filemtime($path) : 0,
            'editable' => isEditableFile($file)
        ];
    }
    
    // Sort: folders first, then files
    usort($result, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    
    return ['files' => $result];
}

function searchFiles($dir, $query) {
    $dir = rtrim($dir, '/\\');
    if (!is_dir($dir)) {
        return ['error' => 'Directory not found'];
    }
    
    if (empty($query)) {
        return ['files' => []];
    }
    
    $results = [];
    $queryLower = strtolower($query);
    
    // Recursive search function
    function searchRecursive($dir, $query, &$results) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            $isDir = is_dir($path);
            
            // Check if file name matches query
            if (stripos($file, $query) !== false) {
                $size = is_file($path) ? filesize($path) : 0;
                $results[] = [
                    'name' => $file,
                    'path' => $path,
                    'is_dir' => $isDir,
                    'size' => $size,
                    'editable' => isEditableFile($file)
                ];
            }
            
            // Recursively search subdirectories (limit depth)
            if ($isDir && $file !== 'logs' && $file !== 'temp' && $file !== 'backups') {
                searchRecursive($path, $query, $results);
            }
        }
    }
    
    searchRecursive($dir, $query, $results);
    
    // Limit results to prevent overload
    $results = array_slice($results, 0, 100);
    
    return ['files' => $results];
}

function downloadFile($filepath) {
    if (!file_exists($filepath) || is_dir($filepath)) {
        return ['error' => 'File not found'];
    }
    
    // Check if path is forbidden
    if (isForbiddenPath($filepath)) {
        return ['error' => 'Cannot download protected file'];
    }
    
    // Set download headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: private');
    
    // Read and output file
    readfile($filepath);
    exit;
}

// ============================================
// Handle Actions
// ============================================

switch ($action) {
    case 'open':
        $filepath = isset($_POST['filepath']) ? $_POST['filepath'] : '';
        if (empty($filepath)) {
            echo json_encode(['error' => 'No file specified']);
            exit;
        }
        echo json_encode(getFileContent($filepath));
        break;
        
    case 'save':
        $filepath = isset($_POST['filepath']) ? $_POST['filepath'] : '';
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        if (empty($filepath)) {
            echo json_encode(['error' => 'No file specified']);
            exit;
        }
        echo json_encode(saveFileContent($filepath, $content));
        break;
        
    case 'create':
        $filepath = isset($_POST['filepath']) ? $_POST['filepath'] : '';
        if (empty($filepath)) {
            echo json_encode(['error' => 'No file specified']);
            exit;
        }
        // Check if creating a folder or file
        if (isset($_POST['is_folder']) && $_POST['is_folder'] === 'true') {
            echo json_encode(createFolder($filepath));
        } else {
            echo json_encode(createFile($filepath));
        }
        break;
        
    case 'delete':
        $filepath = isset($_POST['filepath']) ? $_POST['filepath'] : '';
        if (empty($filepath)) {
            echo json_encode(['error' => 'No file specified']);
            exit;
        }
        echo json_encode(deleteFile($filepath));
        break;
        
    case 'rename':
        $oldPath = isset($_POST['old_path']) ? $_POST['old_path'] : '';
        $newName = isset($_POST['new_name']) ? $_POST['new_name'] : '';
        if (empty($oldPath) || empty($newName)) {
            echo json_encode(['error' => 'Missing parameters']);
            exit;
        }
        echo json_encode(renameFile($oldPath, $newName));
        break;
        
    case 'list':
        $dir = isset($_POST['dir']) ? $_POST['dir'] : $currentDir;
        echo json_encode(getFileList($dir));
        break;
        
    case 'search':
        $dir = isset($_POST['dir']) ? $_POST['dir'] : $currentDir;
        $query = isset($_POST['query']) ? $_POST['query'] : '';
        echo json_encode(searchFiles($dir, $query));
        break;
        
    case 'download':
        $filepath = isset($_GET['filepath']) ? $_GET['filepath'] : '';
        if (empty($filepath)) {
            echo json_encode(['error' => 'No file specified']);
            exit;
        }
        downloadFile($filepath);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>