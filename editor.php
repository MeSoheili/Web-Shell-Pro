<?php
// editor.php - Professional Code Editor with Advanced File Explorer
// Version: 2.7.0 - Fixed Expand/Collapse with dynamic loading
// ⚠️ Attention: No spaces or characters before this line!

require_once 'auth.php';

// ============================================
// Authentication Check
// ============================================
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

// ============================================
// Logout Processing
// ============================================
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: index.php');
    exit;
}

// ============================================
// Get Current Directory
// ============================================
$currentDir = isset($_SESSION['current_dir']) ? $_SESSION['current_dir'] : getcwd();
$currentDirForJS = str_replace('\\', '/', $currentDir);

// Get file to open from URL
$openFile = isset($_GET['file']) ? $_GET['file'] : '';

// ============================================
// Configuration
// ============================================
$allowedExtensions = ['php', 'html', 'htm', 'css', 'js', 'txt', 'json', 'xml', 'sql', 'md', 'py', 'sh', 'bat'];
$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'ico', 'webp'];
$protectedDirs = ['logs', 'temp', 'backups', 'assets', 'lib', 'vendor', 'node_modules'];

// ============================================
// Helper Functions
// ============================================

function isEditableFile($filename) {
    global $allowedExtensions;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowedExtensions);
}

function isImageFile($filename) {
    global $imageExtensions;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $imageExtensions);
}

function escapeJsPath($path) {
    return str_replace('\\', '/', $path);
}

function getFileSize($bytes) {
    if ($bytes === 0) return '';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'php' => '🐘', 'html' => '🌐', 'htm' => '🌐',
        'css' => '🎨', 'js' => '⚡', 'json' => '📋',
        'xml' => '📋', 'sql' => '🗄️', 'md' => '📝',
        'py' => '🐍', 'sh' => '💻', 'bat' => '💻',
        'txt' => '📄'
    ];
    return isset($icons[$ext]) ? $icons[$ext] : '📄';
}

/**
 * Get file list for a directory (only one level)
 */
function getFileList($dir) {
    $items = [];
    $dir = rtrim($dir, '/\\');
    
    if (!is_dir($dir)) {
        return $items;
    }
    
    $files = scandir($dir);
    sort($files);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        $isDir = is_dir($path);
        $size = is_file($path) ? filesize($path) : 0;
        
        $items[] = [
            'name' => $file,
            'path' => $path,
            'js_path' => escapeJsPath($path),
            'is_dir' => $isDir,
            'icon' => $isDir ? '📁' : getFileIcon($file),
            'editable' => isEditableFile($file),
            'size' => $size,
            'size_formatted' => getFileSize($size),
            'has_children' => $isDir ? (count(scandir($path)) > 2) : false
        ];
    }
    
    // Sort: folders first, then files
    usort($items, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $items;
}

// Get root file list
$rootFiles = getFileList($currentDir);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Shell Pro - Code Editor</title>
    
    <!-- CodeMirror CSS -->
    <link rel="stylesheet" href="assets/lib/codemirror/codemirror.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Main Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/editor.css">
    
    <style>
        /* File Tree Styles */
        .file-tree {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .file-tree li {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .file-item {
            display: flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 3px;
            cursor: default;
            gap: 4px;
            transition: background 0.15s;
        }
        .file-item:hover {
            background: #151525;
        }
        .file-item .file-icon {
            font-size: 14px;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }
        .file-item .folder-toggle {
            cursor: pointer;
            font-size: 10px;
            color: #555;
            width: 16px;
            text-align: center;
            flex-shrink: 0;
            transition: transform 0.2s;
            user-select: none;
        }
        .file-item .folder-toggle.expanded {
            transform: rotate(90deg);
            color: #4CAF50;
        }
        .file-item .folder-toggle:hover {
            color: #aaa;
        }
        .file-item .folder-name {
            color: #4CAF50;
            cursor: pointer;
            flex: 1;
            font-size: 12px;
            padding: 2px 0;
        }
        .file-item .folder-name:hover {
            text-decoration: underline;
        }
        .file-item .file-name {
            color: #3498db;
            cursor: pointer;
            flex: 1;
            font-size: 12px;
            text-decoration: none;
            padding: 2px 0;
        }
        .file-item .file-name:hover {
            text-decoration: underline;
        }
        .file-item .file-name.non-editable {
            color: #555;
            cursor: not-allowed;
        }
        .file-item .file-name.non-editable:hover {
            text-decoration: none;
        }
        .file-item .file-size {
            color: #444;
            font-size: 9px;
            flex-shrink: 0;
            margin-right: 4px;
        }
        .file-item .file-actions {
            display: none;
            gap: 2px;
            flex-shrink: 0;
        }
        .file-item:hover .file-actions {
            display: flex;
        }
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 11px;
            padding: 1px 4px;
            border-radius: 3px;
            color: #666;
            transition: all 0.2s;
        }
        .action-btn:hover {
            transform: scale(1.15);
        }
        .action-btn.rename-btn:hover {
            color: #3498db;
            background: #1a2a4a;
        }
        .action-btn.delete-btn:hover {
            color: #e74c3c;
            background: #2a1a1a;
        }
        .folder-children {
            list-style: none;
            padding-left: 20px;
            margin: 0;
            display: none;
        }
        .folder-children.expanded {
            display: block;
        }
        .file-tree-empty {
            padding: 20px;
            text-align: center;
            color: #444;
            font-size: 12px;
        }
        .file-tree-loading {
            padding: 15px;
            text-align: center;
            color: #444;
            font-size: 12px;
        }
        .file-tree-loading .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #1a1a2e;
            border-top-color: #4CAF50;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="editor-container">
        <!-- Editor Header -->
        <div class="editor-header">
            <div class="editor-title">
                <div class="window-controls">
                    <span class="dot dot-red"></span>
                    <span class="dot dot-yellow"></span>
                    <span class="dot dot-green"></span>
                </div>
                <span class="logo">✏️</span>
                <span>Web Shell Pro - Editor</span>
                <span class="os-badge <?php echo IS_WINDOWS ? 'windows' : 'linux'; ?>">
                    <?php echo IS_WINDOWS ? '🪟 Windows' : '🐧 Linux'; ?>
                </span>
                <span class="working-dir" title="<?php echo htmlspecialchars($currentDir); ?>" id="currentDir">
                    📂 <?php echo htmlspecialchars($currentDirForJS); ?>
                </span>
            </div>
            <div class="editor-actions">
                <span class="user-info">👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="terminal.php" class="btn-terminal">🖥️ Terminal</a>
                <a href="admin.php" class="btn-admin">⚙️ Admin</a>
                <a href="?logout=1" class="btn-logout" onclick="return confirm('Are you sure you want to logout?')">🚪 Logout</a>
            </div>
        </div>
        
        <!-- Editor Body -->
        <div class="editor-body">
            <!-- Sidebar - File Explorer -->
            <div class="editor-sidebar" id="fileTree">
                <div class="sidebar-header">
                    <div class="header-title">
                        <span class="icon">📂</span>
                        <span>File Explorer</span>
                    </div>
                    <button class="btn-refresh" onclick="refreshFileTree()" title="Refresh">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                
                <!-- Search Bar -->
                <div class="search-bar">
                    <span class="search-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="fileSearch" placeholder="Search files..." oninput="searchFiles(this.value)">
                    <span class="search-clear" onclick="clearSearch()">✕</span>
                    <span class="search-count" id="searchCount"></span>
                </div>
                
                <!-- Sidebar Actions -->
                <div class="sidebar-actions">
                    <button class="action-btn-sm" onclick="expandAllFolders()">
                        <span class="icon">📂</span> Expand All
                    </button>
                    <button class="action-btn-sm" onclick="collapseAllFolders()">
                        <span class="icon">📁</span> Collapse All
                    </button>
                    <button class="action-btn-sm primary" onclick="refreshFileTree()">
                        <span class="icon">🔄</span> Refresh
                    </button>
                </div>
                
                <div class="sidebar-content" id="fileTreeContent">
                    <!-- File tree will be rendered by JavaScript -->
                </div>
            </div>
            
            <!-- Editor Main Area -->
            <div class="editor-main">
                <!-- Tabs Bar -->
                <div class="tabs-bar" id="tabsBar">
                    <div class="tabs-container" id="tabsContainer">
                        <!-- Tabs will be added dynamically -->
                    </div>
                    <div class="tabs-actions">
                        <button class="btn-new-tab" onclick="openNewFile()" title="New File">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn-save-all" onclick="saveAllFiles()" title="Save All">
                            <i class="fas fa-save"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Editor Area -->
                <div class="editor-wrapper" id="editorWrapper">
                    <div class="editor-placeholder" id="editorPlaceholder">
                        <div class="placeholder-content">
                            <span class="icon">✏️</span>
                            <h3>Welcome to Code Editor</h3>
                            <p>Select a file from the sidebar or create a new file to start editing</p>
                            <div class="placeholder-actions">
                                <button class="btn-primary" onclick="openNewFile()">
                                    <i class="fas fa-plus"></i> New File
                                </button>
                                <button class="btn-secondary" onclick="document.getElementById('fileInput').click()">
                                    <i class="fas fa-folder-open"></i> Open File
                                </button>
                                <input type="file" id="fileInput" style="display:none" onchange="uploadFile(this)">
                            </div>
                        </div>
                    </div>
                    <textarea id="codeEditor" style="display:none;"></textarea>
                </div>
            </div>
        </div>
        
        <!-- Status Bar -->
        <div class="editor-statusbar" id="statusBar">
            <span id="statusInfo">Ready</span>
            <span class="status-divider">|</span>
            <span id="statusCursor">Ln 1, Col 1</span>
            <span class="status-divider">|</span>
            <span id="statusMode">Plain Text</span>
            <span class="status-divider">|</span>
            <span id="statusEncoding">UTF-8</span>
        </div>
    </div>
    
    <!-- ============================================
    JavaScript Libraries
    ============================================ -->
    <!-- CodeMirror -->
    <script src="assets/lib/codemirror/codemirror.js"></script>
    <script src="assets/lib/codemirror/mode/xml/xml.js"></script>
    <script src="assets/lib/codemirror/mode/css/css.js"></script>
    <script src="assets/lib/codemirror/mode/javascript/javascript.js"></script>
    <script src="assets/lib/codemirror/mode/htmlmixed/htmlmixed.js"></script>
    <script src="assets/lib/codemirror/mode/php/php.js"></script>
    <script src="assets/lib/codemirror/addon/edit/matchbrackets.js"></script>
    <script src="assets/lib/codemirror/addon/selection/active-line.js"></script>

    <!-- Pass root files to JavaScript -->
    <script>
        window.rootFiles = <?php echo json_encode($rootFiles); ?>;
        window.currentDir = <?php echo json_encode($currentDirForJS); ?>;
    </script>
    
    <!-- Editor Script -->
    <script src="assets/js/editor.js"></script>
</body>
</html>
<?php
// End of file - No spaces after this line
?>