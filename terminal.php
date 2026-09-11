<?php
// terminal.php - Web Shell Pro Terminal (English Version - LTR)
// ⚠️ Attention: No spaces or characters before this line!
// Version: 2.0.0
// ⚠️ This file contains ONLY PHP + HTML - All CSS/JS are in separate files!

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
// Current Directory Management using Session
// ============================================

if (!isset($_SESSION['current_dir'])) {
    $_SESSION['current_dir'] = getcwd();
}

if (!chdir($_SESSION['current_dir'])) {
    $_SESSION['current_dir'] = getcwd();
    chdir($_SESSION['current_dir']);
}

function changeDirectory($newPath) {
    if (chdir($newPath)) {
        $_SESSION['current_dir'] = getcwd();
        return true;
    }
    return false;
}

// ============================================
// Linux to Windows Command Converter
// ============================================

function normalizeCommand($command) {
    $command = trim($command);
    
    if (empty($command)) {
        return $command;
    }
    
    $conversions = [
        '/^ls\s*-la\s*$/i' => 'dir /a',
        '/^ls\s*-l\s*$/i' => 'dir',
        '/^ls\s*-a\s*$/i' => 'dir /a',
        '/^ls\s*-al\s*$/i' => 'dir /a',
        '/^ls\s+-la\s*$/i' => 'dir /a',
        '/^ls\s+(.+)$/i' => 'dir $1',
        '/^pwd\s*$/i' => 'echo %cd%',
        '/^cat\s+(.+)$/i' => 'type $1',
        '/^clear\s*$/i' => 'cls',
        '/^cp\s+(.+?)\s+(.+)$/i' => 'copy $1 $2',
        '/^mv\s+(.+?)\s+(.+)$/i' => 'move $1 $2',
        '/^rm\s+(.+)$/i' => 'del $1',
        '/^rm\s+-rf\s+(.+)$/i' => 'rmdir /s /q $1',
        '/^mkdir\s+(.+)$/i' => 'mkdir $1',
        '/^rmdir\s+(.+)$/i' => 'rmdir $1',
        '/^grep\s+(.+?)\s+(.+)$/i' => 'findstr $1 $2',
        '/^ps\s*$/i' => 'tasklist',
        '/^kill\s+(\d+)$/i' => 'taskkill /PID $1',
        '/^ifconfig\s*$/i' => 'ipconfig',
        '/^uname\s*-a\s*$/i' => 'ver',
        '/^whoami\s*$/i' => 'whoami',
        '/^hostname\s*$/i' => 'hostname',
        '/^date\s*$/i' => 'date /t',
        '/^time\s*$/i' => 'time /t',
    ];
    
    foreach ($conversions as $pattern => $replacement) {
        if (preg_match($pattern, $command)) {
            $newCommand = preg_replace($pattern, $replacement, $command);
            if ($newCommand !== $command) {
                return $newCommand;
            }
        }
    }
    
    return $command;
}

// ============================================
// Command Processing
// ============================================

$output = '';
$command = '';
$returnCode = 0;
$currentDir = $_SESSION['current_dir'];
$originalCommand = '';
$isCommandExecuted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    $originalCommand = trim($_POST['command']);
    $command = $originalCommand;
    $isCommandExecuted = true;
    
    if (empty($command)) {
        $output = '';
        $returnCode = 0;
    } else {
        if (IS_WINDOWS) {
            $command = normalizeCommand($command);
        }
        
        $baseCommand = explode(' ', $command)[0];
        $baseCommand = strtolower($baseCommand);
        
        // ============================================
        // Custom Commands
        // ============================================
        
        if ($baseCommand === 'check_functions' || $originalCommand === 'check_functions' || $originalCommand === 'check') {
            $functions = ['exec', 'shell_exec', 'passthru', 'proc_open', 'system', 'popen'];
            $output = "🔍 Checking PHP Execution Functions:\n";
            $output .= str_repeat('=', 55) . "\n\n";
            
            $allActive = true;
            $activeList = [];
            $inactiveList = [];
            
            foreach ($functions as $func) {
                $exists = function_exists($func);
                $status = $exists ? '✅ Active' : '❌ Inactive';
                $output .= "  • {$func}: " . $status . "\n";
                if ($exists) {
                    $activeList[] = $func;
                } else {
                    $inactiveList[] = $func;
                    $allActive = false;
                }
            }
            
            $output .= "\n" . str_repeat('=', 55) . "\n";
            $output .= "📊 Summary:\n";
            $output .= "  • Active: " . count($activeList) . " (" . implode(', ', $activeList) . ")\n";
            $output .= "  • Inactive: " . count($inactiveList) . " (" . implode(', ', $inactiveList) . ")\n\n";
            
            if ($allActive) {
                $output .= "✅ All execution functions are active.\n";
                $output .= "💡 You can use Shell commands.\n";
            } else {
                $output .= "⚠️ Some functions are inactive.\n";
                $output .= "📌 The hosting admin may have restricted them in php.ini.\n";
                $output .= "💡 Use 'phpinfo' for more information.\n";
            }
            
            $returnCode = 0;
            Logger::logCommand($originalCommand, $output, $returnCode);
        }
        elseif ($baseCommand === 'phpinfo' || $originalCommand === 'phpinfo') {
            ob_start();
            phpinfo();
            $output = ob_get_clean();
            $output = strip_tags($output);
            $output = "📋 PHP Information:\n" . str_repeat('=', 55) . "\n\n" . $output;
            $returnCode = 0;
            Logger::logCommand($originalCommand, $output, $returnCode);
        }
        elseif ($baseCommand === 'php_version' || $baseCommand === 'phpver' || $originalCommand === 'php_version') {
            $output = "🐘 PHP Version: " . phpversion() . "\n";
            $output .= "📅 Release Date: " . date(DATETIME_FORMAT) . "\n";
            $output .= "💻 Operating System: " . PHP_OS . "\n";
            $output .= "📂 SAPI: " . php_sapi_name() . "\n";
            $output .= "📁 php.ini Path: " . php_ini_loaded_file() . "\n\n";
            
            $output .= "📦 Loaded Extensions (" . count(get_loaded_extensions()) . "):\n";
            $output .= str_repeat('-', 55) . "\n";
            $modules = get_loaded_extensions();
            sort($modules);
            $output .= implode(', ', $modules) . "\n";
            
            $returnCode = 0;
            Logger::logCommand($originalCommand, $output, $returnCode);
        }
        elseif ($baseCommand === 'clear' || $baseCommand === 'cls' || $originalCommand === 'clear') {
            header('Location: terminal.php');
            exit;
        }
        elseif ($baseCommand === 'help' || $originalCommand === 'help' || $originalCommand === '?') {
            $output = "📚 Web Shell Pro Command Help\n";
            $output .= str_repeat('=', 55) . "\n\n";
            $output .= "🔧 System Commands:\n";
            $output .= "  • cd [path]    - Change directory\n";
            $output .= "  • pwd          - Show current directory\n";
            $output .= "  • ls [-la]     - List files\n";
            $output .= "  • clear        - Clear screen\n\n";
            $output .= "🐘 PHP Commands:\n";
            $output .= "  • check_functions - Check PHP execution functions\n";
            $output .= "  • phpinfo        - Show PHP configuration\n";
            $output .= "  • php_version    - Show PHP version\n\n";
            $output .= "📂 File Management:\n";
            $output .= "  • cat [file]    - Display file content\n";
            $output .= "  • cp [src] [dst] - Copy file\n";
            $output .= "  • mv [src] [dst] - Move file\n";
            $output .= "  • mkdir [dir]   - Create directory\n";
            $output .= "  • rm [file]     - Delete file\n\n";
            $output .= "💻 System Commands:\n";
            $output .= "  • whoami        - Show current user\n";
            $output .= "  • hostname      - Show hostname\n";
            $output .= "  • date          - Show date\n";
            $output .= "  • time          - Show time\n";
            $output .= "  • ps            - List processes\n";
            $output .= "  • ifconfig      - Network information\n\n";
            $output .= "❓ Type 'help' for this help message.\n";
            
            $returnCode = 0;
            Logger::logCommand($originalCommand, $output, $returnCode);
        }
        elseif ($baseCommand === 'cd') {
            $parts = explode(' ', $command, 2);
            $newDir = isset($parts[1]) ? trim($parts[1]) : '';
            
            if (empty($newDir)) {
                $output = "📂 Current Directory: " . $_SESSION['current_dir'];
                $returnCode = 0;
            } else {
                $targetDir = $newDir;
                
                if (!preg_match('/^[a-zA-Z]:\\\\|^\\\\|^\//', $targetDir)) {
                    $targetDir = $_SESSION['current_dir'] . DIRECTORY_SEPARATOR . $targetDir;
                }
                
                $targetDir = realpath($targetDir);
                
                if ($targetDir === false) {
                    $output = "❌ Error: Directory '{$newDir}' does not exist.";
                    $returnCode = 1;
                } else {
                    if (changeDirectory($targetDir)) {
                        $output = "✔️ Changed to '{$targetDir}'";
                        $currentDir = $_SESSION['current_dir'];
                        $returnCode = 0;
                    } else {
                        $output = "❌ Error: Cannot access '{$targetDir}'";
                        $returnCode = 1;
                    }
                }
            }
        }
        elseif (isDangerousCommand($command)) {
            $output = "⚠️ Command '{$baseCommand}' is blocked for security.\n";
            $output .= "📌 This command is in the restricted list.";
            Logger::log('BLOCKED_CMD', "User attempted dangerous command: {$command}");
            $returnCode = 1;
        }
        else {
            $allowed = false;
            foreach (ALLOWED_COMMANDS as $allowedCmd) {
                if (stripos($baseCommand, $allowedCmd) !== false && stripos($baseCommand, $allowedCmd) === 0) {
                    $allowed = true;
                    break;
                }
            }
            
            if (!$allowed) {
                $output = "⚠️ Command '{$baseCommand}' is not allowed.\n";
                $output .= "💡 Use 'help' to see allowed commands.";
                Logger::log('UNAUTHORIZED_CMD', "User attempted unauthorized command: {$command}");
                $returnCode = 1;
            } else {
                if (strtolower($baseCommand) === 'dir' || strtolower($baseCommand) === 'dir /a') {
                    if (!preg_match('/\s+[a-zA-Z]:\\\\|\\\\|\//', $command)) {
                        $command = $command . ' ' . $_SESSION['current_dir'];
                    }
                }
                
                if (IS_WINDOWS) {
                    $fullCommand = 'cd /d ' . $_SESSION['current_dir'] . ' && ' . $command . ' 2>&1';
                } else {
                    $fullCommand = 'cd ' . $_SESSION['current_dir'] . ' && ' . $command . ' 2>&1';
                }
                
                $execOutput = [];
                exec($fullCommand, $execOutput, $returnCode);
                $output = implode("\n", $execOutput);
                
                if (empty($output) && $returnCode !== 0) {
                    $output = "❌ Command failed with error code: {$returnCode}";
                } elseif (empty($output) && $returnCode === 0) {
                    $output = "✔️ Command executed successfully (no output)";
                }
                
                Logger::logCommand($originalCommand, $output, $returnCode);
            }
        }
    }
}

$currentDir = $_SESSION['current_dir'];

// Prepare history for JavaScript
$historyJson = '[]';
if (isset($_POST['command']) && !empty($_POST['command'])) {
    $historyJson = json_encode([$_POST['command']]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Shell Pro - Terminal</title>
    
    <!-- ============================================
    CSS - Separated from PHP
    ============================================ -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="terminal-container">
        <!-- ============================================
        Terminal Header
        ============================================ -->
        <div class="terminal-header">
            <div class="terminal-title">
                <span class="dot red"></span>
                <span class="dot yellow"></span>
                <span class="dot green"></span>
                <span>Web Shell Pro</span>
                <span class="os-badge <?php echo IS_WINDOWS ? 'windows' : 'linux'; ?>">
                    <?php echo IS_WINDOWS ? '🪟 Windows' : '🐧 Linux'; ?>
                </span>
                <span class="working-dir" title="<?php echo htmlspecialchars($currentDir); ?>">
                    📂 <?php echo htmlspecialchars($currentDir); ?>
                </span>
            </div>
            <div class="terminal-actions">
                <span class="user-info">👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="editor.php" class="btn-editor">✏️ Editor</a>
                <a href="admin.php" class="btn-admin">⚙️ Admin</a>
                <a href="?logout=1" class="btn-logout" onclick="return confirm('Are you sure you want to logout?')">🚪 Logout</a>
            </div>
        </div>
        
        <!-- ============================================
        Terminal Body
        ============================================ -->
        <div class="terminal-body">
            <div class="terminal-output" id="output">
                <!-- Welcome Message -->
                <div class="welcome-message">
                    <p>🔐 <span class="label">User:</span> <span class="value"><?php echo htmlspecialchars($_SESSION['username']); ?></span></p>
                    <p>📅 <span class="label">Login Time:</span> <span class="value"><?php echo date(DATETIME_FORMAT, $_SESSION['login_time']); ?></span></p>
                    <p>🌐 <span class="label">Your IP:</span> <span class="value"><?php echo htmlspecialchars($_SESSION['ip']); ?></span></p>
                    <p>📂 <span class="label">Current Directory:</span> <span class="value"><?php echo htmlspecialchars($currentDir); ?></span></p>
                    <p>💻 <span class="label">Operating System:</span> <span class="value"><?php echo IS_WINDOWS ? 'Windows' : 'Linux/Unix'; ?></span></p>
                    <hr>
                    <p style="color: #666; font-size: 11px;">
                        💡 <span class="label">Useful Commands:</span>
                        <span style="color: #4CAF50;">check_functions</span> ·
                        <span style="color: #4CAF50;">phpinfo</span> ·
                        <span style="color: #4CAF50;">php_version</span> ·
                        <span style="color: #4CAF50;">help</span>
                    </p>
                    <div class="quick-links">
                        <span class="quick-link primary" onclick="runCommand('check_functions')">🔍 check_functions</span>
                        <span class="quick-link" onclick="runCommand('phpinfo')">📋 phpinfo</span>
                        <span class="quick-link" onclick="runCommand('php_version')">🐘 php_version</span>
                        <span class="quick-link" onclick="runCommand('cd ..')">📁 cd ..</span>
                        <span class="quick-link" onclick="runCommand('ls -la')">📋 ls -la</span>
                        <span class="quick-link" onclick="runCommand('pwd')">📍 pwd</span>
                        <span class="quick-link" onclick="runCommand('clear')">🧹 clear</span>
                        <span class="quick-link" onclick="runCommand('help')">❓ help</span>
                    </div>
                </div>
                
                <!-- Command Output Display -->
                <?php if ($isCommandExecuted && isset($output) && $output !== ''): ?>
                    <div class="command-block">
                        <div class="command-prompt">
                            <span class="prompt">$</span>
                            <span class="command"><?php echo htmlspecialchars($originalCommand ?? $command); ?></span>
                            <?php if (isset($originalCommand) && $originalCommand !== $command && !empty($command)): ?>
                                <span class="converted">→ <?php echo htmlspecialchars($command); ?></span>
                            <?php endif; ?>
                            <?php if ($returnCode === 0): ?>
                                <span class="return-code success">✓ Success</span>
                            <?php else: ?>
                                <span class="return-code error">✗ Error (Code: <?php echo $returnCode; ?>)</span>
                            <?php endif; ?>
                        </div>
                        <pre class="command-output <?php echo $returnCode === 0 ? 'success' : 'error'; ?>"><?php
                            if (!empty($output)) {
                                $outputLines = explode("\n", $output);
                                $coloredOutput = '';
                                foreach ($outputLines as $line) {
                                    if (empty($line) && $line !== '0') {
                                        continue;
                                    }
                                    $lineLower = strtolower($line);
                                    
                                    if (strpos($lineLower, 'error') !== false ||
                                        strpos($lineLower, 'خطا') !== false ||
                                        strpos($lineLower, 'not recognized') !== false ||
                                        strpos($lineLower, 'not found') !== false ||
                                        strpos($lineLower, 'access denied') !== false ||
                                        strpos($lineLower, 'invalid') !== false ||
                                        strpos($lineLower, 'permission denied') !== false ||
                                        strpos($lineLower, 'cannot') !== false) {
                                        $coloredOutput .= '<span class="error-text">' . htmlspecialchars($line) . '</span>' . "\n";
                                    }
                                    elseif (strpos($lineLower, 'warning') !== false ||
                                            strpos($lineLower, 'هشدار') !== false) {
                                        $coloredOutput .= '<span class="warning-text">' . htmlspecialchars($line) . '</span>' . "\n";
                                    }
                                    elseif (strpos($lineLower, 'success') !== false ||
                                            strpos($lineLower, 'موفق') !== false ||
                                            strpos($lineLower, 'active') !== false ||
                                            strpos($lineLower, 'فعال') !== false ||
                                            strpos($lineLower, 'connected') !== false) {
                                        $coloredOutput .= '<span class="success-text">' . htmlspecialchars($line) . '</span>' . "\n";
                                    }
                                    elseif (strpos($line, 'Volume') !== false ||
                                            strpos($line, 'Directory') !== false ||
                                            strpos($line, 'گنجایش') !== false ||
                                            strpos($line, 'مسیر') !== false ||
                                            strpos($line, 'نسخه') !== false) {
                                        $coloredOutput .= '<span class="info-text">' . htmlspecialchars($line) . '</span>' . "\n";
                                    }
                                    else {
                                        $coloredOutput .= htmlspecialchars($line) . "\n";
                                    }
                                }
                                echo $coloredOutput;
                            }
                        ?></pre>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- ============================================
            Command Input Form
            ============================================ -->
            <form method="POST" action="" class="command-form" id="commandForm" autocomplete="off">
                <div class="input-group">
                    <span class="prompt">$</span>
                    <input type="text" name="command" id="commandInput"
                           placeholder="Enter your command..."
                           autofocus
                           value="<?php echo isset($_POST['command']) ? htmlspecialchars($_POST['command']) : ''; ?>">
                    <button type="submit" id="executeBtn">▶ Execute</button>
                </div>
                <div class="form-hint">
                    <span>⚡ ↑/↓ History | Esc Clear | Tab Auto-complete</span>
                    <span>💡 <span style="color: #4CAF50;">help</span> for command list</span>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ============================================
    Pass history to JavaScript
    ============================================ -->
    <script>
        // Initial history from PHP
        window.initialHistory = <?php echo $historyJson; ?>;
    </script>
    
    <!-- ============================================
    JavaScript - Separated from PHP
    ============================================ -->
    <script src="assets/js/terminal.js"></script>
</body>
</html>
<?php
// ============================================
// End of file - No spaces or characters after this line!
// ============================================
?>