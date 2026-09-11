<?php
// admin.php - پنل مدیریت
// ⚠️ توجه: هیچ فاصله یا کاراکتری قبل از این خط وجود نداشته باشد!

require_once 'auth.php';

// بررسی احراز هویت
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

// فقط مدیر می‌تواند به پنل مدیریت دسترسی داشته باشد
if ($_SESSION['username'] !== ADMIN_USERNAME) {
    die('⛔ دسترسی غیرمجاز! شما مدیر سیستم نیستید.');
}

$security = new SecurityManager();
$message = '';
$messageType = '';

// پردازش اقدامات مدیریتی
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'unblock_ip':
                $ip = trim($_POST['ip'] ?? '');
                if ($security->unblockIp($ip)) {
                    $message = "IP {$ip} با موفقیت آزاد شد.";
                    $messageType = 'success';
                } else {
                    $message = "IP {$ip} در لیست بلاک شده‌ها یافت نشد.";
                    $messageType = 'danger';
                }
                break;
                
            case 'clear_all':
                $security->clearAllBlockedIps();
                $message = 'همه IP‌های بلاک شده پاک شدند.';
                $messageType = 'success';
                break;
                
            case 'change_password':
                $newPassword = $_POST['new_password'] ?? '';
                if (strlen($newPassword) >= 8) {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $configFile = 'config.php';
                    $content = file_get_contents($configFile);
                    $content = preg_replace(
                        "/define\('ADMIN_PASSWORD_HASH',\s*'.*?'\);/",
                        "define('ADMIN_PASSWORD_HASH', '{$newHash}');",
                        $content
                    );
                    file_put_contents($configFile, $content);
                    $message = 'رمز عبور با موفقیت تغییر کرد.';
                    $messageType = 'success';
                } else {
                    $message = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
                    $messageType = 'danger';
                }
                break;
                
            case 'clear_logs':
                $logType = $_POST['log_type'] ?? 'all';
                Logger::clearLogs($logType);
                $message = 'لاگ‌ها با موفقیت پاک شدند.';
                $messageType = 'success';
                break;
        }
    }
}

// دریافت داده‌ها
$blockedIps = $security->getBlockedIps();
$securityStats = $security->getSecurityStats();
$authLogs = Logger::getLogs('auth', 50);
$commandLogs = Logger::getLogs('commands', 50);
$logStats = Logger::getStats();
$systemInfo = getSystemInfo();
$securityStatus = getSecurityStatus();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت - Web Shell Pro</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a12;
            color: #d4d4d4;
            padding: 20px;
            min-height: 100vh;
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .admin-header {
            background: #16162a;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid #1f1f3a;
        }
        
        .admin-header h1 {
            font-size: 22px;
            color: #e94560;
        }
        
        .admin-nav {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .admin-nav a {
            color: #3498db;
            text-decoration: none;
            font-size: 14px;
            padding: 6px 14px;
            border-radius: 6px;
            background: #0d0d1a;
            border: 1px solid #1a1a2a;
            transition: all 0.3s;
        }
        
        .admin-nav a:hover {
            background: #1a1a3a;
            border-color: #3498db;
        }
        
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(46, 204, 113, 0.15);
            border: 1px solid rgba(46, 204, 113, 0.3);
            color: #2ecc71;
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }
        
        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .admin-card {
            background: #12121f;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #1a1a2a;
        }
        
        .admin-card h2 {
            font-size: 16px;
            color: #e94560;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #1a1a2a;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #1a1a2a;
        }
        
        .admin-table th {
            color: #888;
            font-weight: 500;
            font-size: 12px;
        }
        
        .admin-table tr:hover {
            background: #0d0d1a;
        }
        
        .btn-small {
            padding: 3px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.3s;
        }
        
        .btn-success {
            background: #27ae60;
            color: #fff;
        }
        .btn-success:hover { background: #219a52; }
        
        .btn-danger {
            background: #e74c3c;
            color: #fff;
            padding: 6px 14px;
        }
        .btn-danger:hover { background: #c0392b; }
        
        .btn-primary {
            background: #3498db;
            color: #fff;
            padding: 6px 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-primary:hover { background: #2980b9; }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-group label {
            display: block;
            color: #aaa;
            font-size: 13px;
            margin-bottom: 4px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            background: #0d0d1a;
            border: 1px solid #1a1a2a;
            border-radius: 4px;
            color: #fff;
            font-size: 13px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .log-content {
            background: #0a0a12;
            padding: 12px;
            border-radius: 6px;
            max-height: 350px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.5;
        }
        
        .log-content pre {
            color: #d4d4d4;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .tab-btn {
            padding: 6px 14px;
            background: #0d0d1a;
            color: #888;
            border: 1px solid #1a1a2a;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 6px;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: #e94560;
            color: #fff;
            border-color: #e94560;
        }
        
        .tab-btn:hover:not(.active) {
            background: #1a1a2a;
        }
        
        .log-tabs {
            margin-bottom: 12px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            background: #0d0d1a;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
        }
        
        .stat-item .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #4CAF50;
        }
        
        .stat-item .stat-label {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        
        .text-muted {
            color: #666;
            font-size: 13px;
        }
        
        @media (max-width: 768px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>⚙️ پنل مدیریت</h1>
            <div class="admin-nav">
                <a href="terminal.php">🖥️ Terminal</a>
                <a href="editor.php">✏️ Editor</a>
                <a href="?logout=1">🚪 Logout</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="admin-grid">
            <!-- بخش IP‌های بلاک شده -->
            <div class="admin-card">
                <h2>🚫 IP‌های بلاک شده</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo count($blockedIps); ?></div>
                        <div class="stat-label">IP بلاک شده</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo MAX_LOGIN_ATTEMPTS; ?></div>
                        <div class="stat-label">حداکثر تلاش</div>
                    </div>
                </div>
                
                <?php if (empty($blockedIps)): ?>
                    <p class="text-muted">✅ هیچ IP بلاک شده‌ای وجود ندارد.</p>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>IP</th>
                                <th>زمان بلاک</th>
                                <th>زمان باقی‌مانده</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blockedIps as $ip => $data): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($ip); ?></code></td>
                                    <td><?php echo date(DATETIME_FORMAT, $data['time']); ?></td>
                                    <td><?php echo gmdate('H:i:s', $data['time_remaining']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="unblock_ip">
                                            <input type="hidden" name="ip" value="<?php echo htmlspecialchars($ip); ?>">
                                            <button type="submit" class="btn-small btn-success">آزاد کردن</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <form method="POST">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="btn-danger" onclick="return confirm('آیا مطمئن هستید؟')">🗑️ پاک کردن همه</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- بخش تغییر رمز عبور -->
            <div class="admin-card">
                <h2>🔑 تغییر رمز عبور</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label for="new_password">رمز عبور جدید (حداقل ۸ کاراکتر)</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                    </div>
                    <button type="submit" class="btn-primary">تغییر رمز عبور</button>
                </form>
                <hr style="border-color: #1a1a2a; margin: 15px 0;">
                <h2>🗑️ مدیریت لاگ‌ها</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="clear_logs">
                    <div class="form-group">
                        <label for="log_type">نوع لاگ</label>
                        <select name="log_type" id="log_type">
                            <option value="all">همه لاگ‌ها</option>
                            <option value="auth">لاگ احراز هویت</option>
                            <option value="commands">لاگ دستورات</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-danger" onclick="return confirm('آیا مطمئن هستید؟')">🗑️ پاک کردن لاگ‌ها</button>
                </form>
            </div>
        </div>
        
        <!-- بخش اطلاعات سیستم -->
        <div class="admin-grid">
            <div class="admin-card">
                <h2>💻 اطلاعات سیستم</h2>
                <table class="admin-table">
                    <tr><td style="color:#888;">سیستمعامل</td><td><?php echo htmlspecialchars($systemInfo['os']); ?></td></tr>
                    <tr><td style="color:#888;">نسخه PHP</td><td><?php echo htmlspecialchars($systemInfo['php_version']); ?></td></tr>
                    <tr><td style="color:#888;">SAPI</td><td><?php echo htmlspecialchars($systemInfo['php_sapi']); ?></td></tr>
                    <tr><td style="color:#888;">کاربر</td><td><?php echo htmlspecialchars($systemInfo['current_user']); ?></td></tr>
                    <tr><td style="color:#888;">محدودیت حافظه</td><td><?php echo htmlspecialchars($systemInfo['memory_limit']); ?></td></tr>
                    <tr><td style="color:#888;">زمان اجرا</td><td><?php echo htmlspecialchars($systemInfo['max_execution_time']); ?> ثانیه</td></tr>
                </table>
            </div>
            
            <div class="admin-card">
                <h2>🔒 وضعیت امنیتی</h2>
                <table class="admin-table">
                    <tr><td style="color:#888;">حداکثر تلاش</td><td><?php echo $securityStatus['max_login_attempts']; ?></td></tr>
                    <tr><td style="color:#888;">مدت بلاک</td><td><?php echo $securityStatus['block_duration']; ?></td></tr>
                    <tr><td style="color:#888;">انقضای نشست</td><td><?php echo $securityStatus['session_timeout']; ?></td></tr>
                    <tr><td style="color:#888;">بررسی IP</td><td><?php echo $securityStatus['check_session_ip'] ? '✅ فعال' : '❌ غیرفعال'; ?></td></tr>
                    <tr><td style="color:#888;">لاگ دستورات</td><td><?php echo $securityStatus['command_logging'] ? '✅ فعال' : '❌ غیرفعال'; ?></td></tr>
                    <tr><td style="color:#888;">دستورات مجاز</td><td><?php echo $securityStatus['allowed_commands_count']; ?> مورد</td></tr>
                </table>
            </div>
        </div>
        
        <!-- بخش لاگ‌ها -->
        <div class="admin-card full-width">
            <h2>📋 لاگ‌های سیستم</h2>
            <div class="log-tabs">
                <button class="tab-btn active" onclick="showLog('auth')">🔐 لاگ احراز هویت</button>
                <button class="tab-btn" onclick="showLog('commands')">💻 لاگ دستورات</button>
                <button class="tab-btn" onclick="showLog('stats')">📊 آمار لاگ‌ها</button>
            </div>
            
            <div id="authLog" class="log-content">
                <pre><?php echo htmlspecialchars(implode("\n", $authLogs)); ?></pre>
            </div>
            
            <div id="commandLog" class="log-content" style="display:none;">
                <pre><?php echo htmlspecialchars(implode("\n", $commandLogs)); ?></pre>
            </div>
            
            <div id="statsLog" class="log-content" style="display:none;">
                <pre><?php echo json_encode($logStats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
            </div>
        </div>
    </div>
    
    <script>
        function showLog(type) {
            document.querySelectorAll('.log-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            if (type === 'auth') {
                document.getElementById('authLog').style.display = 'block';
                document.querySelector('.tab-btn:first-child').classList.add('active');
            } else if (type === 'commands') {
                document.getElementById('commandLog').style.display = 'block';
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
            } else {
                document.getElementById('statsLog').style.display = 'block';
                document.querySelector('.tab-btn:last-child').classList.add('active');
            }
        }
    </script>
</body>
</html>
<?php
// پردازش خروج
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: index.php');
    exit;
}
// ⚠️ هیچ فاصله یا کاراکتری بعد از این خط وجود نداشته باشد!
?>