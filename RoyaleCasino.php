<?php
session_start();

if (!isset($_SESSION['chips'])) {
    $_SESSION['chips'] = 1000;
}

$chips = $_SESSION['chips'];
$bp    = $_SESSION['bp'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Royale Casino</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Josefin+Sans:wght@300;400;600&display=swap" rel="stylesheet">
<style>

/* ── Reset ───────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
a { text-decoration: none; color: inherit; }

/* ── CSS Variables ───────────────────────────────────── */
:root {
  --gold:      #c9a84c;
  --gold-lt:   #e8c96e;
  --gold-dk:   #8a6a20;
  --cream:     #f0e6cc;
  --bg:        #080f0a;
  --bg2:       #0d1a10;
  --card-bg:   #0f2016;
  --border:    rgba(201,168,76,0.25);
  --text-muted:#a09070;
}

/* ── Body & Background ───────────────────────────────── */
body {
  font-family: 'Josefin Sans', sans-serif;
  background: var(--bg);
  color: var(--cream);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  overflow-x: hidden;
  position: relative;
}

/* Subtle radial glow behind everything */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 50% at 50% -10%, rgba(201,168,76,0.10) 0%, transparent 70%),
    radial-gradient(ellipse 60% 40% at 50% 110%, rgba(20,80,40,0.25) 0%, transparent 70%);
  pointer-events: none;
  z-index: 0;
}

/* Grain texture overlay */
body::after {
  content: '';
  position: fixed;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
  pointer-events: none;
  z-index: 0;
  opacity: 0.6;
}

/* ── Header ──────────────────────────────────────────── */
header {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 22px 48px;
  background: linear-gradient(180deg, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0) 100%);
  border-bottom: 1px solid var(--border);
  position: relative;
  z-index: 10;
  backdrop-filter: blur(10px);
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo img {
  height: 36px;
  filter: drop-shadow(0 0 8px rgba(201,168,76,0.6));
}

.logo-text {
  font-family: 'Playfair Display', serif;
  font-size: 1.6rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  background: linear-gradient(135deg, var(--gold-lt) 0%, var(--gold) 50%, var(--gold-dk) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.balance-bar {
  display: flex;
  gap: 24px;
  align-items: center;
}

.balance-chip {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(201,168,76,0.08);
  border: 1px solid var(--border);
  border-radius: 50px;
  padding: 8px 20px;
  font-size: 0.95rem;
  letter-spacing: 0.05em;
}

.balance-chip .label {
  color: var(--text-muted);
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.balance-chip .value {
  color: var(--gold-lt);
  font-weight: 600;
  font-size: 1.05rem;
}

.chip-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--gold);
  box-shadow: 0 0 6px var(--gold);
  animation: pulse 2.5s ease-in-out infinite;
}

@keyframes pulse {
  0%,100% { opacity: 1; box-shadow: 0 0 6px var(--gold); }
  50%      { opacity: 0.5; box-shadow: 0 0 14px var(--gold); }
}

/* ── Hero Section ────────────────────────────────────── */
.hero {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 48px 24px 36px;
  text-align: center;
  animation: fadeDown 0.8s ease both;
}

@keyframes fadeDown {
  from { opacity: 0; transform: translateY(-20px); }
  to   { opacity: 1; transform: translateY(0); }
}

.hero-eyebrow {
  font-size: 0.75rem;
  letter-spacing: 0.35em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 18px;
}

.hero h1 {
  font-family: 'Playfair Display', serif;
  font-size: clamp(2.8rem, 6vw, 5rem);
  font-weight: 900;
  line-height: 1.05;
  background: linear-gradient(180deg, #fff 0%, var(--gold-lt) 50%, var(--gold) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 24px;
}

/* Decorative divider */
.divider {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}
.divider-line {
  width: 80px;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--gold), transparent);
}
.divider-diamond {
  width: 7px; height: 7px;
  background: var(--gold);
  transform: rotate(45deg);
}

.hero p {
  max-width: 540px;
  font-size: 1.05rem;
  line-height: 1.7;
  color: var(--text-muted);
  font-weight: 300;
  letter-spacing: 0.03em;
}

/* ── Game Grid ───────────────────────────────────────── */
.games-section {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 1100px;
  padding: 0 32px 40px;
}

.games-section h2 {
  font-family: 'Playfair Display', serif;
  font-size: 0.8rem;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 20px;
  text-align: center;
}

.game-grid {
  display: flex;
  justify-content: center;
  gap: 18px;
  flex-wrap: wrap;
}

/* ── Game Card ───────────────────────────────────────── */
.game-card {
  position: relative;
  width: 280px;
  border-radius: 20px;
  overflow: hidden;
  background: var(--card-bg);
  border: 1px solid var(--border);
  transition: transform 0.35s cubic-bezier(0.23,1,0.32,1),
              box-shadow  0.35s cubic-bezier(0.23,1,0.32,1),
              border-color 0.35s;
  animation: cardRise 0.8s ease both;
  cursor: pointer;
  box-shadow: 0 8px 32px rgba(0,0,0,0.5);
}

.game-card:nth-child(1) { animation-delay: 0.1s; }
.game-card:nth-child(2) { animation-delay: 0.22s; }
.game-card:nth-child(3) { animation-delay: 0.34s; }

@keyframes cardRise {
  from { opacity: 0; transform: translateY(30px); }
  to   { opacity: 1; transform: translateY(0); }
}

.game-card:hover {
  transform: translateY(-10px) scale(1.02);
  box-shadow: 0 24px 60px rgba(0,0,0,0.7), 0 0 40px rgba(201,168,76,0.18);
  border-color: rgba(201,168,76,0.55);
}

/* Gold shimmer line at top on hover */
.game-card::before {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 100%; height: 2px;
  background: linear-gradient(90deg, transparent, var(--gold-lt), transparent);
  transition: left 0.5s ease;
  z-index: 5;
}
.game-card:hover::before { left: 100%; }

/* Image area */
.card-img-wrap {
  position: relative;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(ellipse at 50% 60%, rgba(201,168,76,0.06), transparent 70%);
  overflow: hidden;
}

.card-img-wrap img {
  width: 55%;
  max-height: 90px;
  object-fit: contain;
  transition: transform 0.4s ease, filter 0.4s ease;
  filter: drop-shadow(0 8px 16px rgba(0,0,0,0.5));
}

.game-card:hover .card-img-wrap img {
  transform: scale(1.1) translateY(-4px);
  filter: drop-shadow(0 12px 24px rgba(201,168,76,0.3));
}

/* Card body */
.card-body {
  padding: 14px 20px 18px;
  border-top: 1px solid var(--border);
  background: linear-gradient(180deg, rgba(201,168,76,0.04) 0%, transparent 100%);
}

.card-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--cream);
  margin-bottom: 4px;
  letter-spacing: 0.03em;
}

.card-desc {
  font-size: 0.78rem;
  color: var(--text-muted);
  line-height: 1.45;
  letter-spacing: 0.03em;
  margin-bottom: 12px;
}

.card-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(135deg, rgba(201,168,76,0.15), rgba(201,168,76,0.05));
  border: 1px solid rgba(201,168,76,0.35);
  border-radius: 8px;
  padding: 10px 16px;
  font-size: 0.78rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--gold-lt);
  font-weight: 600;
  transition: background 0.3s, border-color 0.3s;
}

.game-card:hover .card-btn {
  background: linear-gradient(135deg, rgba(201,168,76,0.28), rgba(201,168,76,0.10));
  border-color: rgba(201,168,76,0.7);
}

.card-btn svg {
  width: 14px; height: 14px;
  transition: transform 0.3s;
}
.game-card:hover .card-btn svg { transform: translateX(4px); }

/* ── Footer ──────────────────────────────────────────── */
footer {
  position: relative;
  z-index: 1;
  width: 100%;
  padding: 20px 40px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: center;
  gap: 8px;
  font-size: 0.72rem;
  letter-spacing: 0.1em;
  color: rgba(160,144,112,0.5);
  text-transform: uppercase;
  margin-top: auto;
}

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 700px) {
  header { padding: 16px 20px; }
  .logo-text { font-size: 1.2rem; }
  .balance-chip { padding: 6px 14px; }
  .hero { padding: 52px 20px 40px; }
  .game-card { width: 100%; max-width: 340px; }
  .games-section { padding: 0 20px 60px; }
}
</style>
</head>
<body>

<!-- ── HEADER ─────────────────────────────────────── -->
<header>
  <div class="logo">
    <img src="chips.png" alt="Chip">
    <span class="logo-text">Royale Casino</span>
  </div>

  <div class="balance-bar">
    <div class="balance-chip">
      <div class="chip-dot"></div>
      <span class="label">Chips</span>
      <span class="value">₱<?= number_format($chips) ?></span>
    </div>
  </div>
</header>

<!-- ── HERO ───────────────────────────────────────── -->
<div class="hero">
  <p class="hero-eyebrow">Est. 2025 &nbsp;·&nbsp; Premium Gaming</p>
  <h1>Welcome to<br>Casino Royale</h1>
  <div class="divider">
    <div class="divider-line"></div>
    <div class="divider-diamond"></div>
    <div class="divider-line"></div>
  </div>
  <p>Your chips carry across every table. Place your bets wisely — fortune favors the bold.</p>
</div>

<!-- ── GAMES ──────────────────────────────────────── -->
<section class="games-section">
  <h2>Choose your game</h2>

  <div class="game-grid">

    <!-- Baccarat -->
    <a href="Baccarat.php" class="game-card">
      <div class="card-img-wrap">
        <img src="baccarat.png" alt="Baccarat">
      </div>
      <div class="card-body">
        <div class="card-title">Baccarat</div>
        <div class="card-desc">Bet on Player or Banker. The closest to 9 wins. Pure elegance under pressure.</div>
        <div class="card-btn">
          Play Now
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </div>
      </div>
    </a>

    <!-- Mines -->
    <a href="Mines.php" class="game-card">
      <div class="card-img-wrap">
        <img src="mines.png" alt="Mines">
      </div>
      <div class="card-body">
        <div class="card-title">Mines</div>
        <div class="card-desc">Reveal safe tiles and grow your multiplier — but one wrong move and it's all gone.</div>
        <div class="card-btn">
          Play Now
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </div>
      </div>
    </a>

    <!-- Plinko -->
    <a href="Plinko.php" class="game-card">
      <div class="card-img-wrap">
        <img src="plinko.png" alt="Plinko">
      </div>
      <div class="card-body">
        <div class="card-title">Plinko</div>
        <div class="card-desc">Drop the ball and watch it bounce. Land in the right slot and multiply your stack.</div>
        <div class="card-btn">
          Play Now
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14M12 5l7 7-7 7"/>
          </svg>
        </div>
      </div>
    </a>

  </div>
</section>

<!-- ── FOOTER ─────────────────────────────────────── -->
<footer>
  <span>Royale Casino</span>
  <span>·</span>
  <span>Play responsibly</span>
  <span>·</span>
  <span>18+</span>
</footer>

</body>
</html>
