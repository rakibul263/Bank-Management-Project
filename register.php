<?php
require_once 'config.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitize_input($_POST['full_name']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name) || empty($phone)) {
        $error = 'Please fill in all required fields';
    } elseif (!validate_email($email)) {
        $error = 'Please enter a valid email address';
    } elseif (!validate_password($password)) {
        $error = 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Username or email already exists';
        } else {
            // Create user with pending approval status
            $hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
            
            try {
                // Start transaction
                $conn->beginTransaction();
                
                // Insert user with pending status
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, address, is_approved) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $address]);
                
                $user_id = $conn->lastInsertId();
                
                // Create notification for admin
                $notification_message = "New user registration: $full_name ($email) is pending approval";
                $stmt = $conn->prepare("INSERT INTO admin_notifications (type, message, user_id) VALUES ('registration', ?, ?)");
                $stmt->execute([$notification_message, $user_id]);
                
                // Commit transaction
                $conn->commit();
                
                $success = 'Registration successful! Your account is pending admin approval. You will be able to log in once approved.';
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
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
        }
        
        body {
            background: var(--bg-gradient);
            min-height: 100vh;
        }
        
        .register-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h1 {
            color: var(--primary-color);
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .register-header p {
            color: var(--secondary-color);
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(58, 90, 157, 0.25);
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            padding: 10px 0;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(58, 90, 157, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            padding: 15px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-left: 4px solid var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
        }
        
        .note-text {
            background-color: rgba(255, 193, 7, 0.1);
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-header">
                <h1><?php echo SITE_NAME; ?></h1>
                <p class="text-muted">Create your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo $success; ?>
                    <br>
                    <a href="login.php" class="mt-2 d-inline-block">Return to login page</a>
                </div>
            <?php else: ?>
                <div class="note-text">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Note:</strong> Your account will require admin approval before you can log in.
                </div>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="text-center mt-3">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 