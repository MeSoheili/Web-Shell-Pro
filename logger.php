<?php
// logger.php - سیستم لاگ‌گیری پیشرفته
// ⚠️ توجه: هیچ فاصله یا کاراکتری قبل از این خط وجود نداشته باشد!

/**
 * کلاس Logger - مدیریت لاگ‌گیری سیستم
 * 
 * ویژگی‌ها:
 * - سطوح مختلف لاگ (DEBUG, INFO, WARNING, ERROR, CRITICAL)
 * - لاگ احراز هویت
 * - لاگ دستورات
 * - جستجو در لاگ‌ها
 * - مدیریت حجم لاگ‌ها
 */
class Logger {
    /**
     * سطوح لاگ
     */
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';
    
    /**
     * ثبت لاگ با سطوح مختلف
     * 
     * @param string $level سطح لاگ
     * @param string $message پیام لاگ
     * @param mixed $data داده‌های اضافی (اختیاری)
     * @return bool نتیجه عملیات
     */
    public static function log($level, $message, $data = null) {
        // اگر لاگ‌گیری غیرفعال است، خروج
        if (!ENABLE_COMMAND_LOGGING && $level !== self::CRITICAL) {
            return false;
        }
        
        $logFile = LOGS_PATH . '/auth.log';
        $timestamp = date(DATETIME_FORMAT);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $user = isset($_SESSION['username']) ? $_SESSION['username'] : 'GUEST';
        
        // ساخت لاگ
        $logEntry = "[{$timestamp}] [{$level}] [USER: {$user}] [IP: {$ip}] [UA: {$userAgent}] {$message}";
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $logEntry .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                $logEntry .= " | Data: {$data}";
            }
        }
        
        $logEntry .= PHP_EOL;
        
        // ذخیره در فایل
        try {
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            chmod($logFile, 0644);
            
            // اگر خطای بحرانی است، در error_log هم ثبت کن
            if ($level === self::CRITICAL) {
                error_log("CRITICAL: {$message}");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to write log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ثبت لاگ دستورات
     * 
     * @param string $command دستور اجرا شده
     * @param string $output خروجی دستور
     * @param int $returnCode کد بازگشت
     * @return bool نتیجه عملیات
     */
    public static function logCommand($command, $output, $returnCode) {
        if (!ENABLE_COMMAND_LOGGING) {
            return false;
        }
        
        $logFile = LOGS_PATH . '/commands.log';
        $timestamp = date(DATETIME_FORMAT);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $user = isset($_SESSION['username']) ? $_SESSION['username'] : 'UNKNOWN';
        
        // محدود کردن حجم خروجی
        $outputPreview = substr($output, 0, 500);
        if (strlen($output) > 500) {
            $outputPreview .= '... (truncated)';
        }
        
        $logEntry = sprintf(
            "[%s] [USER: %s] [IP: %s] [CMD: %s] [RETURN: %d]",
            $timestamp,
            $user,
            $ip,
            $command,
            $returnCode
        ) . PHP_EOL;
        
        $logEntry .= "OUTPUT: " . $outputPreview . PHP_EOL;
        $logEntry .= str_repeat('-', 80) . PHP_EOL;
        
        try {
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            chmod($logFile, 0644);
            return true;
        } catch (Exception $e) {
            error_log("Failed to write command log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * خواندن لاگ‌ها
     * 
     * @param string $type نوع لاگ ('auth' یا 'commands')
     * @param int $lines تعداد خطوط
     * @param string $filter فیلتر متن (اختیاری)
     * @return array خطوط لاگ
     */
    public static function getLogs($type = 'auth', $lines = 100, $filter = null) {
        $logFile = LOGS_PATH . '/' . $type . '.log';
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        try {
            $content = file_get_contents($logFile);
            $logLines = array_filter(explode(PHP_EOL, $content));
            
            // اعمال فیلتر
            if ($filter !== null && !empty($filter)) {
                $logLines = array_filter($logLines, function($line) use ($filter) {
                    return stripos($line, $filter) !== false;
                });
            }
            
            // برگرداندن آخرین خطوط
            return array_slice($logLines, -$lines);
        } catch (Exception $e) {
            error_log("Failed to read logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جستجو در لاگ‌ها
     * 
     * @param string $type نوع لاگ ('auth' یا 'commands')
     * @param string $keyword کلمه کلیدی
     * @param int $maxLines حداکثر تعداد خطوط
     * @return array نتایج جستجو
     */
    public static function searchLogs($type, $keyword, $maxLines = 100) {
        return self::getLogs($type, $maxLines, $keyword);
    }
    
    /**
     * پاک کردن لاگ‌ها
     * 
     * @param string $type نوع لاگ ('auth' یا 'commands' یا 'all')
     * @return bool نتیجه عملیات
     */
    public static function clearLogs($type = 'all') {
        try {
            if ($type === 'all' || $type === 'auth') {
                file_put_contents(LOGS_PATH . '/auth.log', '');
                chmod(LOGS_PATH . '/auth.log', 0644);
            }
            
            if ($type === 'all' || $type === 'commands') {
                file_put_contents(LOGS_PATH . '/commands.log', '');
                chmod(LOGS_PATH . '/commands.log', 0644);
            }
            
            self::log(self::INFO, "Logs cleared: {$type}");
            return true;
        } catch (Exception $e) {
            error_log("Failed to clear logs: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت آمار لاگ‌ها
     * 
     * @return array آمار لاگ‌ها
     */
    public static function getStats() {
        $stats = [];
        $logFiles = ['auth.log', 'commands.log'];
        
        foreach ($logFiles as $file) {
            $path = LOGS_PATH . '/' . $file;
            if (file_exists($path)) {
                $stats[$file] = [
                    'size' => filesize($path),
                    'lines' => count(file($path)),
                    'modified' => date(DATETIME_FORMAT, filemtime($path))
                ];
            } else {
                $stats[$file] = [
                    'size' => 0,
                    'lines' => 0,
                    'modified' => 'N/A'
                ];
            }
        }
        
        return $stats;
    }
}

// ⚠️ هیچ فاصله یا کاراکتری بعد از این خط وجود نداشته باشد!
?>