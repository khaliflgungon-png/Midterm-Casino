<?php
session_start();

// Initialize game state
if (!isset($_SESSION['chips'])) {
    $_SESSION['chips'] = 1000.00;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'start_game') {
        $bet = floatval($_POST['bet'] ?? 0);
        $mines = intval($_POST['mines'] ?? 3);

        if ($bet <= 0 || $bet > $_SESSION['chips']) {
            echo json_encode(['error' => 'Invalid bet']);
            exit;
        }
        if ($mines < 1 || $mines > 24) {
            echo json_encode(['error' => 'Invalid mines count']);
            exit;
        }

        $_SESSION['chips'] -= $bet;
        $_SESSION['current_bet'] = $bet;
        $_SESSION['mines_count'] = $mines;
        $_SESSION['game_active'] = true;
        $_SESSION['revealed'] = [];
        $_SESSION['safe_revealed'] = 0;
        $_SESSION['mine_positions'] = [];

        // Place mines randomly
        $positions = range(0, 24);
        shuffle($positions);
        $_SESSION['mine_positions'] = array_slice($positions, 0, $mines);

        echo json_encode([
            'success' => true,
            'balance' => $_SESSION['chips'],
        ]);
        exit;
    }

    if ($action === 'reveal_tile') {
        if (empty($_SESSION['game_active'])) {
            echo json_encode(['error' => 'No active game']);
            exit;
        }

        $tile = intval($_POST['tile'] ?? -1);
        $revealed = $_SESSION['revealed'] ?? [];
        if ($tile < 0 || $tile > 24 || in_array($tile, $revealed)) {
            echo json_encode(['error' => 'Invalid tile']);
            exit;
        }

        $_SESSION['revealed'][] = $tile;

        if (in_array($tile, $_SESSION['mine_positions'] ?? [])) {
            // Hit a mine — preserve balance, clear only game state
            $mines = $_SESSION['mine_positions'];
            $balance = $_SESSION['chips'];
            $_SESSION['game_active'] = false;
            $_SESSION['mine_positions'] = [];
            $_SESSION['revealed'] = [];
            $_SESSION['safe_revealed'] = 0;
            $_SESSION['current_bet'] = 0;
            $_SESSION['mines_count'] = 0;

            echo json_encode([
                'result' => 'mine',
                'mine_positions' => $mines,
                'balance' => $balance,
            ]);
            exit;
        }

        // Safe tile
        $_SESSION['safe_revealed']++;
        $safe = $_SESSION['safe_revealed'];
        $total_safe = 25 - $_SESSION['mines_count'];
        $mines_count = $_SESSION['mines_count'];

        // Multiplier calculation
        $multiplier = calculateMultiplier($safe, $mines_count);

        echo json_encode([
            'result' => 'safe',
            'safe_count' => $safe,
            'multiplier' => round($multiplier, 2),
            'potential_win' => round($_SESSION['current_bet'] * $multiplier, 2),
            'balance' => $_SESSION['chips'],
        ]);
        exit;
    }

    if ($action === 'cashout') {
        if (empty($_SESSION['game_active'])) {
            echo json_encode(['error' => 'No active game']);
            exit;
        }

        $safe = $_SESSION['safe_revealed'];
        $mines_count = $_SESSION['mines_count'];
        $multiplier = $safe > 0 ? calculateMultiplier($safe, $mines_count) : 1.0;
        $winnings = round($_SESSION['current_bet'] * $multiplier, 2);

        $_SESSION['chips'] += $winnings;
        $_SESSION['game_active'] = false;
        $mine_positions = $_SESSION['mine_positions'];

        echo json_encode([
            'success' => true,
            'winnings' => $winnings,
            'multiplier' => round($multiplier, 2),
            'mine_positions' => $mine_positions,
            'balance' => $_SESSION['chips'],
        ]);
        exit;
    }

    if ($action === 'reset') {
        $_SESSION['game_active'] = false;
        $_SESSION['revealed'] = [];
        echo json_encode(['success' => true, 'balance' => 1000.00]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

function calculateMultiplier($safe_revealed, $mines_count) {
    // House edge ~2%
    $total = 25;
    $safe_total = $total - $mines_count;
    $multiplier = 1.0;
    for ($i = 0; $i < $safe_revealed; $i++) {
        $remaining_tiles = $total - $i;
        $remaining_mines = $mines_count;
        $safe_prob = ($remaining_tiles - $remaining_mines) / $remaining_tiles;
        $multiplier *= (1 / $safe_prob) * 0.98; // 2% house edge
    }
    return max(1.0, $multiplier);
}

$balance = $_SESSION['chips'] ?? 1000.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mines Royale</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Josefin+Sans:wght@300;400;600&display=swap" rel="stylesheet">
<style>
  :root {
    --deep-green:    #0a1f14;
    --felt-green:    #0d2b1a;
    --table-green:   #133a22;
    --rich-green:    #1a4d2e;
    --gold:          #c9a84c;
    --gold-light:    #e2c070;
    --gold-pale:     #f0d98a;
    --cream:         #f5edd8;
    --cream-dim:     #d6c9a8;
    --burgundy:      #7a1e2e;
    --crimson:       #a02030;
    --red-bright:    #e04050;
    --ivory:         #faf6ed;
    --shadow-gold:   rgba(201,168,76,0.18);
    --shadow-dark:   rgba(0,0,0,0.6);
    --text-primary:  #f0e6cc;
    --text-dim:      #a89870;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {background-color: var(--deep-green);background-image:radial-gradient(ellipse at 50% 0%, rgba(26,77,46,0.6) 0%, transparent 60%),
      repeating-linear-gradient(
        45deg,
        transparent,
        transparent 20px,
        rgba(10,31,20,0.3) 20px,
        rgba(10,31,20,0.3) 21px
      );font-family: 'Josefin Sans', sans-serif;color: var(--text-primary);min-height: 100vh;display: flex;flex-direction: column;
    align-items: center;padding: 0 16px 40px;
  }

  /* ─── Header ─── */
  .hdr{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;border-bottom:1px solid rgba(201,168,76,.25);padding:28px 0 16px;margin-bottom:24px;}
  .game-tag{font-size:.62rem;letter-spacing:.25em;text-transform:uppercase;color:var(--text-dim);margin-bottom:3px;}
  h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4.2vw,3rem);line-height:1;color:var(--gold);letter-spacing:.08em;text-transform:uppercase;}
  h1 em{color:var(--cream);font-style:normal;font-weight:400;}
  .hdr-right{display:flex;gap:20px;align-items:flex-end;}
  .game-tag{font-size:.62rem;letter-spacing:.25em;text-transform:uppercase;color:var(--text-dim);margin-bottom:3px;
  }
  header {width: 100%;max-width: 860px;display: flex;align-items: center;justify-content: space-between;padding: 24px 0 16px;
    border-bottom: 1px solid rgba(201,168,76,0.25);
  }
  h1{
    font-family:'Cormorant Garamond',serif;
    font-size:clamp(2rem,4.2vw,3rem);
    line-height:1;color:var(--gold);
    letter-spacing:.08em;
    text-transform:uppercase;
  }
  h1 em{
    color:var(--cream);
    font-style:normal;
    font-weight:400;
  }
  .logo {
    font-family: 'Cormorant Garamond', serif;
    font-size: 2rem;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }
  .logo span { color: var(--cream); font-weight: 400; }
  
  .balance-chip {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
  }
  .balance-label {
    font-size: 0.62rem;
    letter-spacing: 0.2em;
    color: var(--text-dim);
    text-transform: uppercase;
  }
  .balance-value {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--gold-light);
  }

  /* ─── Main layout ─── */
  .game-wrapper {
    width: 100%;
    max-width: 860px;
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 24px;
    margin-top: 28px;
  }

  @media (max-width: 680px) {
    .game-wrapper { grid-template-columns: 1fr; }
  }

  /* ─── Grid ─── */
  .grid-section {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .grid-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .section-title {
    font-size: 0.62rem;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: var(--text-dim);
  }

  .multiplier-badge {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--gold);
    background: rgba(201,168,76,0.1);
    border: 1px solid rgba(201,168,76,0.3);
    padding: 4px 14px;
    border-radius: 4px;
    transition: all 0.3s ease;
    min-width: 90px;
    text-align: center;
  }

  .tile-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
  }

  .tile {
    aspect-ratio: 1;
    border-radius: 6px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    border: 1px solid rgba(201,168,76,0.15);
    background: linear-gradient(145deg, #193828, #0f2419);
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .tile::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, transparent 60%);
    border-radius: inherit;
  }

  .tile:hover:not(.revealed):not(.disabled) {
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 8px 24px rgba(0,0,0,0.4), 0 0 12px var(--shadow-gold);
    border-color: rgba(201,168,76,0.4);
  }

  .tile:active:not(.revealed):not(.disabled) {
    transform: scale(0.97);
  }

  .tile.disabled {
    cursor: default;
    pointer-events: none;
  }

  .tile-inner {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
  }

  /* Safe tile */
  .tile.safe {
    background: linear-gradient(145deg, #1a4d2e, #133a22);
    border-color: rgba(201,168,76,0.5);
    box-shadow: 0 0 16px rgba(26,77,46,0.5), inset 0 1px 0 rgba(201,168,76,0.2);
    animation: safeReveal 0.4s cubic-bezier(0.34,1.56,0.64,1);
  }

  @keyframes safeReveal {
    0%   { transform: scale(0.7); opacity: 0; }
    100% { transform: scale(1);   opacity: 1; }
  }

  /* Mine tile */
  .tile.mine {
    background: linear-gradient(145deg, #5a1020, #3a0a15);
    border-color: rgba(224,64,80,0.6);
    box-shadow: 0 0 20px rgba(160,32,48,0.5);
    animation: mineReveal 0.35s ease;
  }

  @keyframes mineReveal {
    0%   { transform: scale(0.8); }
    50%  { transform: scale(1.1); }
    100% { transform: scale(1); }
  }

  /* Hit mine (the one you clicked) */
  .tile.mine-hit {
    background: linear-gradient(145deg, #8a1828, #5a0e18);
    border-color: var(--red-bright);
    box-shadow: 0 0 30px rgba(224,64,80,0.7);
  }

  /* ─── Controls panel ─── */
  .controls {
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .panel {
    background: linear-gradient(160deg, #122a1a, #0d2215);
    border: 1px solid rgba(201,168,76,0.2);
    border-radius: 10px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .panel-title {
    font-size: 0.6rem;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: var(--gold);
    border-bottom: 1px solid rgba(201,168,76,0.15);
    padding-bottom: 8px;
  }

  .field-label {
    font-size: 0.65rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--text-dim);
    margin-bottom: 6px;
    display: block;
  }

  .input-row {
    display: flex;
    gap: 8px;
  }

  input[type="number"] {
    flex: 1;
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(201,168,76,0.25);
    color: var(--cream);
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.1rem;
    padding: 10px 12px;
    border-radius: 6px;
    outline: none;
    transition: border-color 0.2s;
    width: 100%;
  }
  input[type="number"]:focus {
    border-color: var(--gold);
  }

  .quick-bets {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
  }

  .quick-bet-btn {
    background: rgba(201,168,76,0.08);
    border: 1px solid rgba(201,168,76,0.2);
    color: var(--gold-light);
    font-family: 'Josefin Sans', sans-serif;
    font-size: 0.70rem;
    letter-spacing: 0.1em;
    padding: 7px 4px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s;
    text-transform: uppercase;
  }
  .quick-bet-btn:hover {
    background: rgba(201,168,76,0.18);
    border-color: var(--gold);
    color: var(--gold-pale);
  }

  .mines-selector {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 6px;
  }

  .mine-opt {
    background: rgba(0,0,0,0.25);
    border: 1px solid rgba(201,168,76,0.2);
    color: var(--text-dim);
    font-family: 'Josefin Sans', sans-serif;
    font-size: 0.7rem;
    padding: 8px 4px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
  }
  .mine-opt:hover { border-color: rgba(201,168,76,0.5); color: var(--cream); }
  .mine-opt.active {
    background: rgba(201,168,76,0.15);
    border-color: var(--gold);
    color: var(--gold-light);
    font-weight: 600;
  }

  /* Buttons */
  .btn {
    width: 100%;
    padding: 14px;
    border-radius: 7px;
    border: none;
    cursor: pointer;
    font-family: 'Josefin Sans', sans-serif;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
  }

  .btn-primary {
    background: linear-gradient(135deg, #c9a84c, #a07a28);
    color: var(--deep-green);
    box-shadow: 0 4px 16px rgba(201,168,76,0.3);
  }
  .btn-primary:hover:not(:disabled) {
    background: linear-gradient(135deg, #e2c070, #c9a84c);
    box-shadow: 0 6px 22px rgba(201,168,76,0.45);
    transform: translateY(-1px);
  }

  .btn-cashout {
    background: linear-gradient(135deg, #1a5c30, #0f3d1e);
    color: var(--gold-light);
    border: 1px solid rgba(201,168,76,0.4);
    box-shadow: 0 4px 16px rgba(26,92,48,0.4);
    display: none;
  }
  .btn-cashout:hover:not(:disabled) {
    background: linear-gradient(135deg, #216b38, #174d26);
    transform: translateY(-1px);
  }
  .btn-cashout.show { display: block; }

  .btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none !important;
  }

  /* Stats */
  .stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid rgba(201,168,76,0.08);
  }
  .stat-row:last-child { border-bottom: none; }
  .stat-label { font-size: 0.65rem; letter-spacing: 0.12em; color: var(--text-dim); text-transform: uppercase; }
  .stat-value {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1rem;
    font-weight: 600;
    color: var(--cream);
  }
  .stat-value.highlight { color: var(--gold-light); }

  /* Toast */
  #toast {
    position: fixed;
    top: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(-80px);
    background: linear-gradient(135deg, #122a1a, #0d2215);
    border: 1px solid rgba(201,168,76,0.4);
    color: var(--cream);
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.1rem;
    padding: 14px 28px;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1);
    z-index: 999;
    white-space: nowrap;
  }
  #toast.show { transform: translateX(-50%) translateY(0); }
  #toast.win  { border-color: var(--gold); color: var(--gold-light); }
  #toast.lose { border-color: var(--crimson); color: #ff8090; }

  /* Decorative line */
  .gold-line {
    width: 100%;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    margin: 4px 0;
    opacity: 0.4;
  }

  .game-state-msg {
    font-family: 'Cormorant Garamond', serif;
    font-size: 0.9rem;
    color: var(--text-dim);
    text-align: center;
    font-style: italic;
    padding: 4px 0;
  }

  /* ── RULES ── */
.rules-area{margin-top:16px;}
summary{font-size:.6rem;letter-spacing:.22em;text-transform:uppercase;color:var(--text-dim);cursor:pointer;padding:5px 0;display:inline-block;}
summary:hover{color:var(--gold-light);}
.rules-body{margin-top:10px;background:linear-gradient(160deg,#0d2215,#0a1f14);border:1px solid rgba(201,168,76,.15);border-radius:6px;padding:15px 18px;font-size:.78rem;line-height:1.9;color:var(--text-dim);}
.rules-body strong{color:var(--cream);}
</style>
</head>
<body>

<header>
  <div style="position: fixed; top: 20px; right: 20px;">
    <a href="RoyaleCasino.php" style="
        padding: 8px 16px;
        background: #c9a84c;
        color: #0a1f14;
        border-radius: 5px;
        font-weight: 600;
        text-decoration: none;
    ">🏠 Home</a>
</div>
  <div>
    <div class="game-tag">Casino · Mine Sweep Edition</div>
    <h1>Mines<em> Royale</em></h1>
  </div>
  <div class="balance-chip">
    <span class="balance-label">Balance</span>
    <span class="balance-value" id="balance-display">₱<?= number_format($balance, 2) ?></span>
  </div>
  <button class="quick-bet-btn" style="width:10%;padding:5px;" onclick="resetBalance()">&#8635; Reset</button>
</header>

<div class="game-wrapper">

  <!-- Grid -->
  <div class="grid-section">
    <div class="grid-header">
      <span class="section-title">Select a tile</span>
      <div class="multiplier-badge" id="multiplier-badge">1.00×</div>
    </div>

    <div class="tile-grid" id="tile-grid">
      <?php for ($i = 0; $i < 25; $i++): ?>
      <div class="tile disabled" id="tile-<?= $i ?>" data-index="<?= $i ?>" onclick="revealTile(<?= $i ?>)">
        <div class="tile-inner">◆</div>
      </div>
      <?php endfor; ?>
    </div>

    <div class="game-state-msg" id="state-msg">Place a bet to begin</div>
    <div class="rules-area">
        <details>
          <summary>How to play — Rules &amp; Payouts</summary>
          <div class="rules-body">
            <p><strong>Few mines:</strong>  safer, lower profit.</p>
            <p><strong>More mines:</strong> risky, huge profit potential.</p>
          </div>
        </details>
      </div>
  </div>
        
  <!-- Controls -->
  <div class="controls">

    <div class="panel">
      <div class="panel-title">Wager</div>

      <div>
        <label class="field-label">Bet Amount (₱)</label>
        <input type="number" id="bet-input" value="50" min="1" step="1" placeholder="0.00">
      </div>

      <div class="quick-bets">
        <button class="quick-bet-btn" onclick="setBet(50)">₱50</button>
        <button class="quick-bet-btn" onclick="setBet(100)">₱100</button>
        <button class="quick-bet-btn" onclick="setBet(250)">₱250</button>
        <button class="quick-bet-btn" onclick="setAllIn()">All In</button>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">Mines</div>
      <div class="mines-selector" id="mines-selector">
        <?php foreach ([1,3,5,10,24] as $m): ?>
        <div class="mine-opt <?= $m === 3 ? 'active' : '' ?>"
             onclick="selectMines(<?= $m ?>)"
             data-mines="<?= $m ?>">
          <?= $m ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div>
        <label class="field-label">Custom Amount (1–24)</label>
        <input type="number" id="mines-input" value="3" min="1" max="24" step="1"
               oninput="selectMinesCustom(this.value)"
               onblur="clampMinesInput()"
               onkeydown="blockInvalidMinesKey(event)">
      </div>
    </div>

    <button class="btn btn-primary" id="start-btn" onclick="startGame()">Bet</button>
    <button class="btn btn-cashout" id="cashout-btn" onclick="cashOut()">Cash Out</button>

    <div class="panel">
      <div class="panel-title">Round Info</div>
      <div class="stat-row">
        <span class="stat-label">Mines</span>
        <span class="stat-value" id="stat-mines">3</span>
      </div>
      <div class="stat-row">
        <span class="stat-label">Safe Tiles Left</span>
        <span class="stat-value" id="stat-safe">0</span>
      </div>
      <div class="stat-row">
        <span class="stat-label">Multiplier</span>
        <span class="stat-value highlight" id="stat-mult">1.00×</span>
      </div>
      <div class="gold-line"></div>
      <div class="stat-row">
        <span class="stat-label">Potential Win</span>
        <span class="stat-value highlight" id="stat-win">₱0.00</span>
      </div>
    </div>

    <button class="quick-bet-btn" style="width:100%;padding:10px;" onclick="resetBalance()">↺ Reset Balance</button>

  </div>
</div>

<div id="toast">—</div>

<script>
let gameActive = false;
let selectedMines = 3;
let currentBet = 0;
let currentMultiplier = 1.00;

function setBet(v) {
  document.getElementById('bet-input').value = v;
}

function setAllIn() {
  const balance = parseFloat(document.getElementById('balance-display').textContent.replace(/[₱,]/g, ''));
  if (balance > 0) document.getElementById('bet-input').value = Math.floor(balance);
}

function selectMines(n) {
  n = Math.max(1, Math.min(24, parseInt(n) || 1));
  selectedMines = n;
  document.querySelectorAll('.mine-opt').forEach(el => {
    el.classList.toggle('active', parseInt(el.dataset.mines) === n);
  });
  document.getElementById('mines-input').value = n;
  document.getElementById('stat-mines').textContent = n;
}

function selectMinesCustom(val) {
  let n = parseInt(val);
  if (isNaN(n)) return;
  if (n > 24) { n = 24; document.getElementById('mines-input').value = 24; }
  if (n < 1)  { n = 1;  document.getElementById('mines-input').value = 1;  }
  selectedMines = n;
  document.querySelectorAll('.mine-opt').forEach(el => {
    el.classList.toggle('active', parseInt(el.dataset.mines) === n);
  });
  document.getElementById('stat-mines').textContent = n;
}

function clampMinesInput() {
  const input = document.getElementById('mines-input');
  let n = parseInt(input.value);
  if (isNaN(n) || n < 1)  n = 1;
  if (n > 24) n = 24;
  input.value = n;
  selectMinesCustom(n);
}

function blockInvalidMinesKey(e) {
  // Allow: backspace, delete, arrows, tab
  if (['Backspace','Delete','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Tab'].includes(e.key)) return;
  // Block minus, plus, e (scientific notation), decimal
  if (['-','+','e','E','.'].includes(e.key)) { e.preventDefault(); return; }
  // Block digit if it would make the value exceed 24
  const current = parseInt(document.getElementById('mines-input').value + e.key);
  if (!isNaN(current) && current > 24) e.preventDefault();
}

function startGame() {
  if (gameActive) return;
  const bet = parseFloat(document.getElementById('bet-input').value);
  if (!bet || bet <= 0) { showToast('Enter a valid bet', 'lose'); return; }

  fetch('', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: `action=start_game&bet=${bet}&mines=${selectedMines}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) { showToast(data.error, 'lose'); return; }

    gameActive = true;
    currentBet = bet;
    currentMultiplier = 1.00;

    updateBalance(data.balance);
    resetGrid();

    document.getElementById('start-btn').disabled = true;
    document.getElementById('cashout-btn').classList.add('show');
    document.getElementById('cashout-btn').disabled = false;
    document.getElementById('stat-safe').textContent = (25 - selectedMines);
    document.getElementById('stat-mines').textContent = selectedMines;
    document.getElementById('stat-mult').textContent = '1.00×';
    document.getElementById('stat-win').textContent = '₱' + currentBet.toFixed(2);
    document.getElementById('multiplier-badge').textContent = '1.00×';
    document.getElementById('state-msg').textContent = 'Pick a tile…';
  });
}

function revealTile(index) {
  if (!gameActive) return;
  const tile = document.getElementById('tile-' + index);
  if (tile.classList.contains('revealed') || tile.classList.contains('mine') || tile.dataset.pending) return;

  tile.dataset.pending = '1';

  fetch('', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: `action=reveal_tile&tile=${index}`
  })
  .then(r => r.json())
  .then(data => {
    delete tile.dataset.pending;

    if (data.error) return;

    if (data.result === 'mine') {
      tile.classList.add('revealed', 'mine', 'mine-hit');
      tile.querySelector('.tile-inner').textContent = '💣';

      data.mine_positions.forEach(pos => {
        if (pos !== index) {
          const t = document.getElementById('tile-' + pos);
          t.classList.add('revealed', 'mine');
          t.querySelector('.tile-inner').textContent = '💣';
        }
      });

      gameActive = false;
      document.querySelectorAll('.tile').forEach(t => t.classList.add('disabled'));
      document.getElementById('cashout-btn').classList.remove('show');
      document.getElementById('start-btn').disabled = false;
      document.getElementById('state-msg').textContent = 'Mine hit — better luck next round';
      showToast('💣 Mine hit! Bet lost.', 'lose');
      updateBalance(data.balance);

    } else {
      tile.classList.add('revealed', 'safe');
      tile.querySelector('.tile-inner').textContent = '💎';

      currentMultiplier = data.multiplier;
      document.getElementById('multiplier-badge').textContent = data.multiplier + '×';
      document.getElementById('stat-safe').textContent = (25 - selectedMines - data.safe_count);
      document.getElementById('stat-mult').textContent = data.multiplier + '×';
      document.getElementById('stat-win').textContent = '₱' + data.potential_win.toFixed(2);
      document.getElementById('cashout-btn').disabled = false;
      document.getElementById('state-msg').textContent = 'Safe! Cash out or keep going…';
    }
  })
  .catch(() => {
    delete tile.dataset.pending;
  });
}

function cashOut() {
  if (!gameActive) return;

  fetch('', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: `action=cashout`
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) { showToast(data.error, 'lose'); return; }

    // Reveal mines
    data.mine_positions.forEach(pos => {
      const t = document.getElementById('tile-' + pos);
      t.classList.add('revealed', 'mine');
      t.querySelector('.tile-inner').textContent = '💣';
    });

    gameActive = false;
    document.querySelectorAll('.tile').forEach(t => t.classList.add('disabled'));
    document.getElementById('cashout-btn').classList.remove('show');
    document.getElementById('start-btn').disabled = false;
    document.getElementById('state-msg').textContent = 'Cashed out successfully!';
    updateBalance(data.balance);
    showToast('✦ Won ₱' + data.winnings.toFixed(2) + ' at ' + data.multiplier + '×', 'win');
  });
}

function resetBalance() {
  if (gameActive) return;
  fetch('', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: 'action=reset'
  })
  .then(r => r.json())
  .then(data => {
    updateBalance(data.balance);
    showToast('Balance reset to ₱1,000', 'win');
  });
}

function resetGrid() {
  document.querySelectorAll('.tile').forEach(tile => {
    tile.className = 'tile';
    delete tile.dataset.pending;
    tile.querySelector('.tile-inner').textContent = '◆';
  });
}

function updateBalance(val) {
  document.getElementById('balance-display').textContent = '₱' + parseFloat(val).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

let toastTimer;
function showToast(msg, type) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'show ' + (type || '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.className = '', 3000);
}
</script>
</body>
</html>