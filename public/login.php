<?php
session_start();
include '../config/config.php';

$message = "";

if(isset($_POST['login'])){

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s",$username);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows > 0){

        $user = $result->fetch_assoc();

        if(password_verify($password,$user['password'])){

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            header("Location: ../public/admin/dashboard.php");
            exit();

        }else{
            $message = "Invalid Password";
        }

    }else{
        $message = "User not found";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ST URIEL ACADEMY LMS</title>
<link rel="stylesheet" href="../public/assests/styles/style.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="particles-container" id="particles"></div>

<div class="container">

    <div class="login-wrapper">

        <!-- Left Side - School Branding -->
        <div class="branding-side">
            <div class="branding-content">
                <div class="logo-container">
                    <div class="logo-ring">
                        <div class="logo-inner">
                            <img src="../public/assests/images/logo.jpg" alt ="logo">
                            <div class="logo-stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <h1 class="school-name">ST. URIEL ACADEMY<br><span>OF TAGUIG CITY, INC.</span></h1>
                <p class="school-motto">"Moving Towards Excellence and Quality Education"</p>

                <div class="school-info">
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Orchids St. SAMAMA 2, Napindan, Taguig City</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Established 2005</span>
                    </div>
                </div>

                <div class="programs-preview">
                    <div class="program-tag"><i class="fas fa-child"></i> Preschool</div>
                    <div class="program-tag"><i class="fas fa-school"></i> Elementary</div>
                    <div class="program-tag"><i class="fas fa-graduation-cap"></i> Junior High</div>
                    <div class="program-tag"><i class="fas fa-university"></i> Senior High</div>
                </div>
            </div>

            <div class="branding-decoration">
                <div class="deco-circle"></div>
                <div class="deco-circle"></div>
                <div class="deco-circle"></div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-side">
            <div class="login-card">

                <div class="login-header">
                    <div class="mobile-logo">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h2>Welcome Back!</h2>
                    <p>Sign in to your learning portal</p>
                </div>

                <?php if($message != ""): ?>
                    <div class="alert alert-shake">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="login-form">

                    <div class="input-group">
                        <label><i class="fas fa-user"></i> Username</label>
                        <input type="text" name="username" placeholder="Enter your username" required>
                    </div>

                    <div class="input-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" placeholder="Enter your password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye" id="eye-icon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember">
                            <span class="checkmark"></span>
                            Remember me
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" name="login" class="login-btn">
                        <span>Sign In</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>

                </form>

                <div class="login-footer">
                    <p>Need help? Contact us</p>
                    <div class="contact-numbers">
                        <a href="tel:09566354765"><i class="fas fa-phone"></i> 0956-635-4765</a>
                        <a href="tel:09213718889"><i class="fas fa-phone"></i> 0921-371-8889</a>
                    </div>
                </div>

            </div>
        </div>

    </div>

</div>

<script>
// Floating particles animation
function createParticles() {
    const container = document.getElementById('particles');
    const colors = ['#1B5E20', '#2E7D32', '#FDD835', '#FFEB3B', '#4CAF50'];

    for (let i = 0; i < 30; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.top = Math.random() * 100 + '%';
        particle.style.width = Math.random() * 8 + 4 + 'px';
        particle.style.height = particle.style.width;
        particle.style.background = colors[Math.floor(Math.random() * colors.length)];
        particle.style.animationDelay = Math.random() * 15 + 's';
        particle.style.animationDuration = Math.random() * 10 + 10 + 's';
        container.appendChild(particle);
    }
}

createParticles();

// Password toggle
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

// Input focus effects
document.querySelectorAll('.input-group input').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });
    input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
    });
});
</script>

</body>
</html>