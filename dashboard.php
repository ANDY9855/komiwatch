<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$msg = '';

// Fetch user details
$user_stmt = $conn->prepare("SELECT email, avatar, created_at FROM users WHERE id=?");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user_res = $user_stmt->get_result();
$user_data = $user_res->fetch_assoc();

function genCode($len=6){ 
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; 
    $c=''; 
    for($i=0;$i<$len;$i++) $c .= $chars[random_int(0, strlen($chars)-1)]; 
    return $c; 
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
    if (isset($_POST['create'])){
        $video = $conn->real_escape_string($_POST['video_url']);
        $code = genCode();
        $stmt = $conn->prepare("INSERT INTO parties (host_id, room_code, video_url) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $user_id, $code, $video);
        if ($stmt->execute()){
            $party_id = $stmt->insert_id;
            $m = $conn->prepare("INSERT INTO party_members (party_id, user_id) VALUES (?, ?)");
            $m->bind_param('ii', $party_id, $user_id);
            $m->execute();
            header("Location: party.php?room={$code}"); exit;
        }
    }
    if (isset($_POST['join'])){
        $code = $conn->real_escape_string($_POST['room_code']);
        $r = $conn->query("SELECT id FROM parties WHERE room_code='".$code."' LIMIT 1");
        if ($r && $r->num_rows){
            header("Location: party.php?room={$code}"); exit;
        } else { 
            $msg = 'Room not found.'; 
        }
    }
}

// Fetch recent parties
$recent_res = $conn->query("SELECT room_code, video_url, created_at FROM parties WHERE host_id=$user_id ORDER BY created_at DESC LIMIT 5");
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard - KomiWatch</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
<style>
body {
  background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
  font-family: 'Poppins', sans-serif;
  color: #f1f1f1;
  min-height: 100vh;
}
.navbar {
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid rgba(255, 215, 0, 0.3);
}
.navbar-brand {
  font-weight: 800;
  font-size: 1.8rem;
  background: linear-gradient(90deg, #ffd700, #ffb400);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.navbar a { color: #ffd700 !important; }
.navbar a:hover { color: #fff !important; }

.card {
  border-radius: 20px;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 215, 0, 0.3);
  box-shadow: 0 15px 40px rgba(0,0,0,0.6);
  transition: all 0.3s ease;
}
.card:hover { transform: translateY(-5px); box-shadow: 0 20px 50px rgba(255,215,0,0.4); }

.btn-primary, .btn-success {
  border-radius: 28px;
  padding: 10px 25px;
  font-weight: 700;
  border: none;
  transition: all 0.3s ease;
  box-shadow: 0 5px 15px rgba(255,215,0,0.3);
}
.btn-primary {
  background: linear-gradient(135deg, #ffd700, #ffb400);
  color: #000;
}
.btn-success {
  background: linear-gradient(135deg, #28a745, #1e7e34);
  color: #fff;
}
.btn-primary:hover, .btn-success:hover {
  filter: brightness(1.15);
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(255,215,0,0.5);
}

.form-control {
  background: rgba(255,255,255,0.05);
  color: #fff;
  border: 1px solid rgba(255,215,0,0.3);
  border-radius: 14px;
  padding: 12px 15px;
  transition: all 0.3s ease;
}
.form-control:focus {
  border-color: #ffd700;
  box-shadow: 0 0 15px rgba(255,215,0,0.3);
  background: rgba(255,255,255,0.05);
  color: #fff;
}

.alert-danger {
  background: rgba(255,0,0,0.1);
  color: #ff4d4d;
  border: 1px solid #ff4d4d;
  border-radius: 12px;
  box-shadow: 0 5px 15px rgba(255,0,0,0.2);
}

h5 { font-weight: 800; color: #ffd700; font-size: 1.4rem; margin-bottom: 15px; }

.avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #ffd700;
  margin-right: 15px;
}
.container { max-width: 1100px; }
@media (max-width: 768px){
  .row { flex-direction: column; }
  .btn { width: 100%; }
}
</style>
</head>
<body>
<nav class="navbar navbar-expand shadow-sm mb-4">
  <div class="container d-flex align-items-center">
    <a class="navbar-brand">🎬 KomiWatch</a>
    <div class="ms-auto text-light small">
      Logged in as <strong><?=htmlspecialchars($username)?></strong> &nbsp;|&nbsp;
      <a href="logout.php" class="text-decoration-none text-info">Logout</a>
    </div>
  </div>
</nav>


<div class="container">
  <?php if($msg): ?>
    <div class="alert alert-danger"><?=htmlspecialchars($msg)?></div>
  <?php endif; ?>

  <div class="row g-4 mb-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h5>Create Party</h5>
          <form method="post">
            <div class="mb-3">
              <input name="video_url" class="form-control" placeholder="Video URL (optional)">
            </div>
            <button name="create" class="btn btn-primary w-100">Create Room</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h5>Join Party</h5>
          <form method="post">
            <div class="mb-3">
              <input name="room_code" class="form-control" placeholder="Enter room code">
            </div>
            <button name="join" class="btn btn-success w-100">Join Room</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Parties -->
  <h5>Recent Parties</h5>
  <div class="row g-3">
    <?php if($recent_res->num_rows): ?>
      <?php while($row = $recent_res->fetch_assoc()): ?>
      <div class="col-md-4">
        <div class="card p-3">
          <strong>Room Code: <?=$row['room_code']?></strong><br>
          Video: <?= $row['video_url'] ? htmlspecialchars($row['video_url']) : 'Screen Share' ?><br>
          Created: <?=date('d M Y', strtotime($row['created_at']))?>
        </div>
      </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12"><div class="card p-3 text-center">No recent parties.</div></div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
