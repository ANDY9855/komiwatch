<?php
session_start();
require 'db.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        $username = $conn->real_escape_string(trim($_POST['username']));
        $email = $conn->real_escape_string(trim($_POST['email']));
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        if ($username && $email && $_POST['password']) {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param('sss', $username, $email, $password);
            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['username'] = $username;
                header('Location: dashboard.php'); exit;
            } else {
                $err = 'Username or email might be taken.';
            }
        } else { $err = 'Enter username, email and password.'; }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = $conn->real_escape_string(trim($_POST['username']));
        $password = $_POST['password'];
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $username;
                header('Location: dashboard.php'); exit;
            } else { $err = 'Invalid credentials.'; }
        } else { $err = 'No such user.'; }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WatchParty — Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body {
    margin:0;
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
    overflow: hidden;
}

/* === Animated Background Particles === */
body::before {
    content: '';
    position: absolute;
    top:0; left:0;
    width:100%; height:100%;
    background: radial-gradient(circle, rgba(255,215,0,0.2) 1px, transparent 1px) repeat;
    background-size: 40px 40px;
    animation: moveBG 20s linear infinite;
    z-index: 0;
}
@keyframes moveBG {
    0% {background-position: 0 0;}
    100% {background-position: 800px 800px;}
}

/* === GLASS CARD === */
.card {
    position: relative;
    z-index: 1;
    background: rgba(255,255,255,0.05);
    border-radius: 25px;
    backdrop-filter: blur(15px);
    padding: 3rem;
    border: 1px solid rgba(255,215,0,0.3);
    box-shadow: 0 15px 50px rgba(0,0,0,0.6);
    width: 100%;
    max-width: 450px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 60px rgba(255,215,0,0.4);
}

/* === Headings === */
h3 {
    font-weight: 700;
    color: #ffd700;
    text-align: center;
    margin-bottom: 2rem;
}

/* === ALERT === */
.alert-danger {
    background: rgba(255,0,0,0.1);
    color: #ff4d4d;
    border: 1px solid #ff4d4d;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(255,0,0,0.2);
}

/* === Tabs === */
.nav-tabs {
    border-bottom: none;
    justify-content: center;
    margin-bottom: 1.5rem;
}
.nav-tabs .nav-link {
    color: #fff;
    font-weight: 600;
    border: none;
    border-radius: 30px 30px 0 0;
    margin: 0 5px;
    background: rgba(255,255,255,0.1);
    padding: 10px 30px;
    transition: all 0.3s;
}
.nav-tabs .nav-link.active {
    background: linear-gradient(135deg, #ffd700, #ffb400);
    color: #000;
}
.nav-tabs .nav-link:hover {
    background: rgba(255,255,255,0.2);
}

/* === Inputs === */
.form-control {
    background: rgba(255,255,255,0.05);
    color: #fff;
    border: 1px solid rgba(255,215,0,0.3);
    border-radius: 14px;
    padding: 14px 20px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}
.form-control:focus {
    border-color: #ffd700;
    box-shadow: 0 0 15px rgba(255,215,0,0.4);
    background: rgba(255,255,255,0.05);
    color: #fff;
}

/* === Buttons === */
.btn-primary, .btn-success {
    border-radius: 28px;
    padding: 12px 25px;
    font-weight: 700;
    border: none;
    transition: all 0.3s ease;
    width: 100%;
}
.btn-primary {
    background: linear-gradient(135deg, #ffd700, #ffb400);
    color: #000;
    box-shadow: 0 5px 15px rgba(255,215,0,0.3);
}
.btn-success {
    background: linear-gradient(135deg, #28a745, #1e7e34);
    color: #fff;
    box-shadow: 0 5px 15px rgba(40,167,69,0.3);
}
.btn-primary:hover, .btn-success:hover {
    filter: brightness(1.15);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255,215,0,0.5);
}

/* === Responsive === */
@media (max-width: 576px){
    .card { padding: 2rem; }
    .nav-tabs .nav-link { padding: 8px 15px; font-size: 14px; }
}
</style>
</head>
<body>
<div class="card">
    <h3>Welcome to WatchParty</h3>
    <?php if($err): ?>
        <div class="alert alert-danger"><?=htmlspecialchars($err)?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3" id="tab">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#login">Login</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#reg">Register</a></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="login">
            <form method="post">
                <input type="hidden" name="action" value="login">
                <input name="username" class="form-control" placeholder="Username" required>
                <input name="password" type="password" class="form-control" placeholder="Password" required>
                <button class="btn btn-primary mt-2">Login</button>
            </form>
        </div>
        <div class="tab-pane fade" id="reg">
            <form method="post">
                <input type="hidden" name="action" value="register">
                <input name="username" class="form-control" placeholder="Choose username" required>
                <input name="email" type="email" class="form-control" placeholder="Your email" required>
                <input name="password" type="password" class="form-control" placeholder="Choose password" required>
                <button class="btn btn-success mt-2">Register</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
