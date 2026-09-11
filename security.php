<?php
// security.php - سیستم مدیریت امنیت و IP Blocking
// ⚠️ توجه: هیچ فاصله یا کاراکتری قبل از این خط وجود نداشته باشد!

/**
 * کلاس SecurityManager - مدیریت امنیت سیستم
 * 
 * ویژگی‌ها:
 * - بلاک کردن IP پس از تلاش‌های ناموفق
 * - مدیریت لیست IP‌های بلاک شده
 * - بررسی خودکار انقضای بلاک
 * - لاگ‌گیری فعالیت‌های امنیتی
 */
class SecurityManager {
    /**
     * @var string مسیر فایل ذخیره IP‌های بلاک شده
     */
    private $blockedIpsFile;
    
    /**
     * @var int حداکثر تعداد تلاش‌های مجاز
     */
    private $maxAttempts;
    
    /**
     * @var int مدت زمان بلاک به ثانیه
     */
    private $blockDuration;
    
    /**
     * @var string مسیر فایل ذخیره تلاش‌های ناموفق
     */
    private $attemptsFile;
    
    /**
     * سازنده کلاس - مقداردهی اولیه
     */
    public function __construct() {
        $this->blockedIpsFile = LOGS_PATH . '/blocked_ips.log';
        $this->attemptsFile = LOGS_PATH . '/attempts.json';
        $this->maxAttempts = MAX_LOGIN_ATTEMPTS;
        $this->blockDuration = BLOCK_DURATION;
        
        $this->initFiles();
    }
    
    /**
     * مقداردهی اولیه فایل‌های مورد نیاز
     */
    private function initFiles() {
        // فایل IP‌های بلاک شده
        if (!file_exists($this->blockedIpsFile)) {
            file_put_contents($this->blockedIpsFile, '');
            chmod($this->blockedIpsFile, 0644);
        }
        
        // فایل تلاش‌های ناموفق
        if (!file_exists($this->attemptsFile)) {
            file_put_contents($this->attemptsFile, json_encode([]));
            chmod($this->attemptsFile, 0644);
        }
    }
    
    /**
     * بررسی آیا IP فعلی بلاک شده است؟
     * 
     * @param string $ip آدرس IP
     * @return bool وضعیت بلاک
     */
    public function isIpBlocked($ip) {
        $blockedIps = $this->getBlockedIps();
        
        if (isset($blockedIps[$ip])) {
            $blockTime = $blockedIps[$ip];
            $currentTime = time();
            
            // اگر زمان بلاک تمام شده، IP را آزاد کن
            if (($currentTime - $blockTime) > $this->blockDuration) {
                $this->unblockIp($ip);
                Logger::log('UNBLOCK', "IP {$ip} automatically unblocked after timeout");
                return false;
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * بلاک کردن یک IP
     * 
     * @param string $ip آدرس IP
     * @param string $reason دلیل بلاک (اختیاری)
     * @return bool نتیجه عملیات
     */
    public function blockIp($ip, $reason = 'Multiple failed attempts') {
        $blockedIps = $this->getBlockedIps();
        
        // اگر قبلاً بلاک نشده باشد
        if (!isset($blockedIps[$ip])) {
            $blockedIps[$ip] = [
                'time' => time(),
                'reason' => $reason,
                'attempts' => $this->maxAttempts
            ];
            $this->saveBlockedIps($blockedIps);
            
            // لاگ ثبت
            Logger::log('BLOCK', "IP {$ip} blocked. Reason: {$reason}");
            return true;
        }
        
        return false;
    }
    
    /**
     * آزاد کردن یک IP از حالت بلاک
     * 
     * @param string $ip آدرس IP
     * @return bool نتیجه عملیات
     */
    public function unblockIp($ip) {
        $blockedIps = $this->getBlockedIps();
        
        if (isset($blockedIps[$ip])) {
            unset($blockedIps[$ip]);
            $this->saveBlockedIps($blockedIps);
            
            // لاگ ثبت
            Logger::log('UNBLOCK', "IP {$ip} unblocked manually");
            return true;
        }
        
        return false;
    }
    
    /**
     * دریافت لیست IP‌های بلاک شده
     * 
     * @return array لیست IP‌های بلاک شده
     */
    public function getBlockedIps() {
        if (!file_exists($this->blockedIpsFile)) {
            return [];
        }
        
        $content = file_get_contents($this->blockedIpsFile);
        if (empty($content)) {
            return [];
        }
        
        // فرمت: IP:TIMESTAMP:REASON
        $lines = explode("\n", trim($content));
        $blockedIps = [];
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            $parts = explode(':', $line, 3);
            if (count($parts) >= 2) {
                $ip = trim($parts[0]);
                $time = (int)trim($parts[1]);
                $reason = isset($parts[2]) ? trim($parts[2]) : 'Unknown';
                
                $blockedIps[$ip] = [
                    'time' => $time,
                    'reason' => $reason,
                    'time_remaining' => $this->getRemainingTime($time)
                ];
            }
        }
        
        return $blockedIps;
    }
    
    /**
     * ذخیره لیست IP‌های بلاک شده
     * 
     * @param array $blockedIps لیست IP‌های بلاک شده
     */
    private function saveBlockedIps($blockedIps) {
        $content = '';
        foreach ($blockedIps as $ip => $data) {
            if (is_array($data)) {
                $content .= $ip . ':' . $data['time'] . ':' . $data['reason'] . "\n";
            } else {
                // برای سازگاری با نسخه قبلی
                $content .= $ip . ':' . $data . ':' . 'Unknown' . "\n";
            }
        }
        file_put_contents($this->blockedIpsFile, trim($content));
        chmod($this->blockedIpsFile, 0644);
    }
    
    /**
     * محاسبه زمان باقی‌مانده از بلاک
     * 
     * @param int $blockTime زمان شروع بلاک
     * @return int زمان باقی‌مانده به ثانیه
     */
    private function getRemainingTime($blockTime) {
        $elapsed = time() - $blockTime;
        $remaining = $this->blockDuration - $elapsed;
        return max(0, $remaining);
    }
    
    /**
     * پاک کردن همه IP‌های بلاک شده
     * 
     * @return bool نتیجه عملیات
     */
    public function clearAllBlockedIps() {
        file_put_contents($this->blockedIpsFile, '');
        chmod($this->blockedIpsFile, 0644);
        
        Logger::log('CLEAR_BLOCKS', 'All blocked IPs cleared');
        return true;
    }
    
    /**
     * دریافت آمار امنیتی
     * 
     * @return array آمار امنیتی
     */
    public function getSecurityStats() {
        $blockedIps = $this->getBlockedIps();
        $attempts = $this->getFailedAttempts();
        
        return [
            'total_blocked' => count($blockedIps),
            'blocked_ips' => array_keys($blockedIps),
            'total_attempts' => count($attempts),
            'max_attempts' => $this->maxAttempts,
            'block_duration' => $this->blockDuration,
            'current_time' => time()
        ];
    }
    
    /**
     * دریافت لیست تلاش‌های ناموفق
     * 
     * @return array لیست تلاش‌ها
     */
    private function getFailedAttempts() {
        if (!file_exists($this->attemptsFile)) {
            return [];
        }
        
        $content = file_get_contents($this->attemptsFile);
        return json_decode($content, true) ?: [];
    }
}

// ⚠️ هیچ فاصله یا کاراکتری بعد از این خط وجود نداشته باشد!
?>