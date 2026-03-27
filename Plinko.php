<?php
session_start();

if (!isset($_SESSION['chips'])) {
    $_SESSION['chips'] = 1000;
}

// ── PLINKO CONFIG ──
// rows=12 → 13 slots (0..12)
define('ROWS', 12);
define('SLOTS', ROWS + 1); // 13

// Multipliers per slot index (0=far left, 12=far right) – symmetric, higher at edges
$SLOT_MULTIPLIERS = [
    'low'    => [1.5, 1.2, 1.1, 1.0, 0.5, 0.3, 0.5, 1.0, 1.1, 1.2, 1.5, 1.2, 1.5],
    'medium' => [5.0, 2.0, 1.5, 1.0, 0.5, 0.3, 0.5, 1.0, 1.5, 2.0, 5.0, 2.0, 5.0],
    'high'   => [20.0, 5.0, 2.0, 0.5, 0.3, 0.0, 0.3, 0.5, 2.0, 5.0, 20.0, 5.0, 20.0],
];

$slot_colors = [
    'low'    => ['#c9a84c','#9d7a30','#8a6a20','#6a5010','#3a3010','#252010','#3a3010','#6a5010','#8a6a20','#9d7a30','#c9a84c','#9d7a30','#c9a84c'],
    'medium' => ['#e8402a','#c9a84c','#9d7a30','#6a5010','#3a3010','#252010','#3a3010','#6a5010','#9d7a30','#c9a84c','#e8402a','#c9a84c','#e8402a'],
    'high'   => ['#ff2200','#e8402a','#c9a84c','#3a3010','#252010','#111','#252010','#3a3010','#c9a84c','#e8402a','#ff2200','#e8402a','#ff2200'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'play') {
        $bet_amount = intval($_POST['bet'] ?? 0);
        $risk       = $_POST['risk'] ?? 'medium';
        $num_balls  = intval($_POST['balls'] ?? 1);

        if (!array_key_exists($risk, $SLOT_MULTIPLIERS)) {
            echo json_encode(['error' => 'Invalid risk level']); exit;
        }
        if ($bet_amount < 10) {
            echo json_encode(['error' => 'Minimum bet is ₱10']); exit;
        }
        if ($bet_amount > $_SESSION['chips']) {
            echo json_encode(['error' => 'Insufficient balance']); exit;
        }
        if ($num_balls < 1 || $num_balls > 3) {
            echo json_encode(['error' => 'Invalid ball count']); exit;
        }

        $total_cost = $bet_amount * $num_balls;
        if ($total_cost > $_SESSION['chips']) {
            echo json_encode(['error' => 'Insufficient balance for ' . $num_balls . ' balls']); exit;
        }

        $mults   = $SLOT_MULTIPLIERS[$risk];
        $results = [];
        $total_win = 0;

        for ($b = 0; $b < $num_balls; $b++) {
            // Simulate plinko path: start center, each row go left(0) or right(1)
            $pos = 0;
            $path = [];
            for ($r = 0; $r < ROWS; $r++) {
                $dir = rand(0, 1);
                $path[] = $dir;
                $pos += $dir;
            }
            $slot       = $pos; // 0..12
            $multiplier = $mults[$slot];
            $win        = round($bet_amount * $multiplier);
            $total_win += $win;
            $results[]  = ['path' => $path, 'slot' => $slot, 'multiplier' => $multiplier, 'win' => $win];
        }

        $net = $total_win - $total_cost;
        $_SESSION['chips'] += $net;

        echo json_encode([
            'success'    => true,
            'results'    => $results,
            'bet'        => $bet_amount,
            'num_balls'  => $num_balls,
            'total_cost' => $total_cost,
            'total_win'  => $total_win,
            'net'        => $net,
            'balance'    => $_SESSION['chips'],
            'risk'       => $risk,
        ]);
        exit;
    }

    echo json_encode(['error' => 'Invalid action']); exit;
}

if (isset($_GET['reset'])) {
    $_SESSION['chips'] = 1000;
    header('Location: ' . $_SERVER['PHP_SELF']); exit;
}

$balance = $_SESSION['chips'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Plinko Royale</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Josefin+Sans:wght@300;400;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --deep-green:#0a1f14;--felt-green:#0d2b1a;--table-green:#133a22;--rich-green:#1a4d2e;
  --gold:#c9a84c;--gold-light:#e2c070;--gold-pale:#f0d98a;
  --cream:#f5edd8;--cream-dim:#d6c9a8;--burgundy:#7a1e2e;--crimson:#a02030;
  --text-primary:#f0e6cc;--text-dim:#a89870;
}
html{font-size:16px}
body{
  background-color:var(--deep-green);
  background-image:radial-gradient(ellipse at 50% 0%,rgba(26,77,46,.6) 0%,transparent 60%),
    repeating-linear-gradient(45deg,transparent,transparent 20px,rgba(10,31,20,.3) 20px,rgba(10,31,20,.3) 21px);
  color:var(--text-primary);font-family:'Josefin Sans',sans-serif;
  min-height:100vh;overflow-x:hidden;line-height:1.55;
}
.page{max-width:1200px;margin:0 auto;padding:0 18px 48px}

/* HEADER */
.hdr{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;
  border-bottom:1px solid rgba(201,168,76,.25);padding:24px 0 14px;margin-bottom:20px}
.game-tag{font-size:.58rem;letter-spacing:.25em;text-transform:uppercase;color:var(--text-dim);margin-bottom:3px}
h1{font-family:'Cormorant Garamond',serif;font-size:clamp(1.8rem,4vw,2.8rem);line-height:1;
  color:var(--gold);letter-spacing:.08em;text-transform:uppercase}
h1 em{color:var(--cream);font-style:normal;font-weight:400}
.hdr-right{display:flex;gap:18px;align-items:flex-end}
.stat{text-align:right}
.stat-lbl{font-size:.58rem;letter-spacing:.2em;text-transform:uppercase;color:var(--text-dim)}
.stat-val{font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:600;line-height:1.1;color:var(--gold-light)}
.btn-reset{background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);color:var(--gold-light);
  padding:6px 13px;font-family:'Josefin Sans',sans-serif;font-size:.58rem;letter-spacing:.15em;
  text-transform:uppercase;cursor:pointer;transition:.2s;border-radius:5px}
.btn-reset:hover{background:rgba(201,168,76,.18);border-color:var(--gold)}

/* LAYOUT */
.arena{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start}
.left-col{display:flex;flex-direction:column;gap:14px}
.right-col{position:sticky;top:20px;display:flex;flex-direction:column;gap:14px}

/* BOARD WRAP */
.board-wrap{background:linear-gradient(160deg,var(--table-green),var(--felt-green));
  border:1px solid rgba(201,168,76,.3);border-radius:10px;overflow:hidden;padding:20px 16px 16px}
.board-title{font-size:.58rem;letter-spacing:.3em;text-transform:uppercase;color:var(--gold);
  text-align:center;margin-bottom:14px}

/* PLINKO CANVAS */
#plinkoCanvas{display:block;margin:0 auto;border-radius:6px;cursor:default}

/* RESULT BANNER */
.result-banner{
  padding:12px 18px;border-radius:7px;font-family:'Cormorant Garamond',serif;
  font-size:1.05rem;letter-spacing:.03em;text-align:center;
  opacity:0;transform:translateY(-6px);transition:opacity .35s,transform .35s;
  border:1px solid transparent;
}
.result-banner.show{opacity:1;transform:none}
.result-banner.win{border-color:rgba(26,92,48,.8);background:linear-gradient(160deg,#0f3a1e,#0a2714);color:var(--gold-light)}
.result-banner.lose{border-color:rgba(160,32,48,.7);background:linear-gradient(160deg,#2a0a10,#1a0508);color:#ff8090}
.result-banner.push{border-color:rgba(201,168,76,.3);background:linear-gradient(160deg,#1a2a14,#0d2215);color:var(--cream-dim)}

/* PAYOUT TABLE */
.panel-wrap{background:linear-gradient(160deg,#122a1a,#0d2215);border:1px solid rgba(201,168,76,.2);border-radius:10px}
.panel-inner{padding:18px 16px}
.panel-title{font-size:.58rem;letter-spacing:.3em;text-transform:uppercase;color:var(--gold);
  margin-bottom:12px;border-bottom:1px solid rgba(201,168,76,.15);padding-bottom:7px}
.payout-slots{display:flex;gap:4px;flex-wrap:wrap}
.ps{flex:1;min-width:38px;text-align:center;padding:15px 12px;border-radius:5px;
  border:1px solid rgba(255,255,255,.06);font-size:.62rem;font-family:'Cormorant Garamond',serif;font-weight:600;
  transition:transform .15s}
.ps:hover{transform:scale(1.08)}
.ps .psm{font-size:1.5rem;font-weight:1000}
.ps .psl{font-size:.5rem;font-family:'Josefin Sans',sans-serif;letter-spacing:.05em;color:rgba(255,255,255,.5);margin-top:2px}

/* BET PANEL */
.bet-wrap{background:linear-gradient(160deg,#122a1a,#0d2215);border:1px solid rgba(201,168,76,.2);border-radius:10px}
.bet-inner{padding:18px 16px}
.bet-title{font-size:.58rem;letter-spacing:.3em;text-transform:uppercase;color:var(--gold);
  margin-bottom:12px;border-bottom:1px solid rgba(201,168,76,.15);padding-bottom:7px}
.section-lbl{font-size:.55rem;letter-spacing:.2em;text-transform:uppercase;color:var(--text-dim);margin-bottom:7px}

/* Risk Buttons */
.risk-row{display:flex;gap:6px;margin-bottom:14px}
.risk-btn{flex:1;padding:9px 4px;border-radius:6px;border:1px solid rgba(201,168,76,.2);
  background:rgba(0,0,0,.25);color:var(--text-dim);font-family:'Josefin Sans',sans-serif;
  font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;transition:.18s;text-align:center}
.risk-btn:hover{border-color:rgba(201,168,76,.5);color:var(--cream)}
.risk-btn.sel{border-color:var(--gold);color:var(--gold-light);background:rgba(201,168,76,.15);font-weight:600}

/* Ball Count */
.balls-row{display:flex;gap:6px;margin-bottom:14px}
.ball-btn{flex:1;padding:9px 4px;border-radius:6px;border:1px solid rgba(201,168,76,.2);
  background:rgba(0,0,0,.25);color:var(--text-dim);font-family:'Josefin Sans',sans-serif;
  font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;transition:.18s;text-align:center}
.ball-btn:hover{border-color:rgba(201,168,76,.5);color:var(--cream)}
.ball-btn.sel{border-color:var(--gold);color:var(--gold-light);background:rgba(201,168,76,.15);font-weight:600}

/* Wager */
.wager-row{display:flex;align-items:center;gap:9px;margin-bottom:8px}
.wager-lbl{font-size:.58rem;letter-spacing:.18em;text-transform:uppercase;color:var(--text-dim);white-space:nowrap}
.wager-in{flex:1;border:1px solid rgba(201,168,76,.25);background:rgba(0,0,0,.3);
  font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--cream);
  padding:8px 10px;outline:none;width:100%;border-radius:6px;transition:border-color .2s}
.wager-in:focus{border-color:var(--gold)}
.chips-row{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:2px}
.chip{background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);color:var(--gold-light);
  padding:5px 16px;font-family:'Josefin Sans',sans-serif;font-size:.80rem;letter-spacing:.08em;
  text-transform:uppercase;cursor:pointer;transition:.15s;border-radius:5px}
.chip:hover{background:rgba(201,168,76,.18);border-color:var(--gold)}

/* Drop Button */
.deal-row{margin-top:14px}
.btn-deal{width:100%;background:linear-gradient(135deg,var(--gold),#a07a28);border:none;border-radius:7px;
  color:var(--deep-green);font-family:'Josefin Sans',sans-serif;font-size:.80rem;font-weight:900;
  letter-spacing:.25em;text-transform:uppercase;padding:15px 10px;cursor:pointer;transition:.2s;
  box-shadow:0 4px 16px rgba(201,168,76,.3)}
.btn-deal:hover:not(:disabled){background:linear-gradient(135deg,var(--gold-light),var(--gold));
  box-shadow:0 6px 22px rgba(201,168,76,.45);transform:translateY(-1px)}
.btn-deal:disabled{opacity:.4;cursor:not-allowed;transform:none}

.error-box{background:linear-gradient(160deg,#2a0a10,#1a0508);border:1px solid rgba(160,32,48,.6);
  border-radius:6px;padding:10px 14px;color:#ff8090;font-size:.72rem;line-height:1.5}

/* Rules */
.rules-area{margin-top:2px}
summary{font-size:.58rem;letter-spacing:.22em;text-transform:uppercase;color:var(--text-dim);cursor:pointer;padding:5px 0;display:inline-block}
summary:hover{color:var(--gold-light)}
.rules-body{margin-top:8px;background:linear-gradient(160deg,#0d2215,#0a1f14);
  border:1px solid rgba(201,168,76,.15);border-radius:6px;padding:14px 16px;
  font-size:.76rem;line-height:1.9;color:var(--text-dim)}
.rules-body strong{color:var(--cream)}
.rules-body p+p{margin-top:3px}

@media(max-width:740px){
  .arena{grid-template-columns:1fr}
  .right-col{position:static}
}
</style>
</head>
<body>
<div class="page">
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
  <!-- HEADER -->
  <header class="hdr">
    <div>
      <div class="game-tag">Casino · Ball Drop Edition</div>
      <h1><em>PLINKO</em> ROYALE</h1>
    </div>
    <div class="hdr-right">
      <div class="stat">
        <div class="stat-lbl">Chips</div>
        <div class="stat-val" id="balanceDisplay">&#8369;<?= number_format($balance) ?></div>
      </div>
      <form method="get">
        <button type="submit" name="reset" value="1" class="btn-reset">&#8635; Reset</button>
      </form>
    </div>
  </header>

  <div class="arena">

    <!-- LEFT COLUMN -->
    <div class="left-col">

      <div id="errorBox" class="error-box" style="display:none"></div>
      <div id="resultBanner" class="result-banner"></div>

      <!-- Board -->
      <div class="board-wrap">
        <div class="board-title">PLINKO BOARD</div>
        <canvas id="plinkoCanvas"></canvas>
      </div>

      <!-- Payout display (updated by JS per risk) -->
      <div class="panel-wrap">
        <div class="panel-inner">
          <div class="panel-title">Slot Payouts — <span id="riskLabel">Medium</span> Risk</div>
          <div class="payout-slots" id="payoutSlots"></div>
        </div>
      </div>

      <!-- Rules -->
      <div class="rules-area">
        <details>
          <summary>How to Play — Rules &amp; Payouts</summary>
          <div class="rules-body">
            <p><strong>Select risk level:</strong> Low, Medium, or High — controls multiplier distribution.</p>
            <p><strong>Set your wager:</strong> Minimum ₱10 per ball drop.</p>
            <p><strong>Number of balls:</strong> Drop 1, 2, or 3 balls simultaneously (cost = bet × balls).</p>
            <p><strong>Drop &amp; watch:</strong> Each ball bounces through 12 rows of pegs, landing in a slot at the bottom.</p>
            <p><strong>Payout:</strong> Your bet × the multiplier of the slot the ball lands in.</p>
            <p><strong>Edge slots</strong> have the highest multipliers but are the hardest to hit.</p>
            <p><strong>Center slots</strong> are most likely (bell-curve distribution) but pay the least.</p>
          </div>
        </details>
      </div>

    </div><!-- /left-col -->

    <!-- RIGHT COLUMN -->
    <div class="right-col">
      <div class="bet-wrap">
        <div class="bet-inner">
          <div class="bet-title">Place Your Bet</div>

          <div class="section-lbl">① Risk Level</div>
          <div class="risk-row">
            <button type="button" class="risk-btn" data-risk="low">Low</button>
            <button type="button" class="risk-btn sel" data-risk="medium">Medium</button>
            <button type="button" class="risk-btn" data-risk="high">High</button>
          </div>

          <div class="section-lbl">② Number of Balls</div>
          <div class="balls-row">
            <button type="button" class="ball-btn sel" data-balls="1">1 Ball</button>
            <button type="button" class="ball-btn" data-balls="2">2 Balls</button>
            <button type="button" class="ball-btn" data-balls="3">3 Balls</button>
          </div>

          <div class="section-lbl">③ Wager per Ball</div>
          <div class="wager-row">
            <span class="wager-lbl">₱</span>
            <input type="number" id="betInput" class="wager-in" value="50" min="10">
          </div>
          <div class="chips-row">
            <?php foreach ([100,250,500] as $q): ?>
            <button type="button" class="chip" data-amount="<?= $q ?>">₱<?= $q ?></button>
            <?php endforeach; ?>
            <button type="button" class="chip" data-amount="max">All In</button>
          </div>

          <div class="deal-row">
            <button id="btnDrop" class="btn-deal">&#9678; Drop the Ball</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</div><!-- /page -->

<script>
// ── CONFIG (mirrors PHP) ──
const ROWS  = 12;
const SLOTS = ROWS + 1; // 13

const SLOT_MULTIPLIERS = {
  low:    [1.5, 1.2, 1.1, 1.0, 0.5, 0.3, 0.5, 1.0, 1.1, 1.2, 1.5, 1.2, 1.5],
  medium: [5.0, 2.0, 1.5, 1.0, 0.5, 0.3, 0.5, 1.0, 1.5, 2.0, 5.0, 2.0, 5.0],
  high:   [20.0, 5.0, 2.0, 0.5, 0.3, 0.0, 0.3, 0.5, 2.0, 5.0, 20.0, 5.0, 20.0],
};

const SLOT_COLORS = {
  low:    ['#c9a84c','#9d7a30','#8a6a20','#6a5010','#3a3010','#252010','#3a3010','#6a5010','#8a6a20','#9d7a30','#c9a84c','#9d7a30','#c9a84c'],
  medium: ['#e8402a','#c9a84c','#9d7a30','#6a5010','#3a3010','#252010','#3a3010','#6a5010','#9d7a30','#c9a84c','#e8402a','#c9a84c','#e8402a'],
  high:   ['#ff2200','#e8402a','#c9a84c','#3a3010','#252010','#111111','#252010','#3a3010','#c9a84c','#e8402a','#ff2200','#e8402a','#ff2200'],
};

// ── STATE ──
let currentRisk  = 'medium';
let currentBalls = 1;
let balance      = <?= $balance ?>;
let animating    = false;

// ── CANVAS SETUP ──
const canvas = document.getElementById('plinkoCanvas');
const ctx    = canvas.getContext('2d');

// Board geometry
const BOARD_W = 520;
const BOARD_H = 480;
canvas.width  = BOARD_W;
canvas.height = BOARD_H;
canvas.style.width  = '100%';
canvas.style.maxWidth = BOARD_W + 'px';

const PEG_R   = 5;
const BALL_R  = 10;
const PAD_X   = 30;
const PAD_TOP = 30;
const PAD_BOT = 60; // slot area height

// Compute peg grid
function pegPos(row, col) {
  // row 0..11, col 0..row (inclusive)
  const cols    = row + 1;
  const totalW  = BOARD_W - 2 * PAD_X;
  const usableH = BOARD_H - PAD_TOP - PAD_BOT;
  const rowH    = usableH / (ROWS + 1);
  const colW    = totalW / (ROWS); // max cols-1 spacing
  const startX  = PAD_X + (BOARD_W - 2 * PAD_X) / 2 - (cols - 1) * colW / 2;
  const x       = startX + col * colW;
  const y       = PAD_TOP + (row + 1) * rowH;
  return { x, y };
}

// Slot center x
function slotX(slot) {
  const totalW = BOARD_W - 2 * PAD_X;
  return PAD_X + slot * (totalW / ROWS);
}

function slotTop() { return BOARD_H - PAD_BOT; }

// ── DRAW BOARD (static) ──
function drawBoard(highlightSlots = []) {
  ctx.clearRect(0, 0, BOARD_W, BOARD_H);

  // Background felt
  const felt = ctx.createLinearGradient(0, 0, 0, BOARD_H);
  felt.addColorStop(0, '#133a22');
  felt.addColorStop(1, '#0a1f14');
  ctx.fillStyle = felt;
  ctx.fillRect(0, 0, BOARD_W, BOARD_H);

  // Pegs
  for (let r = 0; r < ROWS; r++) {
    for (let c = 0; c <= r; c++) {
      const { x, y } = pegPos(r, c);
      ctx.beginPath();
      ctx.arc(x, y, PEG_R, 0, Math.PI * 2);
      ctx.fillStyle = '#c9a84c';
      ctx.shadowColor = 'rgba(201,168,76,0.5)';
      ctx.shadowBlur  = 6;
      ctx.fill();
      ctx.shadowBlur = 0;
    }
  }

  // Slots
  const colors = SLOT_COLORS[currentRisk];
  const mults  = SLOT_MULTIPLIERS[currentRisk];
  const slotW  = (BOARD_W - 2 * PAD_X) / ROWS;
  const sy     = slotTop();

  for (let s = 0; s < SLOTS; s++) {
    const sx  = slotX(s) - slotW / 2;
    const isHL = highlightSlots.includes(s);

    // Slot box
    ctx.fillStyle = colors[s] + (isHL ? 'ff' : '99');
    ctx.shadowColor = isHL ? colors[s] : 'transparent';
    ctx.shadowBlur  = isHL ? 18 : 0;
    ctx.fillRect(sx + 2, sy, slotW - 4, PAD_BOT - 6);
    ctx.shadowBlur = 0;

    // Slot border
    ctx.strokeStyle = isHL ? '#fff' : 'rgba(255,255,255,0.12)';
    ctx.lineWidth   = isHL ? 2 : 1;
    ctx.strokeRect(sx + 2, sy, slotW - 4, PAD_BOT - 6);
    ctx.lineWidth = 1;

    // Multiplier text
    const label = mults[s] + 'x';
    ctx.fillStyle = isHL ? '#fff' : 'rgba(255,255,255,0.85)';
    ctx.font      = `bold ${slotW > 38 ? 10 : 9}px 'Josefin Sans', sans-serif`;
    ctx.textAlign = 'center';
    ctx.fillText(label, slotX(s), sy + PAD_BOT / 2 + 4);
  }
}

// ── DRAW BALL ──
function drawBall(x, y, color='#e2c070') {
  const grad = ctx.createRadialGradient(x - BALL_R * 0.3, y - BALL_R * 0.3, 1, x, y, BALL_R);
  grad.addColorStop(0, '#fff8e0');
  grad.addColorStop(0.4, color);
  grad.addColorStop(1, '#5a3800');
  ctx.beginPath();
  ctx.arc(x, y, BALL_R, 0, Math.PI * 2);
  ctx.fillStyle = grad;
  ctx.shadowColor = color;
  ctx.shadowBlur  = 14;
  ctx.fill();
  ctx.shadowBlur = 0;
}

// ── ANIMATE SINGLE BALL PATH ──
function animateBall(path, slotIdx, delay, color, onDone) {
  // Build waypoints: start → each peg → final slot
  const waypoints = [];

  // Starting point (above first peg row, centered at 0 col -> but path tells us direction)
  // Actually start from center top
  waypoints.push({ x: BOARD_W / 2, y: PAD_TOP - 10 });

  let col = 0;
  for (let r = 0; r < ROWS; r++) {
    col += path[r]; // 0=left, 1=right
    const { x, y } = pegPos(r, col > 0 ? col - 1 : 0);
    // Offset slightly based on direction
    const p = pegPos(r, path[r] === 0 ? (r > 0 ? col - 1 : 0) : col);
    waypoints.push({ x: p.x, y: p.y });
  }
  // Final slot center
  waypoints.push({ x: slotX(slotIdx), y: slotTop() + PAD_BOT / 2 - 8 });

  const FPS      = 60;
  const SEG_MS   = 55; // ms per segment
  let seg        = 0;
  let startTime  = null;
  let started    = false;

  function step(ts) {
    if (!started) {
      if (ts < delay) { requestAnimationFrame(step); return; }
      startTime = ts;
      started   = true;
    }
    const elapsed = ts - startTime;
    const segElapsed = elapsed - seg * SEG_MS;
    const t = Math.min(segElapsed / SEG_MS, 1);

    const from = waypoints[seg];
    const to   = waypoints[seg + 1];
    // Ease in-out
    const e = t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
    const bx = from.x + (to.x - from.x) * e;
    const by = from.y + (to.y - from.y) * e;

    // redraw will happen in master loop; just record position
    activeBalls[color] = { x: bx, y: by };

    if (t >= 1) {
      seg++;
      if (seg >= waypoints.length - 1) {
        activeBalls[color] = { x: to.x, y: to.y };
        if (onDone) onDone();
        return;
      }
    }
    requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

// Master render loop for multiple balls
const activeBalls = {};
let renderLoop = null;

function startRenderLoop(highlightSlots) {
  function loop() {
    drawBoard(highlightSlots);
    const ballColors = Object.keys(activeBalls);
    ballColors.forEach(c => {
      const b = activeBalls[c];
      drawBall(b.x, b.y, c);
    });
    renderLoop = requestAnimationFrame(loop);
  }
  renderLoop = requestAnimationFrame(loop);
}
function stopRenderLoop() {
  if (renderLoop) { cancelAnimationFrame(renderLoop); renderLoop = null; }
}

// ── PAYOUT PANEL ──
function renderPayoutSlots() {
  const mults  = SLOT_MULTIPLIERS[currentRisk];
  const colors = SLOT_COLORS[currentRisk];
  const el     = document.getElementById('payoutSlots');
  el.innerHTML = '';
  mults.forEach((m, i) => {
    const div = document.createElement('div');
    div.className = 'ps';
    div.style.background = colors[i] + '33';
    div.style.borderColor = colors[i] + '88';
    div.style.color = '#f0e6cc';
    div.innerHTML = `<div class="psm">${m}x</div><div class="psl">S${i + 1}</div>`;
    el.appendChild(div);
  });
}

// ── RISK BUTTONS ──
document.querySelectorAll('.risk-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.risk-btn').forEach(b => b.classList.remove('sel'));
    btn.classList.add('sel');
    currentRisk = btn.dataset.risk;
    document.getElementById('riskLabel').textContent =
      currentRisk.charAt(0).toUpperCase() + currentRisk.slice(1);
    renderPayoutSlots();
    drawBoard();
  });
});

// ── BALL COUNT BUTTONS ──
document.querySelectorAll('.ball-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.ball-btn').forEach(b => b.classList.remove('sel'));
    btn.classList.add('sel');
    currentBalls = parseInt(btn.dataset.balls);
    updateDropBtn();
  });
});

function updateDropBtn() {
  const bet = parseInt(document.getElementById('betInput').value) || 0;
  const total = bet * currentBalls;
  document.getElementById('btnDrop').textContent = currentBalls > 1
    ? `⬤ Drop ${currentBalls} Balls  (₱${total.toLocaleString()} total)`
    : '⬤ Drop the Ball';
}

document.getElementById('betInput').addEventListener('input', updateDropBtn);

// ── CHIPS ──
document.querySelectorAll('.chip').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('betInput').value =
      btn.dataset.amount === 'max' ? balance : parseInt(btn.dataset.amount);
    updateDropBtn();
  });
});

// ── DROP BUTTON ──
document.getElementById('btnDrop').addEventListener('click', () => {
  if (animating) return;

  const bet  = parseInt(document.getElementById('betInput').value) || 0;
  const risk = currentRisk;
  const nballs = currentBalls;

  const errorBox = document.getElementById('errorBox');
  errorBox.style.display = 'none';

  const formData = new FormData();
  formData.append('action', 'play');
  formData.append('bet', bet);
  formData.append('risk', risk);
  formData.append('balls', nballs);

  animating = true;
  const btn = document.getElementById('btnDrop');
  btn.disabled = true;
  btn.textContent = 'Rolling…';

  fetch('', {
    method: 'POST',
    body: formData,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      errorBox.textContent = '⚠ ' + data.error;
      errorBox.style.display = 'block';
      animating = false;
      btn.disabled = false;
      updateDropBtn();
      return;
    }

    // Run animations
    const ballColorPalette = ['#e2c070', '#ff8090', '#80c8ff'];
    const slots   = data.results.map(r => r.slot);
    const paths   = data.results.map(r => r.path);

    // Clear previous balls
    Object.keys(activeBalls).forEach(k => delete activeBalls[k]);

    // Hide banner
    const banner = document.getElementById('resultBanner');
    banner.className = 'result-banner';
    banner.textContent = '';

    startRenderLoop([]);
    let done = 0;

    paths.forEach((path, i) => {
      const color = ballColorPalette[i % ballColorPalette.length];
      animateBall(path, slots[i], i * 220, color, () => {
        done++;
        if (done === paths.length) {
          // All done
          stopRenderLoop();
          drawBoard(slots);
          // Draw final positions
          paths.forEach((_, j) => {
            const c = ballColorPalette[j % ballColorPalette.length];
            drawBall(slotX(slots[j]), slotTop() + PAD_BOT / 2 - 8, c);
          });

          // Update balance
          balance = data.balance;
          document.getElementById('balanceDisplay').innerHTML =
            '₱' + balance.toLocaleString();

          // Show result
          const net = data.net;
          let cls, msg;
          if (net > 0) {
            cls = 'win';
            msg = `🎉 WIN! +₱${data.total_win.toLocaleString()} (net +₱${net.toLocaleString()})`;
          } else if (net < 0) {
            cls = 'lose';
            msg = `No luck. Lost ₱${Math.abs(net).toLocaleString()}`;
          } else {
            cls = 'push';
            msg = `Break even — ₱${data.total_win.toLocaleString()} returned`;
          }
          banner.className = 'result-banner ' + cls;
          banner.textContent = msg;
          // Trigger animation
          setTimeout(() => banner.classList.add('show'), 20);

          animating = false;
          btn.disabled = false;
          updateDropBtn();
        }
      });
    });
  })
  .catch(() => {
    errorBox.textContent = '⚠ Network error. Please try again.';
    errorBox.style.display = 'block';
    animating = false;
    btn.disabled = false;
    updateDropBtn();
  });
});

// ── INIT ──
renderPayoutSlots();
drawBoard();
updateDropBtn();
</script>
</body>
</html>