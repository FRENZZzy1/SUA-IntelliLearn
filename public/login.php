<?php

require_once '../config/config.php';

$message = "";

if(isset($_POST['login'])){

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows > 0){

        $user = $result->fetch_assoc();

        $passwordMatches = false;

        if(password_get_info($user['password'])['algo'] !== null){
            $passwordMatches = password_verify($password, $user['password']);
        } else {
            if($password === $user['password']){
                $passwordMatches = true;

                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $updateStmt->bind_param("si", $newHash, $user['id']);
                $updateStmt->execute();
            }
        }

        if($passwordMatches){
            $sessionToken = bin2hex(random_bytes(32));

            $tokenStmt = $conn->prepare("UPDATE users SET current_session_token=? WHERE id=?");
            $tokenStmt->bind_param("si", $sessionToken, $user['id']);
            $tokenStmt->execute();

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['session_token'] = $sessionToken;

            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } elseif ($user['role'] === 'teacher') {
                header("Location: teacher/dashboard.php");
            } else {
                header("Location: student/dashboard.php");
            }
            exit();

        } else {
            $message = "Invalid Password";
        }

    } else {
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
<style>
.forgot-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(5px);
}

.forgot-modal-overlay.active {
    display: flex;
}

.forgot-modal {
    width: 100%;
    max-width: 460px;
    background: #fff;
    border-radius: 20px;
    padding: 30px;
    position: relative;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: forgotModalIn 0.25s ease-out;
}

@keyframes forgotModalIn {
    from { opacity: 0; transform: translateY(20px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.forgot-modal-close {
    position: absolute;
    top: 15px;
    right: 17px;
    border: 0;
    background: transparent;
    color: #6c757d;
    font-size: 22px;
    cursor: pointer;
}

.forgot-modal-icon {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 18px;
    background: linear-gradient(135deg, #1B5E20, #2E7D32);
    color: #fff;
    font-size: 25px;
}

.forgot-modal h3 {
    margin: 0 0 10px;
    color: #1a1a2e;
    font-size: 23px;
}

.forgot-modal p {
    margin: 0 0 18px;
    color: #6c757d;
    font-size: 14px;
    line-height: 1.7;
}

.forgot-contact-box {
    background: #f8f9fa;
    border-left: 4px solid #2E7D32;
    border-radius: 10px;
    padding: 15px 16px;
    margin-bottom: 18px;
}

.forgot-contact-box strong {
    display: block;
    color: #1a1a2e;
    margin-bottom: 4px;
}

.forgot-contact-box span {
    color: #6c757d;
    font-size: 13px;
}

.forgot-details {
    margin: 0 0 20px 20px;
    padding: 0;
    color: #495057;
    font-size: 14px;
    line-height: 1.9;
}

.forgot-fb-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    width: 100%;
    padding: 13px 18px;
    border-radius: 11px;
    background: #1877F2;
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    transition: 0.2s ease;
}

.forgot-fb-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.forgot-modal-note {
    text-align: center;
    margin-top: 14px !important;
    margin-bottom: 0 !important;
    font-size: 12px !important;
}

@media (max-width: 480px) {
    .forgot-modal {
        padding: 25px 20px;
    }
}
</style>
</head>
<body>

<div class="particles-container" id="particles"></div>

<div class="container">

    <div class="login-wrapper">

        <div class="branding-side">
            <div class="branding-content">
                <div class="logo-container">
                    <div class="logo-ring">
                        <div class="logo-inner">
                            <img src="../public/assests/images/logo.jpg" alt="logo">
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
                        <?= htmlspecialchars($message) ?>
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
                        <a href="#" class="forgot-link" id="forgotPasswordLink">Forgot password?</a>
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

<!-- Forgot Password Instructions Modal -->
<div class="forgot-modal-overlay" id="forgotPasswordModal" role="dialog" aria-modal="true" aria-labelledby="forgotPasswordTitle">
    <div class="forgot-modal">
        <button type="button" class="forgot-modal-close" id="forgotPasswordClose" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>

        <div class="forgot-modal-icon">
            <i class="fas fa-key"></i>
        </div>

        <h3 id="forgotPasswordTitle">Forgot your password?</h3>
        <p>To reset your password, please contact the following person through Facebook.</p>

        <div class="forgot-contact-box">
            <strong>Noel a Baga</strong>
            <span>Facebook account: Jhay tee</span>
        </div>

        <p>Please include the following information in your message:</p>
        <ul class="forgot-details">
            <li>Your <strong>LRN</strong></li>
            <li>Your <strong>Full Name</strong></li>
        </ul>

        <a href="https://www.facebook.com/jhaytee011" target="_blank" rel="noopener noreferrer" class="forgot-fb-btn">
            <i class="fab fa-facebook-f"></i>
            Message Jhay tee on Facebook
        </a>

        <p class="forgot-modal-note">For your security, do not send your password.</p>
    </div>
</div>

<script>
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

document.querySelectorAll('.input-group input').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });
    input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
    });
});

// Forgot password instructions popup
const forgotPasswordLink = document.getElementById('forgotPasswordLink');
const forgotPasswordModal = document.getElementById('forgotPasswordModal');
const forgotPasswordClose = document.getElementById('forgotPasswordClose');

function openForgotPasswordModal(event) {
    event.preventDefault();
    forgotPasswordModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeForgotPasswordModal() {
    forgotPasswordModal.classList.remove('active');
    document.body.style.overflow = '';
}

forgotPasswordLink.addEventListener('click', openForgotPasswordModal);
forgotPasswordClose.addEventListener('click', closeForgotPasswordModal);

forgotPasswordModal.addEventListener('click', function(event) {
    if (event.target === forgotPasswordModal) {
        closeForgotPasswordModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && forgotPasswordModal.classList.contains('active')) {
        closeForgotPasswordModal();
    }
});
</script>

</body>
</html>