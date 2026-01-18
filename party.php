<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$room = isset($_GET['room']) ? $conn->real_escape_string($_GET['room']) : '';
if (!$room) { die('Missing room'); }

// load party
$stmt = $conn->prepare("SELECT * FROM parties WHERE room_code = ? LIMIT 1");
$stmt->bind_param('s', $room); $stmt->execute(); $res = $stmt->get_result();
if (!$party = $res->fetch_assoc()) die('Room not found');

// detect host: try common col name host_id, fallback to creator_id etc.
$is_host = false;
$hostKeys = ['host_id','owner_id','creator_id','created_by','user_id'];
foreach ($hostKeys as $k) {
  if (isset($party[$k])) {
    $is_host = (intval($party[$k]) === intval($user_id));
    break;
  }
}

$party_id = $party['id'];

// ensure member exists
$m = $conn->prepare("SELECT id FROM party_members WHERE party_id=? AND user_id=? LIMIT 1");
$m->bind_param('ii', $party_id, $user_id); $m->execute(); $mr = $m->get_result();
if (!$mr->num_rows){
  $ins = $conn->prepare("INSERT INTO party_members (party_id, user_id) VALUES (?, ?)");
  $ins->bind_param('ii', $party_id, $user_id);
  $ins->execute();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Room <?=htmlspecialchars($room)?> - WatchParty</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* === GLOBAL === */
body {
  background: linear-gradient(135deg, #000000, #1a1a1a 50%, #000000);
  font-family: 'Poppins', sans-serif;
  color: #f1f1f1;
  min-height: 100vh;
  overflow-x: hidden;
}

/* === NAVBAR === */
.navbar {
  background: #000000cc;
  backdrop-filter: blur(8px);
  border-bottom: 1px solid rgba(255, 215, 0, 0.3);
  padding: 0.8rem 1rem;
}
.navbar-brand {
  font-weight: 700;
  font-size: 1.5rem;
  background: linear-gradient(90deg, #ffd700, #ffb400);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.navbar a {
  color: #ffd700 !important;
  transition: 0.3s;
}
.navbar a:hover {
  color: #fff !important;
}

/* === CARD STYLE === */
.card {
  border-radius: 18px;
  background: #0d0d0d;
  border: 1px solid rgba(255, 215, 0, 0.25);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.8);
  transition: transform 0.3s ease;
}
.card:hover {
  transform: translateY(-3px);
}

/* === VIDEO WRAP === */
.video-wrap {
  background: #000;
  height: 430px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid rgba(255, 215, 0, 0.35);
  box-shadow: 0 0 25px rgba(255, 215, 0, 0.4);
  position: relative;
  overflow: hidden;
}
#remoteStreams video,
#local-screen {
  border-radius: 10px;
  box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
  transition: all 0.3s ease;
}
#remoteStreams video:hover {
  transform: scale(1.03);
}

/* === BUTTONS === */
.btn-ctrl {
  border-radius: 28px;
  padding: 7px 18px;
  font-weight: 500;
  transition: all 0.2s ease;
}
.btn-success {
  background: linear-gradient(135deg, #ffd700, #ffb400);
  border: none;
  color: #000;
}
.btn-danger {
  background: linear-gradient(135deg, #ff4d4d, #b30000);
  border: none;
  color: #fff;
}
.btn-primary {
  background: linear-gradient(135deg, #ffd700, #ffb400);
  border: none;
  color: #000;
}
.btn-ctrl:hover,
.btn-primary:hover {
  filter: brightness(1.1);
  transform: translateY(-2px);
}

/* === CHATBOX === */
.chat-box {
  height: 430px;
  overflow-y: auto;
  padding: 16px;
  background: #111;
  border-left: 1px solid rgba(255, 215, 0, 0.25);
  display: flex;
  flex-direction: column;
}

/* CHAT MESSAGE WRAPPER */
.chat-message {
  max-width: 70%;
  padding: 10px 14px;
  margin: 6px;
  border-radius: 12px;
  clear: both;
  word-wrap: break-word;
  font-size: 15px;
}
.chat-message.me {
  background: #d4af37; /* golden */
  color: #000;
  margin-left: auto;
  text-align: right;
  border-top-right-radius: 0;
}
.chat-message.other {
  background: #222; /* black/dark */
  color: #fff;
  margin-right: auto;
  text-align: left;
  border-top-left-radius: 0;
}

/* TYPING INDICATOR */
.typing {
  display: inline-block;
  padding: 8px 14px;
  border-radius: 14px;
  background: rgba(255, 215, 0, 0.15);
  color: #ffd700;
  font-size: 0.85rem;
  margin-bottom: 12px;
}

/* === CHAT FORM === */
#chatForm {
  background: #0d0d0d;
  border-top: 1px solid rgba(255, 215, 0, 0.25);
}
#chatInput {
  background: transparent;
  border: none;
  color: #fff;
  padding: 12px;
}
#chatInput:focus {
  outline: none;
  box-shadow: none;
  background: transparent;
  color: #fff;
}
#chatForm button {
  border-radius: 0;
  font-weight: 600;
  background: linear-gradient(135deg, #ffd700, #ffb400);
  border: none;
  color: #000;
}

/* === SCROLLBAR === */
.chat-box::-webkit-scrollbar {
  width: 6px;
}
.chat-box::-webkit-scrollbar-thumb {
  background: linear-gradient(#ffd700, #ffb400);
  border-radius: 10px;
}
.chat-box::-webkit-scrollbar-track {
  background: #000;
}

/* === ANIMATIONS === */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>
<nav class="navbar navbar-expand shadow-sm mb-3">
  <div class="container">
    <a class="navbar-brand text-light fw-bold">🎬 KomiWatch</a>
    <div class="ms-auto text-light small-muted">
      Room <strong><?=htmlspecialchars($room)?></strong> — <?=htmlspecialchars($username)?> &nbsp; | &nbsp;
      <a href="dashboard.php" class="text-decoration-none text-info">Back</a>
    </div>
  </div>
</nav>

<div class="container">
  <div class="row g-3">
    <!-- Video Section -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body">
          <div class="video-wrap" id="videoWrap">
            <div id="videoPlaceholder" class="text-center" style="color:#bbb;">
              <div style="font-size:22px; font-weight:600;">Waiting for host to share screen...</div>
              <div class="small-muted" style="margin-top:6px;">When somebody shares their screen, it will appear here.</div>
            </div>
          </div>

          <div class="mt-3 d-flex align-items-center">
            <?php if ($is_host): ?>
              <button id="startShareBtn" class="btn btn-success btn-ctrl">Start Screen Share</button>
              <button id="stopShareBtn" class="btn btn-danger btn-ctrl" style="display:none">Stop Sharing</button>
            <?php endif; ?>
            <div class="ms-auto small-muted">WebRTC live sharing • <span id="peerCount">0</span> viewers</div>
          </div>

          <div id="remoteStreams" class="mt-3 d-flex gap-2 flex-wrap"></div>
        </div>
      </div>
    </div>

    <!-- Chat Section -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header bg-dark text-white py-2">
          💬 Live Chat
        </div>
        <div class="card-body p-0 d-flex flex-column">
          <div id="chatBox" class="chat-box flex-grow-1"></div>

          <form id="chatForm" class="d-flex align-items-center" style="padding:12px;">
            <input id="chatInput" class="form-control me-2" placeholder="Type a message..." autocomplete="off" />
            <button class="btn btn-primary" type="submit">Send</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Server-side vars -->
<script>
  const ROOM = <?= json_encode($room) ?>;
  const USER_ID = <?= json_encode($user_id) ?>;
  const USERNAME = <?= json_encode($username) ?>;
  const IS_HOST = <?= $is_host ? 'true' : 'false' ?>;
</script>

<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<script>
/* ========== Signaling + WebRTC + Chat logic ========== */
const SIGNALING_SERVER = "http://localhost:3000"; // change if remote
const socket = io(SIGNALING_SERVER, { transports: ['websocket'] });

let peers = new Map();
let localScreenStream = null;
let amIHost = false;

// Track viewer count (simple)
let viewerCount = 0;
function updateViewerCount(n){
  const el = document.getElementById('peerCount');
  if (el) el.textContent = n;
}

// join room once connected
socket.on('connect', () => {
  socket.emit('join-room', ROOM, { userId: USER_ID, username: USERNAME });
});

// When someone joins the room we get notified (server relays)
socket.on('peer-joined', async ({ id, userMeta }) => {
  // increment viewer count (server doesn't tell host vs guest)
  viewerCount++;
  updateViewerCount(viewerCount);

  // if I'm host and I have a screen stream, create offer for the newcomer
  if (IS_HOST && localScreenStream) {
    await createOfferForPeer(id, localScreenStream);
  }
});

// signaling messages (offer/answer/candidate)
socket.on('signal', async ({ from, data }) => {
  if (!peers.has(from)) await preparePeerConnection(from);
  const pc = peers.get(from);
  try {
    if (data.type === 'offer') {
      await pc.setRemoteDescription(new RTCSessionDescription(data));
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      socket.emit('signal', { to: from, data: pc.localDescription });
    } else if (data.type === 'answer') {
      await pc.setRemoteDescription(new RTCSessionDescription(data));
    } else if (data.candidate) {
      await pc.addIceCandidate(new RTCIceCandidate(data.candidate));
    }
  } catch (err) { console.error('signal error', err); }
});

// peer left
socket.on('peer-left', (id) => {
  destroyPeer(id);
  viewerCount = Math.max(0, viewerCount-1);
  updateViewerCount(viewerCount);
});

/* ---------- WebRTC helpers ---------- */
async function preparePeerConnection(peerId) {
  if (peers.has(peerId)) return peers.get(peerId);
  const pc = new RTCPeerConnection({
    iceServers: [{ urls: "stun:stun.l.google.com:19302" }]
  });

  pc.onicecandidate = (e) => {
    if (e.candidate) {
      socket.emit('signal', { to: peerId, data: { candidate: e.candidate } });
    }
  };

pc.ontrack = (e) => {
  const remoteStream = e.streams[0];
  
  // Check if remote video element already exists
  let video = document.getElementById('remote-main');
  if (!video) {
    video = document.createElement('video');
    video.id = 'remote-main';
    video.autoplay = true;
    video.playsInline = true;
    video.muted = false; // users should hear host
    video.style.width = '100%';
    video.style.height = '100%';
    video.style.objectFit = 'contain';
    document.getElementById('videoWrap').innerHTML = ''; // remove placeholder
    document.getElementById('videoWrap').appendChild(video);
  }
  video.srcObject = remoteStream;
};


  peers.set(peerId, pc);
  return pc;
}

async function createOfferForPeer(peerId, stream) {
  const pc = await preparePeerConnection(peerId);
  // add tracks
  stream.getTracks().forEach(track => pc.addTrack(track, stream));
  const offer = await pc.createOffer();
  await pc.setLocalDescription(offer);
  socket.emit('signal', { to: peerId, data: pc.localDescription });
}

function destroyPeer(peerId) {
  if (peers.has(peerId)) {
    try { peers.get(peerId).close(); } catch(e) {}
    peers.delete(peerId);
  }
  const el = document.getElementById('remote-' + peerId);
  if (el) el.remove();
}

/* ---------- UI helpers to show streams ---------- */
function showStreamInMain(stream, isRemote=false){
  const videoWrap = document.getElementById('videoWrap');
  videoWrap.innerHTML = '';
  const video = document.createElement('video');
  video.id = isRemote ? ('remote-main-' + Date.now()) : 'local-screen';
  video.autoplay = true;
  video.muted = !isRemote; // mute local preview
  video.playsInline = true;
  video.style.width = '100%';
  video.style.height = '100%';
  video.style.objectFit = 'contain';
  video.srcObject = stream;
  videoWrap.appendChild(video);
}

function addSmallPreview(stream, peerId){
  if (document.getElementById('remote-' + peerId)) return;
  const tile = document.createElement('video');
  tile.id = 'remote-' + peerId;
  tile.autoplay = true; tile.playsInline = true;
  tile.style.width = '220px';
  tile.style.height = '120px';
  tile.style.objectFit = 'cover';
  tile.srcObject = stream;
  document.getElementById('remoteStreams').appendChild(tile);
}

/* ========== Screen share handlers (host-only) ========== */
const startBtn = document.getElementById('startShareBtn');
const stopBtn  = document.getElementById('stopShareBtn');

// hide share controls for guests (buttons were only rendered for host, but guard anyway)
if (!IS_HOST) {
  if (startBtn) startBtn.style.display = 'none';
  if (stopBtn) stopBtn.style.display = 'none';
}

if (IS_HOST && startBtn) {
  startBtn.addEventListener('click', async () => {
    try {
      // request screen (browser will show picker)
      localScreenStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
      if (!localScreenStream) throw new Error('No stream captured');
      amIHost = true;
      startBtn.style.display = 'none';
      if (stopBtn) stopBtn.style.display = 'inline-block';

      // show own screen in main area
      showStreamInMain(localScreenStream, false);

      // create offers for existing peers so they receive the tracks
      for (const peerId of peers.keys()) {
        await createOfferForPeer(peerId, localScreenStream);
      }

      // notify server/others optionally
      socket.emit('host-started', { room: ROOM });
    } catch (err) {
      console.error('Screen share failed:', err);
      alert('Screen share failed: ' + (err && err.message ? err.message : err));
    }
  });
}

if (IS_HOST && stopBtn) {
  stopBtn.addEventListener('click', () => {
    if (localScreenStream) {
      localScreenStream.getTracks().forEach(t => t.stop());
      localScreenStream = null;
    }
    amIHost = false;
    stopBtn.style.display = 'none';
    if (startBtn) startBtn.style.display = 'inline-block';

    // restore placeholder
    const ph = document.createElement('div');
    ph.id = 'videoPlaceholder';
    ph.innerHTML = `<div style="font-size:22px; font-weight:600; color:#bbb;">Waiting for host to share screen...</div>
                    <div class="small-muted" style="margin-top:6px;">When somebody shares their screen, it will appear here.</div>`;
    const videoWrap = document.getElementById('videoWrap');
    videoWrap.innerHTML = '';
    videoWrap.appendChild(ph);

    // notify others optionally
    socket.emit('host-stopped', { room: ROOM });
  });
}

/* ========== CHAT (real-time + persistent) ========== */
const chatBox = document.getElementById('chatBox');
const chatForm = document.getElementById('chatForm');
const chatInput = document.getElementById('chatInput');

function escapeHtml(s){
  return String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
}

function addMessageToUI(user, text, timestampOrIsMe = null, maybeIsMe = null){
  let isMe;
  if (typeof maybeIsMe === 'boolean') {
    isMe = maybeIsMe;
  } else if (typeof timestampOrIsMe === 'boolean') {
    isMe = timestampOrIsMe;
  } else {
    isMe = (user === USERNAME);
  }

  const div = document.createElement('div');
  div.className = 'chat-message ' + (isMe ? 'me' : 'other');
  div.innerHTML = `<strong>${escapeHtml(user)}:</strong> ${escapeHtml(text)}`;
  chatBox.appendChild(div);
  chatBox.scrollTop = chatBox.scrollHeight;
}

// load persisted messages on page load
async function loadOldMessages(){
  try {
    const res = await fetch('getMessages.php?room=' + encodeURIComponent(ROOM));
    if (!res.ok) throw new Error('Failed to load messages');
    const data = await res.json();
    data.forEach(m => {
      const isMe = (m.username === USERNAME);
      addMessageToUI(m.username, m.message, null, isMe);
    });
  } catch (err) {
    console.warn('Could not load old messages:', err);
  }
}
loadOldMessages();

// send message handler
chatForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = chatInput.value.trim();
  if (!msg) return;

  // emit real-time
  socket.emit('chat-message', { room: ROOM, user: USERNAME, text: msg });

  // optimistic UI
  addMessageToUI(USERNAME, msg, null, true);
  chatInput.value = '';

  // persist to DB
  try {
    await fetch('saveMessage.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ room: ROOM, user: USERNAME, msg })
    });
  } catch (err) {
    console.warn('Failed to save chat message:', err);
  }
});

// receive chat messages
socket.on('chat-message', ({ user, text }) => {
  if (user === USERNAME) return; // skip duplicate from server
  addMessageToUI(user, text, null, false);
});

// Optional server notifications from host start/stop
socket.on('host-started', () => {
  // you could show a toast or visual indicator here for guests
  console.log('Host started sharing');
});
socket.on('host-stopped', () => {
  console.log('Host stopped sharing');
});
</script>
</body>
</html>
