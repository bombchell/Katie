<?php
// --- PLAN B: TERMUX LOCAL & SSH REMOTE EXECUTION ---
if (isset($_POST['local_cmd'])) {
    // Executes locally on the Android/Termux device
    echo htmlspecialchars(shell_exec($_POST['local_cmd'] . ' 2>&1'));
    exit;
}

if (isset($_POST['ssh_cmd'])) {
    // Executes remotely on the Windows 2022 VPS via SSH
    // Note: Requires OpenSSH Server on VPS and Termux SSH keys copied over.
    $cmd = escapeshellarg($_POST['ssh_cmd']);
    $output = shell_exec("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 Administrator@162.222.206.145 powershell.exe -Command $cmd 2>&1");
    echo htmlspecialchars($output ?: "SSH Timeout or Auth Failed. Ensure Termux pubkey is on VPS.");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Chell's Shell</title>
<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>

<style>
    :root { 
        --bg-color: #050505; 
        --pipboy-green: #39ff14; 
        --portal-orange: #ff7e00; 
        --portal-blue: #0078ff;
        --panel-bg: rgba(10, 20, 10, 0.85); 
    }
    
    body { 
        background-color: var(--bg-color); 
        color: var(--pipboy-green); 
        font-family: 'Consolas', Courier, monospace; 
        margin: 0; 
        height: 100vh; 
        display: flex; 
        flex-direction: column; 
        overflow: hidden;
        /* CRT Scanline Effect */
        background-image: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
        background-size: 100% 4px, 3px 100%;
    }

    /* Top 1/3: Server PowerShell */
    .remote-terminal { 
        flex: 1; 
        border-bottom: 2px solid var(--portal-blue); 
        padding: 10px; 
        display: flex; 
        flex-direction: column; 
        background: rgba(0, 30, 60, 0.2);
    }
    
    /* Middle 1/3: Local Termux Bash */
    .local-terminal { 
        flex: 1; 
        border-bottom: 2px solid var(--portal-orange); 
        padding: 10px; 
        display: flex; 
        flex-direction: column; 
        background: rgba(60, 20, 0, 0.2);
    }

    .term-output { flex: 1; overflow-y: auto; font-size: 12px; white-space: pre-wrap; margin-bottom: 5px; text-shadow: 0 0 3px var(--pipboy-green); }
    .term-input-row { display: flex; align-items: center; }
    .term-prompt { margin-right: 8px; font-weight: bold; }
    .term-input { flex: 1; background: transparent; border: none; color: white; font-family: inherit; font-size: 14px; outline: none; }

    /* Bottom 1/3: Chell's Shell (Game Engine) */
    .chells-shell { 
        flex: 1.5; 
        position: relative; 
        display: flex; 
        padding: 10px; 
        background: var(--panel-bg);
    }

    /* 3D Player & Fading Feed Overlay */
    .player-zone { 
        flex: 1; 
        position: relative; 
        border: 1px solid #333; 
        border-radius: 8px; 
        margin-right: 10px; 
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .player-render {
        font-size: 40px;
        color: rgba(57, 255, 20, 0.3); /* Ghosted behind console */
        z-index: 1;
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }

    .console-feed {
        position: absolute;
        bottom: 10px;
        left: 10px;
        width: calc(100% - 20px);
        font-size: 11px;
        line-height: 1.5em;
        max-height: 4.5em; /* Exactly 3 lines */
        overflow: hidden;
        z-index: 10;
        color: var(--pipboy-green);
        text-shadow: 0 0 2px black;
        /* Fades the text into the 3D player */
        -webkit-mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 40%, rgba(0,0,0,0) 100%);
        mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 40%, rgba(0,0,0,0) 100%);
    }

    /* Keypad */
    .keypad-zone { 
        width: 140px; 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 5px; 
    }
    .key { 
        background: #111; 
        border: 1px solid var(--pipboy-green); 
        color: var(--pipboy-green); 
        border-radius: 4px; 
        display: flex; 
        justify-content: center; 
        align-items: center; 
        font-size: 18px; 
        cursor: pointer; 
    }
    .key:active { background: var(--pipboy-green); color: black; }
    .key-go { grid-column: 3; grid-row: 3 / span 2; background: var(--portal-orange); color: black; font-weight: bold; border-color: var(--portal-orange);}
    .key-lr { grid-column: 1 / span 2; font-size: 14px; background: var(--portal-blue); color: black; border-color: var(--portal-blue);}
    
    /* Focus Indicator */
    .focus-ring { border: 2px solid white !important; box-shadow: 0 0 10px white; }
</style>
</head>
<body>

<div class="remote-terminal" id="pane-remote" onclick="setFocus('remote')">
    <div style="color:var(--portal-blue); font-weight:bold; font-size:10px;">[PORTAL 1] VPS POWERSHELL (162.222.206.145)</div>
    <div class="term-output" id="out-remote" role="log"></div>
    <div class="term-input-row">
        <span class="term-prompt" style="color:var(--portal-blue);">PS></span>
        <input type="text" class="term-input" id="in-remote" onkeydown="if(event.keyCode===13) execCmd('remote')">
    </div>
</div>

<div class="local-terminal" id="pane-local" onclick="setFocus('local')">
    <div style="color:var(--portal-orange); font-weight:bold; font-size:10px;">[PORTAL 2] LOCAL TERMUX (PLAN B NODE)</div>
    <div class="term-output" id="out-local" role="log"></div>
    <div class="term-input-row">
        <span class="term-prompt" style="color:var(--portal-orange);">~$</span>
        <input type="text" class="term-input" id="in-local" onkeydown="if(event.keyCode===13) execCmd('local')">
    </div>
</div>

<div class="chells-shell" id="pane-game" onclick="setFocus('game')">
    <div class="player-zone">
        <div class="player-render">(****)</div>
        <div class="console-feed" id="game-feed">
            Initializing Chell's Shell...<br>
            Awaiting Proximity Telemetry...<br>
            System Ready.
        </div>
    </div>
    
    <div class="keypad-zone">
        <div class="key" onclick="press('1')">1</div><div class="key" onclick="press('2')">2</div><div class="key" onclick="press('3')">3</div>
        <div class="key" onclick="press('4')">4</div><div class="key" onclick="press('5')">5</div><div class="key" onclick="press('6')">6</div>
        <div class="key" onclick="press('7')">7</div><div class="key" onclick="press('8')">8</div><div class="key key-go" onclick="press('GO')">GO</div>
        <div class="key" onclick="press('9')">9</div><div class="key" onclick="press('0')">0</div><div class="key key-lr" onclick="toggleFocus()">L/R</div>
    </div>
</div>

<script>
    // --- MULTIPLEXER LOGIC ---
    let activePane = 'game'; 
    const panes = ['remote', 'local', 'game'];

    function setFocus(target) {
        activePane = target;
        document.getElementById('pane-remote').classList.remove('focus-ring');
        document.getElementById('pane-local').classList.remove('focus-ring');
        document.getElementById('pane-game').classList.remove('focus-ring');
        document.getElementById('pane-' + target).classList.add('focus-ring');
        
        if(target !== 'game') document.getElementById('in-' + target).focus();
    }

    function toggleFocus() {
        let idx = panes.indexOf(activePane);
        let nextIdx = (idx + 1) % panes.length;
        setFocus(panes[nextIdx]);
    }

    // Initialize focus
    setFocus('game');

    // --- KEYPAD INPUT ROUTING ---
    function press(val) {
        if (val === 'GO') {
            if (activePane !== 'game') execCmd(activePane);
            return;
        }

        if (activePane === 'game') {
            const feed = document.getElementById('game-feed');
            feed.innerHTML += `<br>HEX IN: ${val}`;
            // Auto-scroll logic for 3-line fade
            feed.scrollTop = feed.scrollHeight; 
        } else {
            const input = document.getElementById('in-' + activePane);
            input.value += val;
        }
    }

    // --- TERMINAL EXECUTION LOGIC ---
    function execCmd(target) {
        const input = document.getElementById('in-' + target);
        const output = document.getElementById('out-' + target);
        const cmd = input.value;
        if (!cmd) return;

        output.innerHTML += `<div><span style="color:white;">> ${cmd}</span></div>`;
        input.value = '';

        const formData = new FormData();
        formData.append(target === 'local' ? 'local_cmd' : 'ssh_cmd', cmd);

        fetch('', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(text => { 
                output.innerHTML += `<div>${text}</div>`; 
                output.scrollTop = output.scrollHeight; 
            });
    }

    // --- FIREBASE PROXIMITY (Retained) ---
    const firebaseConfig = { projectId: "studio-6892711111-9062f", databaseURL: "https://studio-6892711111-9062f-default-rtdb.firebaseio.com/" };
    if (!firebase.apps.length) { firebase.initializeApp(firebaseConfig); }
    const db = firebase.database();
    
    if ('geolocation' in navigator) {
        navigator.geolocation.watchPosition((position) => {
            const speed = position.coords.speed !== null ? position.coords.speed : 0; 
            const mode = speed > 4.5 ? 'DRIVING' : 'WALKING'; 
            db.ref('telemetry/chell').set({ 
                lat: position.coords.latitude, 
                lon: position.coords.longitude, 
                state: mode 
            });
            document.getElementById('game-feed').innerHTML += `<br>Telemetry Sync: ${mode}`;
        }, null, { enableHighAccuracy: true });
    }
</script>
</body>
</html>
