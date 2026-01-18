<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$room = isset($_GET['room']) ? $conn->real_escape_string($_GET['room']) : '';
if (!$room) { die('Missing room'); }

// validate party exists
$stmt = $conn->prepare("SELECT * FROM parties WHERE room_code=? LIMIT 1");
$stmt->bind_param("s", $room);
$stmt->execute();
$res = $stmt->get_result();
if (!$party = $res->fetch_assoc()) die("Room not found");
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Chat Room <?=$room?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.chat-box { height: 400px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; }
.message { margin-bottom: 8px; }
.message .user { font-weight: bold; margin-right: 6px; }
</style>
</head>
<body>
<nav class="navbar navbar-expand bg-light mb-3">
  <div class="container">
    <a class="navbar-brand">WatchParty Chat</a>
    <div class="ms-auto">Room <strong><?=$room?></strong> — Logged in as <?=htmlspecialchars($username)?> | <a href="party.php?room=<?=$room?>">Back to Party</a></div>
  </div>
</nav>

<div class="container">
  <div class="chat-box" id="chatBox"></div>
  <form id="chatForm" class="d-flex mt-2">
    <input type="text" id="chatInput" class="form-control me-2" placeholder="Type a message..." autocomplete="off" required>
    <button class="btn btn-primary">Send</button>
  </form>
</div>

<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<script>
const ROOM = <?= json_encode($room) ?>;
const USER_ID = <?= json_encode($user_id) ?>;
const USERNAME = <?= json_encode($username) ?>;

const socket = io("http://localhost:3000", { transports: ["websocket"] });

// join the chat room
socket.emit("join-room", ROOM, { userId: USER_ID, username: USERNAME });

// send message
document.getElementById("chatForm").addEventListener("submit", e => {
  e.preventDefault();
  const input = document.getElementById("chatInput");
  const msg = input.value.trim();
  if (!msg) return;
  socket.emit("chat-message", { room: ROOM, user: USERNAME, text: msg });
  input.value = "";
});

// receive message
socket.on("chat-message", ({ user, text }) => {
  const chatBox = document.getElementById("chatBox");
  const div = document.createElement("div");
  div.className = "message";
  div.innerHTML = `<span class="user">${user}:</span> ${text}`;
  chatBox.appendChild(div);
  chatBox.scrollTop = chatBox.scrollHeight;
});
</script>
</body>
</html>