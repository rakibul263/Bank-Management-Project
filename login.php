<?php
require_once 'config.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $conn->prepare("SELECT id, password, is_approved FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if account is approved
            if ($user['is_approved'] === 'approved') {
                $_SESSION['user_id'] = $user['id'];
                header('Location: dashboard.php');
                exit();
            } elseif ($user['is_approved'] === 'pending') {
                $error = 'Your account is pending approval by the administrator. Please try again later.';
            } elseif ($user['is_approved'] === 'rejected') {
                $error = 'Your account registration has been rejected. Please contact the administrator.';
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3a5a9d;
            --primary-dark: #284888;
            --primary-gradient: linear-gradient(135deg, #3a5a9d 0%, #1e3c72 100%);
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --bg-gradient: linear-gradient(135deg, #f5f7fa 0%, #e4ecf7 100%);
            --card-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
            --input-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            --card-border: rgba(255, 255, 255, 0.3);
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
        }

        body {
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
            overflow-x: hidden;
            color: var(--text-primary);
        }

        /* Enhanced background animation */
        body::before, body::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            opacity: 0.2;
            filter: blur(100px);
            animation: floatBubble 20s infinite;
            z-index: -1;
        }

        body::before {
            background: var(--primary-color);
            top: -150px;
            left: -150px;
        }

        body::after {
            background: var(--success-color);
            bottom: -150px;
            right: -150px;
            animation-delay: -10s;
        }

        @keyframes floatBubble {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(50px, 50px) scale(1.1); }
            50% { transform: translate(0, 100px) scale(1); }
            75% { transform: translate(-50px, 50px) scale(0.9); }
        }

        .login-container {
            max-width: 420px;
            margin: 60px auto 40px;
            padding: 2.5rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1;
            border: 1px solid var(--card-border);
            transform: translateY(0);
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .login-header h1 {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            display: inline-block;
        }

        .login-header h1::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 4px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }

        .login-header p {
            color: var(--secondary-color);
            font-size: 1.1rem;
            margin-bottom: 0;
            animation: fadeIn 1s ease-out;
        }

        .login-form .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .login-form .input-group {
            margin-bottom: 1.5rem;
            box-shadow: var(--input-shadow);
            border-radius: 12px;
            transition: all 0.3s ease;
            animation: slideIn 0.6s ease-out;
            animation-fill-mode: both;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .login-form .input-group:nth-child(2) {
            animation-delay: 0.1s;
        }

        .login-form .input-group:focus-within {
            box-shadow: 0 0 0 3px rgba(58, 90, 157, 0.15);
            transform: translateY(-2px);
        }

        .login-form .input-group-text {
            background: white;
            border: 1px solid #e0e6ed;
            border-right: none;
            border-radius: 12px 0 0 12px;
            padding: 0.75rem 1rem;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .login-form .form-control {
            border: 1px solid #e0e6ed;
            border-left: none;
            border-radius: 0 12px 12px 0;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        .login-form .form-control:focus {
            box-shadow: none;
            border-color: #e0e6ed;
        }

        .login-form .form-control:focus + .input-group-text {
            border-color: var(--primary-color);
            color: var(--primary-dark);
        }

        .form-check {
            margin: 1rem 0;
            animation: fadeIn 0.8s ease-out;
            animation-delay: 0.2s;
            animation-fill-mode: both;
        }

        .form-check-input {
            border-color: var(--primary-color);
            width: 1.1rem;
            height: 1.1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            animation: checkmark 0.3s ease-out;
        }

        @keyframes checkmark {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .form-check-label {
            color: var(--secondary-color);
            font-size: 0.95rem;
            padding-left: 0.5rem;
            cursor: pointer;
        }

        .login-btn {
            width: 100%;
            padding: 0.8rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            background: var(--primary-gradient);
            border: none;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            animation: fadeIn 0.8s ease-out;
            animation-delay: 0.3s;
            animation-fill-mode: both;
            box-shadow: 0 4px 15px rgba(58, 90, 157, 0.3);
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            transition: 0.5s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(58, 90, 157, 0.4);
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn i {
            margin-right: 8px;
            transition: transform 0.3s ease;
        }

        .login-btn:hover i {
            transform: translateX(3px);
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0,0,0,0.1);
            animation: fadeIn 0.8s ease-out;
            animation-delay: 0.4s;
            animation-fill-mode: both;
        }

        .register-link p {
            color: var(--secondary-color);
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        .register-link a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .register-link a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--primary-color);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
        }

        .register-link a:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            animation: shake 0.5s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .alert-danger {
            background: #fff5f5;
            color: #e74a3b;
            border-left: 4px solid #e74a3b;
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* Bank icon and brand styling */
        .bank-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .bank-logo {
            width: 60px;
            height: 60px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            box-shadow: 0 5px 15px rgba(58, 90, 157, 0.3);
            margin-right: 1rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(58, 90, 157, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(58, 90, 157, 0); }
            100% { box-shadow: 0 0 0 0 rgba(58, 90, 157, 0); }
        }

        /* Mobile responsiveness improvements */
        @media (max-width: 576px) {
            .login-container {
                margin: 40px 20px;
                padding: 2rem;
            }

            .login-header h1 {
                font-size: 1.8rem;
            }

            .login-header p {
                font-size: 1rem;
            }

            body::before, body::after {
                width: 200px;
                height: 200px;
            }

            .bank-logo {
                width: 50px;
                height: 50px;
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="bank-brand">
                <div class="bank-logo">
                    <i class="bi bi-bank"></i>
                </div>
            </div>
            <div class="login-header">
                <h1><?php echo SITE_NAME; ?></h1>
                <p>Welcome back! Please login to your account</p>
            </div>
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <div class="login-form">
                <form method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" 
                                placeholder="Enter your email" required 
                                autocomplete="email">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                placeholder="Enter your password" required 
                                autocomplete="current-password">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary login-btn">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Log In
                    </button>
                </form>
            </div>
            <div class="register-link">
                <p>Don't have an account? <a href="register.php">Register now</a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 