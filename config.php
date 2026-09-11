<?php
// ============================================
// تشخیص سیستمعامل
// ============================================
$os = strtoupper(substr(PHP_OS, 0, 3));
define('IS_WINDOWS', ($os === 'WIN'));
define('IS_LINUX', ($os === 'LIN') || ($os === 'DAR') || ($os === 'FREE') || ($os === 'NET'));

// ============================================
// تنظیمات دستورات مجاز بر اساس سیستمعامل
// ============================================

/**
 * لیست دستورات مجاز در ویندوز
 * این دستورات تنها دستوراتی هستند که کاربر می‌تواند اجرا کند
 */
if (IS_WINDOWS) {
    define('ALLOWED_COMMANDS', [
        // دستورات مدیریت فایل
        'dir', 'cd', 'echo', 'type', 'copy', 'move', 'mkdir', 'rmdir', 'del', 'ren', 'rename',
        // دستورات سیستم
        'ver', 'date', 'time', 'cls', 'color', 'title', 'prompt', 'whoami', 'hostname',
        // دستورات شبکه
        'ping', 'ipconfig', 'netstat', 'tracert', 'nslookup', 'arp', 'route',
        // دستورات مدیریت فرآیند
        'tasklist', 'taskkill', 'systeminfo', 'driverquery', 'sc', 'net',
        // دستورات PHP و ابزارهای توسعه
        'php', 'composer', 'node', 'npm', 'python', 'perl', 'ruby', 'java', 'javac',
        // دستورات فشرده‌سازی
        'tar', 'zip', 'unzip', 'gzip', 'gunzip',
        // سایر دستورات مفید
        'find', 'findstr', 'sort', 'more', 'tree', 'xcopy', 'robocopy', 'diskpart', 'chkdsk',
        'sfc', 'reg', 'regedit', 'msconfig', 'taskmgr', 'calc', 'notepad', 'explorer'
    ]);
} else {
    define('ALLOWED_COMMANDS', [
        // دستورات اصلی لینوکس
        'ls', 'pwd', 'cd', 'whoami', 'hostname', 'uname', 'uptime', 'date', 'cal',
        // دستورات مدیریت فایل
        'cat', 'less', 'more', 'head', 'tail', 'grep', 'find', 'locate', 'which', 'whereis',
        'cp', 'mv', 'rm', 'mkdir', 'rmdir', 'chmod', 'chown', 'chgrp', 'ln', 'touch', 'file',
        // دستورات سیستم
        'ps', 'top', 'htop', 'free', 'df', 'du', 'mount', 'umount', 'fdisk', 'parted',
        'systemctl', 'service', 'journalctl', 'dmesg', 'lsof', 'netstat', 'ss', 'ifconfig',
        'ip', 'ping', 'curl', 'wget', 'ssh', 'scp', 'rsync',
        // دستورات مدیریت کاربران
        'useradd', 'userdel', 'usermod', 'passwd', 'groups', 'id', 'su', 'sudo',
        // دستورات PHP و ابزارهای توسعه
        'php', 'composer', 'node', 'npm', 'python', 'python3', 'perl', 'ruby', 'java', 'javac',
        'gcc', 'g++', 'make', 'cmake', 'git', 'svn',
        // دستورات فشرده‌سازی
        'tar', 'zip', 'unzip', 'gzip', 'gunzip', 'bzip2', 'xz',
        // سایر دستورات مفید
        'echo', 'printf', 'alias', 'history', 'clear', 'reset', 'env', 'export', 'source',
        'crontab', 'at', 'screen', 'tmux', 'nano', 'vim', 'vi', 'emacs'
    ]);
}

// ============================================
// تنظیمات امنیتی
// ============================================

/**
 * @var int MAX_LOGIN_ATTEMPTS حداکثر تعداد تلاش‌های ناموفق برای ورود
 */
define('MAX_LOGIN_ATTEMPTS', 3);

/**
 * @var int BLOCK_DURATION مدت زمان بلاک شدن IP به ثانیه (پیش‌فرض: 1 ساعت)
 */
define('BLOCK_DURATION', 3600);

/**
 * @var int SESSION_TIMEOUT زمان انقضای نشست به ثانیه (پیش‌فرض: 30 دقیقه)
 */
define('SESSION_TIMEOUT', 1800);

/**
 * @var int MAX_COMMAND_HISTORY حداکثر تعداد دستورات ذخیره شده در تاریخچه
 */
define('MAX_COMMAND_HISTORY', 100);

/**
 * @var bool ENABLE_COMMAND_LOGGING فعال‌سازی لاگ دستورات
 */
define('ENABLE_COMMAND_LOGGING', true);

/**
 * @var bool ENABLE_DEBUG_MODE فعال‌سازی حالت دیباگ (فقط برای توسعه)
 */
define('ENABLE_DEBUG_MODE', false);

// ============================================
// اطلاعات کاربری مدیر سیستم
// ============================================

/**
 * @var string ADMIN_USERNAME نام کاربری مدیر
 * @var string ADMIN_PASSWORD_HASH هش رمز عبور مدیر (تولید شده با password_hash)
 * 
 * برای تولید هش جدید از کد زیر استفاده کنید:
 * echo password_hash('YourStrongPassword123!', PASSWORD_BCRYPT);
 */
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', password_hash('PASSWORD :)))', PASSWORD_BCRYPT));

// ============================================
// تنظیمات مسیرها
// ============================================

/**
 * @var string BASE_PATH مسیر پایه پروژه
 * @var string LOGS_PATH مسیر ذخیره لاگ‌ها
 * @var string TEMP_PATH مسیر ذخیره فایل‌های موقت
 * @var string BACKUP_PATH مسیر ذخیره پشتیبان‌ها
 */
define('BASE_PATH', dirname(__FILE__));
define('LOGS_PATH', BASE_PATH . '/logs');
define('TEMP_PATH', BASE_PATH . '/temp');
define('BACKUP_PATH', BASE_PATH . '/backups');

// ============================================
// تنظیمات زمان و منطقه زمانی
// ============================================

/**
 * تنظیم منطقه زمانی به ایران
 * لیست مناطق زمانی معتبر: https://www.php.net/manual/en/timezones.php
 */
date_default_timezone_set('Asia/Tehran');

/**
 * @var string DATE_FORMAT فرمت نمایش تاریخ
 * @var string TIME_FORMAT فرمت نمایش زمان
 * @var string DATETIME_FORMAT فرمت نمایش تاریخ و زمان
 */
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// ============================================
// تنظیمات ظاهری و UI
// ============================================

/**
 * @var string THEME_NAME نام تم (فقط برای توسعه آینده)
 * @var bool SHOW_OS_BADGE نمایش برچسب سیستمعامل در هدر
 * @var bool SHOW_QUICK_LINKS نمایش لینک‌های سریع
 */
define('THEME_NAME', 'dark');
define('SHOW_OS_BADGE', true);
define('SHOW_QUICK_LINKS', true);

// ============================================
// تنظیمات لاگ‌گیری
// ============================================

/**
 * @var array LOG_LEVELS سطوح مختلف لاگ
 * @var string DEFAULT_LOG_LEVEL سطح پیش‌فرض لاگ
 */
define('LOG_LEVEL_DEBUG', 0);
define('LOG_LEVEL_INFO', 1);
define('LOG_LEVEL_WARNING', 2);
define('LOG_LEVEL_ERROR', 3);
define('LOG_LEVEL_CRITICAL', 4);
define('DEFAULT_LOG_LEVEL', LOG_LEVEL_INFO);

// ============================================
// تنظیمات پیشرفته امنیتی
// ============================================

/**
 * @var bool ENFORCE_HTTPS اجبار به استفاده از HTTPS
 * @var bool CHECK_SESSION_IP بررسی تطابق IP در نشست
 * @var bool CHECK_SESSION_USER_AGENT بررسی تطابق User Agent در نشست
 */
define('ENFORCE_HTTPS', false);
define('CHECK_SESSION_IP', true);
define('CHECK_SESSION_USER_AGENT', true);

// ============================================
// تنظیمات دستورات خطرناک ممنوع
// ============================================

/**
 * لیست دستوراتی که به هیچ وجه قابل اجرا نیستند
 * این دستورات حتی اگر در لیست مجاز باشند، مسدود می‌شوند
 */
define('DANGEROUS_COMMANDS', [
    // دستورات مخرب
    'rm', 'dd', 'mkfs', 'format', 'shutdown', 'reboot', 'halt', 'poweroff',
    'kill', 'killall', 'pkill', 'chmod', 'chown', 'chgrp',
    // دستورات ویندوز مخرب
    'del', 'rd', 'rmdir', 'diskpart', 'format', 'chkdsk', 'sfc',
    // دستورات خطرناک شبکه
    'nmap', 'nikto', 'sqlmap', 'hydra', 'john', 'aircrack',
    // دستورات تغییر رمز
    'passwd', 'chpasswd', 'usermod', 'userdel'
]);

// ============================================
// تنظیمات برای اجرا و عملکرد
// ============================================

/**
 * @var int MEMORY_LIMIT محدودیت حافظه (MB)
 * @var int MAX_EXECUTION_TIME حداکثر زمان اجرا (ثانیه)
 * @var int MAX_INPUT_TIME حداکثر زمان پردازش ورودی (ثانیه)
 */
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);

// تنظیمات نمایش خطاها بر اساس حالت دیباگ
if (ENABLE_DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// تنظیمات امنیتی PHP
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', ENFORCE_HTTPS ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');

// ============================================
// ایجاد پوشه‌های مورد نیاز
// ============================================

/**
 * ایجاد پوشه‌های مورد نیاز سیستم
 * اگر پوشه‌ها وجود نداشته باشند، ایجاد می‌شوند
 */
$directories = [LOGS_PATH, TEMP_PATH, BACKUP_PATH];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ایجاد فایل‌های .htaccess برای محافظت از پوشه‌ها
$htaccessContent = "Order Deny,Allow\nDeny from all\n";
foreach ([LOGS_PATH, TEMP_PATH, BACKUP_PATH] as $dir) {
    $htaccessFile = $dir . '/.htaccess';
    if (!file_exists($htaccessFile)) {
        file_put_contents($htaccessFile, $htaccessContent);
    }
}

// ============================================
// لاگ کردن اطلاعات سیستم هنگام بارگذاری
// ============================================

/**
 * ثبت اطلاعات سیستم در لاگ هنگام بارگذاری
 * فقط یک بار در طول اجرا انجام می‌شود
 */
if (!defined('SYSTEM_CHECKED')) {
    define('SYSTEM_CHECKED', true);
    
    $osName = IS_WINDOWS ? 'Windows' : 'Linux/Unix';
    $phpVersion = phpversion();
    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $currentUser = get_current_user();
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';
    
    // لاگ در error_log
    error_log("========================================");
    error_log("Web Shell Pro - System Initialization");
    error_log("========================================");
    error_log("OS: {$osName}");
    error_log("PHP Version: {$phpVersion}");
    error_log("Server: {$serverSoftware}");
    error_log("User: {$currentUser}");
    error_log("Document Root: {$documentRoot}");
    error_log("Base Path: " . BASE_PATH);
    error_log("Logs Path: " . LOGS_PATH);
    error_log("Memory Limit: " . ini_get('memory_limit'));
    error_log("Max Execution Time: " . ini_get('max_execution_time'));
    error_log("========================================");
    
    // اگر در حالت دیباگ هستیم، اطلاعات را به صورت JSON هم ذخیره کن
    if (ENABLE_DEBUG_MODE) {
        $debugInfo = [
            'timestamp' => date(DATETIME_FORMAT),
            'os' => $osName,
            'php_version' => $phpVersion,
            'server' => $serverSoftware,
            'user' => $currentUser,
            'document_root' => $documentRoot,
            'base_path' => BASE_PATH,
            'logs_path' => LOGS_PATH,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'extensions' => get_loaded_extensions(),
            'environment' => $_ENV,
            'server' => $_SERVER
        ];
        file_put_contents(LOGS_PATH . '/debug.json', json_encode($debugInfo, JSON_PRETTY_PRINT));
    }
}

// ============================================
// توابع کمکی برای دسترسی به تنظیمات
// ============================================

/**
 * بررسی اینکه آیا یک دستور در لیست خطرناک است؟
 * 
 * @param string $command دستور مورد بررسی
 * @return bool آیا دستور خطرناک است؟
 */
function isDangerousCommand($command) {
    $baseCommand = strtolower(explode(' ', trim($command))[0]);
    $dangerous = DANGEROUS_COMMANDS;
    
    foreach ($dangerous as $dangerCmd) {
        if (stripos($baseCommand, $dangerCmd) !== false && stripos($baseCommand, $dangerCmd) === 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * دریافت اطلاعات سیستم
 * 
 * @return array اطلاعات سیستم
 */
function getSystemInfo() {
    return [
        'os' => IS_WINDOWS ? 'Windows' : 'Linux/Unix',
        'os_version' => php_uname(),
        'php_version' => phpversion(),
        'php_sapi' => php_sapi_name(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'current_user' => get_current_user(),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'extensions' => get_loaded_extensions(),
        'timestamp' => date(DATETIME_FORMAT)
    ];
}

/**
 * بررسی وضعیت امنیتی سیستم
 * 
 * @return array وضعیت امنیتی
 */
function getSecurityStatus() {
    return [
        'max_login_attempts' => MAX_LOGIN_ATTEMPTS,
        'block_duration' => BLOCK_DURATION . ' seconds',
        'session_timeout' => SESSION_TIMEOUT . ' seconds',
        'enforce_https' => ENFORCE_HTTPS,
        'check_session_ip' => CHECK_SESSION_IP,
        'check_session_user_agent' => CHECK_SESSION_USER_AGENT,
        'command_logging' => ENABLE_COMMAND_LOGGING,
        'debug_mode' => ENABLE_DEBUG_MODE,
        'dangerous_commands_count' => count(DANGEROUS_COMMANDS),
        'allowed_commands_count' => count(ALLOWED_COMMANDS)
    ];
}

// ============================================
// اعمال تنظیمات پیشرفته امنیتی
// ============================================

// اگر HTTPS اجباری است و درخواست HTTP است، به HTTPS ریدایرکت کن
if (ENFORCE_HTTPS && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    if (!headers_sent()) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// ============================================
// پایان فایل - هیچ فاصله یا کاراکتری بعد از این خط نباشد!
// ============================================
?>