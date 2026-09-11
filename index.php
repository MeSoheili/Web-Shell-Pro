<?php
// index.php - صفحه اصلی ورود به سیستم
// ⚠️ توجه: هیچ فاصله یا کاراکتری قبل از این خط وجود نداشته باشد!

require_once 'auth.php';

// اگر کاربر قبلاً وارد شده، به ترمینال هدایت شود
if ($auth->isAuthenticated()) {
    header('Location: terminal.php');
    exit;
}

$error = '';
$success = '';

// پردازش فرم ورود
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'لطفاً تمام فیلدها را پر کنید.';
    } else {
        $result = $auth->login($username, $password);
        if ($result['success']) {
            header('Location: terminal.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Shell Pro - ورود</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        
        .login-box {
            background: rgba(22, 33, 62, 0.95);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .login-header .icon {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
        }
        
        .login-header h1 {
            color: #fff;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .login-header .subtitle {
            color: #888;
            font-size: 14px;
            font-weight: 300;
        }
        
        .login-header .version {
            display: inline-block;
            background: rgba(233, 69, 96, 0.2);
            color: #e94560;
            font-size: 11px;
            padding: 2px 12px;
            border-radius: 12px;
            margin-top: 8px;
        }
        
        .form-group {
            margin-bottom: 22px;
        }
        
        .form-group label {
            display: block;
            color: #ccc;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
        }
        
        .form-group input {
            width: 100%;
            padding: 13px 16px;
            background: rgba(15, 52, 96, 0.6);
            border: 2px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #e94560;
            box-shadow: 0 0 20px rgba(233, 69, 96, 0.15);
        }
        
        .form-group input::placeholder {
            color: #666;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }
        
        .alert-success {
            background: rgba(46, 204, 113, 0.15);
            border: 1px solid rgba(46, 204, 113, 0.3);
            color: #2ecc71;
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #e94560, #c73652);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(233, 69, 96, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 22px;
            color: #666;
            font-size: 12px;
        }
        
        .login-footer .security-badge {
            display: inline-block;
            color: #4CAF50;
            font-size: 11px;
            padding: 4px 12px;
            border: 1px solid rgba(76, 175, 80, 0.2);
            border-radius: 20px;
            background: rgba(76, 175, 80, 0.05);
        }
        
        .login-footer .security-badge:before {
            content: "🔒 ";
        }
        
        @media (max-width: 480px) {
            .login-box {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <span class="icon">🖥️</span>
                <h1>Web Shell Pro</h1>
                <p class="subtitle">سیستم مدیریت خط فرمان پیشرفته</p>
                <span class="version">v2.0.0</span>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">👤 نام کاربری</label>
                    <input type="text" id="username" name="username" 
                           placeholder="نام کاربری خود را وارد کنید" 
                           required autofocus
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">🔑 رمز عبور</label>
                    <input type="password" id="password" name="password" 
                           placeholder="رمز عبور خود را وارد کنید" required>
                </div>
                
                <button type="submit" class="btn-primary">🚀 ورود به سیستم</button>
            </form>
            
            <div class="login-footer">
                <span class="security-badge">امنیت با رمزنگاری BCRYPT</span>
                <br>
                <span style="color: #555; font-size: 11px; margin-top: 8px; display: inline-block;">
                    ورود به سیستم با IP شما لاگ می‌شود
                </span>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// ⚠️ هیچ فاصله یا کاراکتری بعد از این خط وجود نداشته باشد!
?>