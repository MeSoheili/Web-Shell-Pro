<?php
// شروع نشست (Session)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// بارگذاری فایل‌های مورد نیاز
require_once 'config.php';
require_once 'security.php';
require_once 'logger.php';

/**
 * کلاس Authentication - مدیریت احراز هویت کاربران
 * 
 * ویژگی‌ها:
 * - بررسی انقضای نشست
 * - محدودیت تعداد تلاش‌های ناموفق
 * - بلاک کردن IP پس از چند بار تلاش ناموفق
 * - لاگ‌گیری کامل از فعالیت‌ها
 * - خروج ایمن از سیستم
 */
class Authentication {
    /**
     * @var SecurityManager نمونه کلاس مدیریت امنیت
     */
    private $security;
    
    /**
     * @var int تعداد تلاش‌های ناموفق کاربر در این نشست
     */
    private $attempts;
    
    /**
     * سازنده کلاس - مقداردهی اولیه و بررسی نشست
     */
    public function __construct() {
        $this->security = new SecurityManager();
        $this->attempts = 0;
        
        // بررسی انقضای نشست
        $this->checkSessionTimeout();
        
        // بررسی وضعیت لاگین
        $this->validateSession();
    }
    
    /**
     * بررسی انقضای نشست
     * اگر نشست منقضی شده باشد، کاربر را خارج می‌کند
     */
    private function checkSessionTimeout() {
        if (isset($_SESSION['last_activity']) && isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive > SESSION_TIMEOUT) {
                $this->logout();
                return false;
            }
        }
        
        // به‌روزرسانی زمان آخرین فعالیت
        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            $_SESSION['last_activity'] = time();
        }
        
        return true;
    }
    
    /**
     * اعتبارسنجی نشست
     * بررسی می‌کند که آیا نشست معتبر است یا خیر
     */
    private function validateSession() {
        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            // بررسی تطابق IP (امنیت بیشتر)
            if (isset($_SESSION['ip']) && $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
                // IP تغییر کرده است - احتمال دزدی نشست
                Logger::log('SECURITY', "Session IP mismatch! Expected: {$_SESSION['ip']}, Actual: {$_SERVER['REMOTE_ADDR']}");
                $this->logout();
                return false;
            }
            
            // بررسی تطابق User Agent (امنیت بیشتر)
            if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
                // User Agent تغییر کرده است - احتمال دزدی نشست
                Logger::log('SECURITY', "Session User Agent mismatch!");
                $this->logout();
                return false;
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * بررسی آیا کاربر وارد شده است؟
     * 
     * @return bool وضعیت احراز هویت
     */
    public function isAuthenticated() {
        // ابتدا اعتبارسنجی نشست را انجام بده
        if (!$this->validateSession()) {
            return false;
        }
        
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }
    
    /**
     * ورود کاربر به سیستم
     * 
     * @param string $username نام کاربری
     * @param string $password رمز عبور
     * @return array نتیجه ورود با کلیدهای success و message
     */
    public function login($username, $password) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // بررسی بلاک بودن IP
        if ($this->security->isIpBlocked($ip)) {
            Logger::log('BLOCKED', "Blocked IP {$ip} attempted login with username: {$username}");
            return [
                'success' => false,
                'message' => 'IP شما به دلیل تلاش‌های ناموفق بلاک شده است. لطفاً بعداً تلاش کنید.'
            ];
        }
        
        // بررسی خالی بودن ورودی‌ها
        if (empty($username) || empty($password)) {
            Logger::log('WARNING', "Empty login attempt from IP: {$ip}");
            return [
                'success' => false,
                'message' => 'لطفاً نام کاربری و رمز عبور را وارد کنید.'
            ];
        }
        
        // بررسی طول رمز عبور
        if (strlen($password) < 6) {
            Logger::log('WARNING', "Short password attempt from IP: {$ip}");
            return [
                'success' => false,
                'message' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.'
            ];
        }
        
        // بررسی اطلاعات ورود
        if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
            // موفقیت - تنظیم نشست
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['ip'] = $ip;
            $_SESSION['user_agent'] = $userAgent;
            $_SESSION['login_attempts'] = 0;
            
            // پاک کردن تلاش‌های ناموفق
            $this->clearFailedAttempts($ip);
            
            // لاگ موفقیت
            Logger::log('LOGIN', "User {$username} logged in successfully from IP: {$ip}");
            
            return [
                'success' => true,
                'message' => 'ورود موفق'
            ];
        } else {
            // ناموفق - افزایش تعداد تلاش‌ها
            $this->incrementFailedAttempts($ip, $username);
            
            // لاگ ناموفق
            Logger::log('FAILED', "Failed login attempt for username: {$username} from IP: {$ip}");
            
            // اگر کاربر وجود ندارد، پیام عمومی بدهیم (امنیت بهتر)
            if ($username !== ADMIN_USERNAME) {
                return [
                    'success' => false,
                    'message' => 'نام کاربری یا رمز عبور اشتباه است.'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'نام کاربری یا رمز عبور اشتباه است.'
            ];
        }
    }
    
    /**
     * افزایش تعداد تلاش‌های ناموفق برای یک IP
     * 
     * @param string $ip آدرس IP
     * @param string $username نام کاربری (برای لاگ)
     */
    private function incrementFailedAttempts($ip, $username = 'unknown') {
        $attemptsFile = LOGS_PATH . '/attempts.json';
        $attempts = [];
        
        // خواندن تلاش‌های قبلی
        if (file_exists($attemptsFile)) {
            $content = file_get_contents($attemptsFile);
            $attempts = json_decode($content, true) ?: [];
        }
        
        // به‌روزرسانی تعداد تلاش‌ها
        $currentTime = time();
        if (!isset($attempts[$ip])) {
            $attempts[$ip] = [
                'count' => 1,
                'first_attempt' => $currentTime,
                'last_attempt' => $currentTime,
                'username' => $username
            ];
        } else {
            $attempts[$ip]['count']++;
            $attempts[$ip]['last_attempt'] = $currentTime;
            $attempts[$ip]['username'] = $username;
        }
        
        // بررسی اینکه آیا تلاش‌ها در مدت زمان کوتاه انجام شده‌اند
        $timeDiff = $attempts[$ip]['last_attempt'] - $attempts[$ip]['first_attempt'];
        if ($timeDiff > 3600) { // اگر بیش از ۱ ساعت گذشته باشد، ریست کن
            $attempts[$ip]['count'] = 1;
            $attempts[$ip]['first_attempt'] = $currentTime;
        }
        
        // اگر تعداد تلاش‌ها از حد مجاز بیشتر شد، IP را بلاک کن
        if ($attempts[$ip]['count'] >= MAX_LOGIN_ATTEMPTS) {
            $this->security->blockIp($ip);
            Logger::log('BLOCK', "IP {$ip} blocked after {$attempts[$ip]['count']} failed attempts");
            unset($attempts[$ip]); // پاک کردن از لیست تلاش‌ها
        }
        
        // ذخیره تلاش‌ها
        file_put_contents($attemptsFile, json_encode($attempts, JSON_PRETTY_PRINT));
        chmod($attemptsFile, 0644);
    }
    
    /**
     * پاک کردن تلاش‌های ناموفق برای یک IP
     * 
     * @param string $ip آدرس IP
     */
    private function clearFailedAttempts($ip) {
        $attemptsFile = LOGS_PATH . '/attempts.json';
        if (file_exists($attemptsFile)) {
            $content = file_get_contents($attemptsFile);
            $attempts = json_decode($content, true) ?: [];
            if (isset($attempts[$ip])) {
                unset($attempts[$ip]);
                file_put_contents($attemptsFile, json_encode($attempts, JSON_PRETTY_PRINT));
            }
        }
    }
    
    /**
     * خروج از سیستم
     * پاک کردن تمام داده‌های نشست
     */
    public function logout() {
        // لاگ خروج
        if (isset($_SESSION['username'])) {
            Logger::log('LOGOUT', "User {$_SESSION['username']} logged out from IP: {$_SERVER['REMOTE_ADDR']}");
        }
        
        // پاک کردن نشست
        $_SESSION = array();
        
        // اگر کوکی نشست وجود دارد، آن را حذف کن
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // نابودی نشست
        session_destroy();
    }
    
    /**
     * بررسی اینکه آیا دستور در لیست مجاز است؟
     * 
     * @param string $command دستور مورد نظر
     * @return bool آیا دستور مجاز است؟
     */
    public function isCommandAllowed($command) {
        if (empty($command)) {
            return false;
        }
        
        $baseCommand = explode(' ', trim($command))[0];
        $baseCommand = strtolower($baseCommand);
        
        // بررسی در لیست دستورات مجاز
        foreach (ALLOWED_COMMANDS as $allowedCmd) {
            if (stripos($baseCommand, $allowedCmd) !== false && stripos($baseCommand, $allowedCmd) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * دریافت اطلاعات کاربر جاری
     * 
     * @return array|null اطلاعات کاربر یا null در صورت عدم ورود
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'username' => $_SESSION['username'] ?? null,
            'login_time' => $_SESSION['login_time'] ?? null,
            'ip' => $_SESSION['ip'] ?? null,
            'user_agent' => $_SESSION['user_agent'] ?? null
        ];
    }
    
    /**
     * تازه‌سازی نشست (برای جلوگیری از انقضا)
     */
    public function refreshSession() {
        if ($this->isAuthenticated()) {
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    
    /**
     * بررسی وضعیت امنیتی نشست
     * 
     * @return array وضعیت امنیتی
     */
    public function getSecurityStatus() {
        return [
            'session_active' => $this->isAuthenticated(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_timeout' => SESSION_TIMEOUT,
            'max_attempts' => MAX_LOGIN_ATTEMPTS,
            'block_duration' => BLOCK_DURATION
        ];
    }
}

// ============================================
// نمونه‌سازی از کلاس برای استفاده در سایر فایل‌ها
// ============================================

/**
 * @var Authentication $auth نمونه کلاس احراز هویت
 */
$auth = new Authentication();

// اگر کاربر وارد شده باشد، نشست را تازه‌سازی کن
if ($auth->isAuthenticated()) {
    $auth->refreshSession();
}

// ============================================
// پایان فایل - هیچ فاصله یا کاراکتری بعد از این خط نباشد!
// ============================================
?>