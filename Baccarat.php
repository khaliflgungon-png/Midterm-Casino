<?php
session_start();

if (!isset($_SESSION['chips'])) {
    $_SESSION['chips'] = 1000.00;
}

function generateFateNumber(): int {
    return rand(1, 30);
}

function evaluateBaccarat(string $bet, int $number): array {
    if ($number % 7 === 0) {
        $winner       = 'tie';
        $resultType   = 'jackpot';
        $pointsEarned = ($bet === 'tie') ? 20 : 2;
        return [$winner, $resultType, $pointsEarned];
    }
    $winner       = ($number % 2 !== 0) ? 'player' : 'banker';
    $resultType   = ($bet === $winner)   ? 'win'    : 'lose';
    $pointsEarned = ($bet === $winner)   ? 10       : 2;
    return [$winner, $resultType, $pointsEarned];
}

function buildResultMessage(string $bet, string $winner, string $resultType, int $number, int $points): string {
    $betLabel    = ucfirst($bet);
    $winnerLabel = ucfirst($winner);
    if ($resultType === 'jackpot' && $bet === 'tie') {
        return "🎰 JACKPOT! Fate #{$number} is divisible by 7 — Tie wins! +{$points} pts!";
    }
    if ($resultType === 'jackpot') {
        return "🎰 Jackpot Tie! Fate #{$number} — You bet {$betLabel}. +{$points} consolation pts.";
    }
    if ($resultType === 'win') {
        return "🏆 {$winnerLabel} wins! Fate #{$number} — Your {$betLabel} bet is correct. +{$points} pts!";
    }
    return "💸 {$winnerLabel} wins! Fate #{$number} — You bet {$betLabel}. +{$points} consolation pts.";
}

function calculatePayout(string $bet, string $winner, string $resultType, int $wagered): int {
    if ($resultType === 'jackpot' && $bet === 'tie') return $wagered * 8;
    if ($resultType === 'jackpot')                   return -$wagered;
    if ($bet !== $winner)                            return -$wagered;
    if ($bet === 'tie')                              return $wagered * 8;
    if ($bet === 'banker')                           return (int) floor($wagered * 0.95);
    return $wagered;
}

// ═══════════════════════════════════════════════════════════════
//  SHOE SYSTEM — 52-card deck, refreshes every 8 rounds
// ═══════════════════════════════════════════════════════════════

/**
 * Build and shuffle a full 52-card Baccarat shoe.
 * Ranks: A=1, 2–9=face value, 10/J/Q/K=0
 */
function buildShoe(): array {
    $suits       = ['♥','♦','♣','♠'];
    $redSuits    = ['♥','♦'];
    $rankMap     = [
        'A'=>1, '2'=>2, '3'=>3, '4'=>4, '5'=>5,
        '6'=>6, '7'=>7, '8'=>8, '9'=>9,
        '10'=>0, 'J'=>0, 'Q'=>0, 'K'=>0,
    ];
    $deck = [];
    foreach ($suits as $suit) {
        foreach ($rankMap as $rank => $val) {
            $deck[] = [
                'rank' => $rank,
                'suit' => $suit,
                'red'  => in_array($suit, $redSuits),
                'val'  => $val,
            ];
        }
    }
    shuffle($deck);
    return $deck;
}

/**
 * Pop one card off the top of the session shoe.
 * Caller is responsible for ensuring shoe has enough cards first.
 */
function drawFromShoe(): array {
    return array_shift($_SESSION['shoe']);
}

function baccTotal(array $hand): int {
    return array_sum(array_map(fn($c) => $c['val'], $hand)) % 10;
}

/**
 * Deal a full Baccarat round from the session shoe.
 * Deal order: P1, B1, P2, B2 — then third cards per standard rules.
 * The outcome is whatever the real cards produce — fate number still
 * drives the game result; if the natural card outcome disagrees,
 * we reshuffle until it aligns (max 10 re-deals, then accept actual).
 */
function buildVisualHands(int $number, string $requiredWinner): array {
    // Defensive init — guard against sessions that predate the shoe system
    if (!isset($_SESSION['shoe']) || !is_array($_SESSION['shoe'])) {
        $_SESSION['shoe']       = buildShoe();
        $_SESSION['shoe_round'] = 0;
    }

    // Safety: ensure shoe has at least 6 cards (max possible per round)
    if (count($_SESSION['shoe']) < 6) {
        $_SESSION['shoe']        = buildShoe();
        $_SESSION['shoe_round']  = 0;
        $_SESSION['shoe_reshuffled'] = true;
    }

    // Save shoe state so we can restore if outcome doesn't match
    $savedShoe = $_SESSION['shoe'];

    for ($attempt = 0; $attempt < 50; $attempt++) {
        if ($attempt > 0) {
            // Restore shoe and re-shuffle remaining cards for next attempt
            $_SESSION['shoe'] = $savedShoe;
            shuffle($_SESSION['shoe']);
        }

        // Deal alternating: P1, B1, P2, B2
        $p = [drawFromShoe(), drawFromShoe()];
        $b = [drawFromShoe(), drawFromShoe()];

        $pTotal  = baccTotal($p);
        $bTotal  = baccTotal($b);
        $natural = ($pTotal >= 8 || $bTotal >= 8);
        $pThird  = null;
        $bThird  = null;

        if (!$natural) {
            // ── PLAYER RULE ──────────────────────────────────────
            // Total 0–5: draw a third card
            // Total 6–7: stand
            if ($pTotal <= 5) {
                $pThird = drawFromShoe();
                $p[]    = $pThird;
            }

            // ── BANKER RULE ──────────────────────────────────────
            $bTotalNow = baccTotal($b);

            if ($pThird === null) {
                // Player stood (6 or 7) — Banker uses same simple rule:
                // total 0–5: draw, total 6–7: stand
                if ($bTotalNow <= 5) {
                    $b[] = drawFromShoe();
                }
            } else {
                // Player drew a third card — use the full Banker table
                $pThirdVal = $pThird['val'];

                // Ace=1, 9=9, 10/face=0 — group by Player's third card value:
                // Player drew 2 or 3  → Banker draws on 0–4, stands on 5–7
                // Player drew 4 or 5  → Banker draws on 0–5, stands on 6–7
                // Player drew 6 or 7  → Banker draws on 0–6, stands on 7
                // Player drew 8       → Banker draws on 0–2, stands on 3–7
                // Player drew A(1), 9, 10/face(0) → Banker draws on 0–3, stands on 4–7
                if (in_array($pThirdVal, [2, 3])) {
                    $draw = ($bTotalNow <= 4);
                } elseif (in_array($pThirdVal, [4, 5])) {
                    $draw = ($bTotalNow <= 5);
                } elseif (in_array($pThirdVal, [6, 7])) {
                    $draw = ($bTotalNow <= 6);
                } elseif ($pThirdVal === 8) {
                    $draw = ($bTotalNow <= 2);
                } else {
                    // Covers: 0 (10/J/Q/K), 1 (Ace), 9
                    $draw = ($bTotalNow <= 3);
                }

                if ($draw) {
                    $b[] = drawFromShoe();
                }
            }
        }

        $pFinal = baccTotal($p);
        $bFinal = baccTotal($b);
        $actual = $pFinal > $bFinal ? 'player' : ($bFinal > $pFinal ? 'banker' : 'tie');

        if ($actual === $requiredWinner) {
            return ['player' => $p, 'banker' => $b];
        }
    }

    // All 10 attempts failed — force-build a minimal hand that guarantees the correct winner
    // Uses cards already drawn from the shoe to avoid re-using them
    $v2r = [0=>'K',1=>'A',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9'];
    $mk  = fn(int $v, string $s) => ['rank'=>$v2r[$v],'suit'=>$s,'red'=>in_array($s,['♥','♦']),'val'=>$v];

    if ($requiredWinner === 'player') {
        // Player 8, Banker 7 — natural, no third cards
        return ['player' => [$mk(8,'♠'), $mk(0,'♥')], 'banker' => [$mk(4,'♦'), $mk(3,'♣')]];
    } elseif ($requiredWinner === 'banker') {
        // Banker 8, Player 7 — natural, no third cards
        return ['player' => [$mk(4,'♠'), $mk(3,'♥')], 'banker' => [$mk(8,'♦'), $mk(0,'♣')]];
    } else {
        // Tie — both get score 7 (natural stand, no third cards)
        return ['player' => [$mk(4,'♠'), $mk(3,'♥')], 'banker' => [$mk(4,'♦'), $mk(3,'♣')]];
    }
}

// ═══════════════════════════════════════════════════════════════
//  SESSION INIT
// ═══════════════════════════════════════════════════════════════

// Force-reset any session that predates the shoe system
if (!isset($_SESSION['_v'])) {
    $_SESSION['_v'] = 2;
}

if (!isset($_SESSION['shoe'])) {
    $_SESSION['score']           = 0;
    $_SESSION['road']            = [];
    $_SESSION['message']         = '';
    $_SESSION['lastGame']        = null;
    $_SESSION['shoe']            = buildShoe();
    $_SESSION['shoe_round']      = 0;
    $_SESSION['shoe_reshuffled'] = false;
}

// Belt-and-suspenders: ensure shoe keys always exist
if (!isset($_SESSION['shoe']) || !is_array($_SESSION['shoe'])) {
    $_SESSION['shoe']        = buildShoe();
    $_SESSION['shoe_round']  = 0;
    $_SESSION['shoe_reshuffled'] = false;
}
if (!isset($_SESSION['shoe_round']))      $_SESSION['shoe_round']      = 0;
if (!isset($_SESSION['shoe_reshuffled'])) $_SESSION['shoe_reshuffled'] = false;

// ═══════════════════════════════════════════════════════════════
//  HANDLE POST
// ═══════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'deal') {
        $bet   = $_POST['bc']    ?? 'player';   // radio input — always reliable
        $wager = max(1, min((int)($_POST['wager'] ?? 50), $_SESSION['chips']));

        if (!in_array($bet, ['player','banker','tie'])) {
            $_SESSION['message'] = 'Invalid bet selection.';
        } else {
            // Reshuffle shoe after every 8 rounds or if too few cards remain
            $_SESSION['shoe_reshuffled'] = false;
            if ($_SESSION['shoe_round'] >= 8 || count($_SESSION['shoe']) < 6) {
                $_SESSION['shoe']        = buildShoe();
                $_SESSION['shoe_round']  = 0;
                $_SESSION['shoe_reshuffled'] = true;
            }

            $fateNumber = generateFateNumber();
            [$winner, $resultType, $pointsEarned] = evaluateBaccarat($bet, $fateNumber);

            $payout = calculatePayout($bet, $winner, $resultType, $wager);
            $_SESSION['chips'] += $payout;
            if ($_SESSION['chips'] < 0) $_SESSION['chips'] = 0;

            $_SESSION['score'] += $pointsEarned;
            $hands   = buildVisualHands($fateNumber, $winner);
            $message = buildResultMessage($bet, $winner, $resultType, $fateNumber, $pointsEarned);

            $_SESSION['shoe_round']++;

            $_SESSION['lastGame'] = compact(
                'hands','winner','resultType','bet','wager',
                'payout','fateNumber','pointsEarned','message'
            );

            $_SESSION['road'][] = $winner;
            if (count($_SESSION['road']) > 60) array_shift($_SESSION['road']);

            $_SESSION['message'] = $message;
        }
    }

    if ($action === 'bonus_bet') {
        $ptsBet   = max(10, min((int)($_POST['pts_wager'] ?? 100), $_SESSION['score']));
        $bonusBet = $_POST['bet'] ?? 'player';

        if (!in_array($bonusBet, ['player','banker','tie'])) {
            $_SESSION['message'] = 'Invalid bet selection.';
        } elseif ($_SESSION['score'] < 10) {
            $_SESSION['message'] = '⚠️ Need at least 10 bonus points to place a bonus bet.';
        } else {
            // Reshuffle shoe if needed
            $_SESSION['shoe_reshuffled'] = false;
            if ($_SESSION['shoe_round'] >= 8 || count($_SESSION['shoe']) < 6) {
                $_SESSION['shoe']        = buildShoe();
                $_SESSION['shoe_round']  = 0;
                $_SESSION['shoe_reshuffled'] = true;
            }

            $fateNumber = generateFateNumber();
            [$winner, $resultType, $pointsEarned] = evaluateBaccarat($bonusBet, $fateNumber);

            // Deduct points (always)
            $_SESSION['score'] -= $ptsBet;
            if ($_SESSION['score'] < 0) $_SESSION['score'] = 0;

            // Add consolation points for losing bonus bets too
            $_SESSION['score'] += $pointsEarned;

            // Chip payout on win
            $chipsWon = 0;
            if ($resultType === 'win') {
                $chipsWon = $bonusBet === 'banker' ? (int) floor($ptsBet * 0.95) : $ptsBet;
                $_SESSION['chips'] += $chipsWon;
            } elseif ($resultType === 'jackpot' && $bonusBet === 'tie') {
                $chipsWon = $ptsBet * 8;
                $_SESSION['chips'] += $chipsWon;
            }

            // Build visual hands from shoe
            $hands = buildVisualHands($fateNumber, $winner);

            $_SESSION['shoe_round']++;

            $winnerLabel = ucfirst($winner);
            $message = $chipsWon > 0
                ? "🌟 Bonus Bet — {$winnerLabel} wins! Fate #{$fateNumber} — Spent {$ptsBet} pts → +\${$chipsWon} chips!"
                : "💫 Bonus Bet — {$winnerLabel} wins! Fate #{$fateNumber} — Spent {$ptsBet} pts. No chip gain.";

            $_SESSION['lastGame'] = [
                'hands'        => $hands,
                'winner'       => $winner,
                'resultType'   => $resultType,
                'bet'          => $bonusBet,
                'wager'        => 0,
                'payout'       => $chipsWon,
                'fateNumber'   => $fateNumber,
                'pointsEarned' => $pointsEarned,
                'message'      => $message,
            ];

            $_SESSION['road'][] = $winner;
            if (count($_SESSION['road']) > 60) array_shift($_SESSION['road']);

            $_SESSION['message'] = $message;
        }
    }

    if ($action === 'reset') {
      $_SESSION['chips'] = 1000.00;
        session_destroy();
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }

    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  RENDER PREP
// ═══════════════════════════════════════════════════════════════

$chips          = $_SESSION['chips'];
$score          = $_SESSION['score'];
$road           = $_SESSION['road'] ?? [];
$message        = $_SESSION['message'];
$lastGame       = $_SESSION['lastGame'];
$shoeRemaining  = count($_SESSION['shoe'] ?? []);
$shoeRound      = $_SESSION['shoe_round'] ?? 0;
$shoeReshuffled = $_SESSION['shoe_reshuffled'] ?? false;
$_SESSION['message']         = '';
$_SESSION['lastGame']        = null;
$_SESSION['shoe_reshuffled'] = false;

function renderCard(array $card, int $idx = 0, string $hand = 'p'): string {
    $col     = $card['red'] ? 'red' : 'blk';
    // Alternating deal: P1=0s, B1=0.5s, P2=1s, B2=1.5s, 3rd cards=2s
    $delays  = ['p0'=>0, 'b0'=>0.5, 'p1'=>1, 'b1'=>1.5, 'p2'=>2, 'b2'=>2];
    $key     = $hand . $idx;
    $delay   = $delays[$key] ?? 2;
    $isThird = ($idx === 2) ? ' third-card' : '';
    return "<div class='card {$col} face-down{$isThird}' style='animation-delay:{$delay}s'>
        <div class='card-back'></div>
        <div class='card-front'>
          <span class='r top'>{$card['rank']}</span>
          <span class='s mid'>{$card['suit']}</span>
          <span class='r bot'>{$card['rank']}</span>
        </div>
    </div>";
}

function handScore(array $hand): int {
    return array_sum(array_map(fn($c) => $c['val'] ?? 0, $hand)) % 10;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Baccarat Royale</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Josefin+Sans:wght@300;400;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --deep-green:   #0a1f14;
  --felt-green:   #0d2b1a;
  --table-green:  #133a22;
  --rich-green:   #1a4d2e;
  --gold:         #c9a84c;
  --gold-light:   #e2c070;
  --gold-pale:    #f0d98a;
  --cream:        #f5edd8;
  --cream-dim:    #d6c9a8;
  --burgundy:     #7a1e2e;
  --crimson:      #a02030;
  --text-primary: #f0e6cc;
  --text-dim:     #a89870;
}

html{font-size:17px;}
body{
  background-color:var(--deep-green);
  background-image:
    radial-gradient(ellipse at 50% 0%,rgba(26,77,46,0.6) 0%,transparent 60%),
    repeating-linear-gradient(45deg,transparent,transparent 20px,rgba(10,31,20,0.3) 20px,rgba(10,31,20,0.3) 21px);
  color:var(--text-primary);
  font-family:'Josefin Sans',sans-serif;
  min-height:100vh;overflow-x:hidden;line-height:1.55;
}
.page{position:relative;z-index:1;max-width:1200px;margin:0 auto;padding:0 22px 48px;}

/* ── HEADER ── */
.hdr{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;border-bottom:1px solid rgba(201,168,76,.25);padding:28px 0 16px;margin-bottom:24px;}
.game-tag{font-size:.62rem;letter-spacing:.25em;text-transform:uppercase;color:var(--text-dim);margin-bottom:3px;}
h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4.2vw,3rem);line-height:1;color:var(--gold);letter-spacing:.08em;text-transform:uppercase;}
h1 em{color:var(--cream);font-style:normal;font-weight:400;}
.hdr-right{display:flex;gap:20px;align-items:flex-end;}
.stat{text-align:right;}
.stat-lbl{font-size:.62rem;letter-spacing:.2em;text-transform:uppercase;color:var(--text-dim);}
.stat-val{font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:600;line-height:1.1;color:var(--gold-light);}
.stat-val.pts{color:var(--gold-light);}
.btn-reset{background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);color:var(--gold-light);padding:6px 13px;font-family:'Josefin Sans',sans-serif;font-size:.62rem;letter-spacing:.15em;text-transform:uppercase;cursor:pointer;transition:.2s;white-space:nowrap;border-radius:5px;}
.btn-reset:hover{background:rgba(201,168,76,.18);border-color:var(--gold);color:var(--gold-pale);}

/* ── TWO-COLUMN ── */
.arena{display:grid;grid-template-columns:1fr 370px;gap:22px;align-items:start;}

/* ── BANNER ── */
.banner{padding:12px 18px;margin-bottom:16px;border:1px solid rgba(201,168,76,.3);background:linear-gradient(160deg,#122a1a,#0d2215);font-size:.88rem;letter-spacing:.02em;line-height:1.5;position:relative;animation:slideIn .3s ease both;border-radius:6px;color:var(--cream);}
.banner.win  {border-color:rgba(26,92,48,.8);background:linear-gradient(160deg,#0f3a1e,#0a2714);color:var(--gold-light);}
.banner.lose {border-color:rgba(160,32,48,.7);background:linear-gradient(160deg,#2a0a10,#1a0508);color:#ff8090;}
.banner.jp   {border-color:rgba(201,168,76,.6);background:linear-gradient(160deg,#1a1400,#0f0d00);color:var(--gold-pale);}
.banner.info {border-color:rgba(201,168,76,.4);background:linear-gradient(160deg,#122a1a,#0d2215);color:var(--gold-light);}
.banner .pts-badge{display:inline-block;background:rgba(201,168,76,.2);border:1px solid rgba(201,168,76,.4);color:var(--gold-pale);font-size:.62rem;letter-spacing:.12em;padding:2px 8px;margin-left:8px;vertical-align:middle;border-radius:3px;}
@keyframes slideIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}

/* ── FATE REVEAL ── */
.fate-reveal{display:flex;align-items:center;gap:16px;margin-bottom:16px;padding:14px 18px;background:linear-gradient(135deg,#0a1f14,#0d2b1a);border:1px solid rgba(201,168,76,.25);border-radius:6px;}
.fate-lbl{font-size:.6rem;letter-spacing:.28em;text-transform:uppercase;color:var(--text-dim);}
.fate-num{font-family:'Cormorant Garamond',serif;font-size:3rem;line-height:1;font-weight:700;color:var(--gold);}
.fate-rules{display:flex;flex-direction:column;gap:3px;font-size:.7rem;letter-spacing:.04em;color:var(--text-dim);border-left:1px solid rgba(201,168,76,.15);padding-left:14px;margin-left:2px;}
.fate-rules .on{color:var(--gold-light);}

/* ── TABLE ── */
.tbl-wrap{border:1px solid rgba(201,168,76,.25);border-radius:8px;position:relative;overflow:hidden;}
.tbl-inner{background:linear-gradient(160deg,var(--table-green),var(--felt-green));padding:36px 24px 40px;}
.hands{display:grid;grid-template-columns:1fr auto 1fr;gap:16px;align-items:center;}
.hand{text-align:center;}
.h-name{font-family:'Cormorant Garamond',serif;font-size:1.45rem;font-weight:600;color:var(--cream-dim);margin-bottom:6px;}
.h-score{display:inline-block;border:1px solid rgba(201,168,76,.2);padding:2px 16px;border-radius:3px;font-size:.9rem;color:var(--text-dim);margin-bottom:18px;}
.hand.win .h-name{color:var(--gold-light);}
.hand.win .h-score{border-color:var(--gold);color:var(--gold-light);}
.w-tag{font-size:.6rem;letter-spacing:.18em;text-transform:uppercase;color:var(--gold);min-height:14px;margin-bottom:4px;}
.cards{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}
.vs-div{font-family:'Cormorant Garamond',serif;font-size:.8rem;letter-spacing:.18em;color:rgba(201,168,76,.2);text-align:center;padding:0 8px;}
.empty-felt{grid-column:1/-1;text-align:center;padding:60px 0;font-size:.7rem;letter-spacing:.2em;text-transform:uppercase;color:rgba(201,168,76,.2);font-style:italic;}

/* ── CARD FLIP ── */
.card{width:76px;height:114px;perspective:600px;position:relative;cursor:default;}
.card-inner{width:100%;height:100%;position:relative;transform-style:preserve-3d;transition:transform .55s cubic-bezier(.4,0,.2,1);}
.card.face-down .card-inner{transform:rotateY(180deg);}
.card:not(.face-down) .card-inner{transform:rotateY(0deg);}
.card-front,.card-back{position:absolute;inset:0;backface-visibility:hidden;border-radius:6px;display:flex;align-items:center;justify-content:center;}
.card-front{background:#faf6ed;border:1px solid var(--cream-dim);box-shadow:2px 3px 10px rgba(0,0,0,.4);flex-direction:column;}
.card.red .card-front{color:var(--crimson);}
.card.blk .card-front{color:var(--deep-green);}
.card-back{background:linear-gradient(145deg,var(--rich-green),var(--felt-green));border:2px solid rgba(201,168,76,.35);transform:rotateY(180deg);overflow:hidden;}
.card-back::before{content:'';position:absolute;inset:6px;border:1px solid rgba(201,168,76,.2);border-radius:3px;background:repeating-linear-gradient(45deg,rgba(201,168,76,.06) 0,rgba(201,168,76,.06) 1px,transparent 1px,transparent 8px);}
.r{position:absolute;font-family:'Cormorant Garamond',serif;font-size:1.1rem;line-height:1;font-weight:700;}
.r.top{top:5px;left:7px;}
.r.bot{bottom:5px;right:7px;transform:rotate(180deg);}
.s.mid{font-size:2.6rem;line-height:1;}
@keyframes dealIn{from{opacity:0;transform:translateY(-40px) scale(.85)}to{opacity:1;transform:none}}
.card{animation:dealIn .35s cubic-bezier(.34,1.2,.64,1) both;}
.card.third-card .card-front{border-top:3px solid var(--gold);}

/* ── SHOE STATUS BAR ── */
.shoe-bar{display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:9px 16px;margin-bottom:20px;border:1px solid rgba(201,168,76,.15);background:linear-gradient(160deg,#0d2215,#0a1f14);border-radius:6px;font-size:.6rem;letter-spacing:.14em;text-transform:uppercase;color:var(--text-dim);}
.shoe-bar .shoe-seg{display:flex;align-items:center;gap:7px;}
.shoe-pip{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:var(--rich-green);color:var(--gold);font-family:'Cormorant Garamond',serif;font-size:.8rem;font-weight:700;}
.shoe-dots{display:flex;gap:3px;align-items:center;}
.shoe-dot{width:7px;height:7px;border-radius:50%;background:rgba(201,168,76,.15);}
.shoe-dot.used{background:rgba(201,168,76,.25);opacity:.5;}
.shoe-dot.current{background:var(--gold);}

/* ── ROAD MAP ── */
.road-wrap{border:1px solid rgba(201,168,76,.2);margin-bottom:16px;border-radius:8px;overflow:hidden;}
.road-head{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(201,168,76,.15);padding:9px 15px;background:linear-gradient(135deg,#122a1a,#0d2215);}
.road-head span{font-size:.6rem;letter-spacing:.2em;text-transform:uppercase;color:var(--text-dim);}
.road-grid{background:linear-gradient(160deg,var(--table-green),var(--felt-green));padding:12px 14px;display:flex;flex-direction:column;gap:5px;overflow-x:auto;}
.road-row{display:flex;gap:5px;}
.bead{width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-family:'Josefin Sans',sans-serif;}
.bead.P{background:#1a3a6a;color:#b5d4f4;border:1.5px solid rgba(201,168,76,.3);}
.bead.B{background:var(--burgundy);color:#f7c1c1;border:1.5px solid rgba(201,168,76,.3);}
.bead.T{background:var(--rich-green);color:var(--gold-pale);border:1.5px solid rgba(201,168,76,.3);}
.bead.empty{background:rgba(201,168,76,.04);border:1px solid rgba(201,168,76,.08);}
.road-legend{display:flex;gap:12px;padding:8px 14px;border-top:1px solid rgba(201,168,76,.08);}
.road-legend span{font-size:.58rem;color:var(--text-dim);display:flex;align-items:center;gap:5px;}
.road-legend i{width:12px;height:12px;border-radius:50%;display:inline-block;}
.road-legend i.p{background:#1a3a6a;}
.road-legend i.b{background:var(--burgundy);}
.road-legend i.t{background:var(--rich-green);}

/* ── RIGHT COLUMN ── */
.right-col{position:sticky;top:20px;display:flex;flex-direction:column;gap:16px;}

/* ── PANEL BASE ── */
.bet-wrap,.bonus-wrap{background:linear-gradient(160deg,#122a1a,#0d2215);border:1px solid rgba(201,168,76,.2);border-radius:10px;position:relative;}
.bet-inner,.bonus-inner{padding:20px 18px;}
.panel-title,.bonus-title{font-size:.6rem;letter-spacing:.3em;text-transform:uppercase;color:var(--gold);margin-bottom:14px;border-bottom:1px solid rgba(201,168,76,.15);padding-bottom:8px;}

/* BET BUTTONS */
.bet-opts{display:flex;flex-direction:column;gap:8px;margin-bottom:14px;}
.bet-btn{background:rgba(0,0,0,.25);border:1px solid rgba(201,168,76,.2);padding:11px 14px;cursor:pointer;text-align:left;transition:.18s;position:relative;display:flex;align-items:center;gap:12px;border-radius:6px;}
.bet-btn input{position:absolute;opacity:0;width:0;height:0;}
.b-name{font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:600;color:var(--cream);white-space:nowrap;min-width:58px;}
.bet-btn.tie-btn{border-color:rgba(201,168,76,.35);background:rgba(201,168,76,.06);}
.bet-btn.tie-btn .b-name{color:var(--gold-light);}
.bet-btn.tie-btn.sel{background:rgba(201,168,76,.18);border-color:var(--gold);}
.bet-btn.tie-btn.sel .b-name{color:var(--gold-pale);}
.bet-btn.tie-btn.sel .b-hint{color:rgba(240,217,138,.6);}
.b-hint{font-size:.62rem;letter-spacing:.04em;color:var(--text-dim);line-height:1.4;}
.bet-btn:hover{border-color:rgba(201,168,76,.5);background:rgba(201,168,76,.08);}
.bet-btn.sel{border-color:var(--gold);background:rgba(201,168,76,.15);}
.bet-btn.sel .b-name{color:var(--gold-pale);}
.bet-btn.sel .b-hint{color:rgba(240,217,138,.55);}

/* CONFIRM BOX */
.confirm-box{display:none;margin-bottom:12px;padding:9px 13px;background:rgba(0,0,0,.3);border:1px solid rgba(201,168,76,.25);font-size:.75rem;line-height:1.5;border-radius:5px;color:var(--cream-dim);}
.confirm-box.show{display:block;}
.confirm-box strong{color:var(--gold-light);}

/* WAGER */
.wager-row,.bonus-wager-row{display:flex;align-items:center;gap:9px;margin-bottom:10px;}
.wager-lbl{font-size:.6rem;letter-spacing:.2em;text-transform:uppercase;color:var(--text-dim);white-space:nowrap;}
.wager-in,.bonus-wager-in{flex:1;border:1px solid rgba(201,168,76,.25);background:rgba(0,0,0,.3);font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--cream);padding:8px 12px;outline:none;width:100%;border-radius:6px;transition:border-color .2s;}
.wager-in:focus,.bonus-wager-in:focus{border-color:var(--gold);}
.chips-row{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:4px;}
.chip{background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);color:var(--gold-light);padding:5px 16px;font-family:'Josefin Sans',sans-serif;font-size:.80rem;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;transition:.15s;border-radius:5px;}
.chip:hover{background:rgba(201,168,76,.18);border-color:var(--gold);color:var(--gold-pale);}

/* BUTTONS */
.deal-row{display:flex;gap:8px;margin-top:13px;}
.btn-preview{flex:0 0 auto;background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);color:var(--gold-light);font-family:'Josefin Sans',sans-serif;font-size:.62rem;letter-spacing:.1em;text-transform:uppercase;padding:11px 12px;cursor:pointer;transition:.18s;white-space:nowrap;border-radius:6px;}
.btn-preview:hover{background:rgba(201,168,76,.18);border-color:var(--gold);color:var(--gold-pale);}
.btn-deal{flex:1;background:linear-gradient(135deg,var(--gold),#a07a28);border:none;color:var(--deep-green);font-family:'Josefin Sans',sans-serif;font-size:.78rem;font-weight:600;letter-spacing:.25em;text-transform:uppercase;padding:13px 8px;cursor:pointer;transition:.2s;border-radius:7px;box-shadow:0 4px 16px rgba(201,168,76,.3);}
.btn-deal:hover:not(:disabled){background:linear-gradient(135deg,var(--gold-light),var(--gold));box-shadow:0 6px 22px rgba(201,168,76,.45);transform:translateY(-1px);}
.btn-deal > span{position:relative;z-index:1;}
.btn-deal:disabled{opacity:.4;cursor:not-allowed;transform:none;}

/* ── BONUS BET PANEL ── */
.bonus-desc{font-size:.72rem;color:var(--text-dim);margin-bottom:12px;line-height:1.6;}
.bonus-desc strong{color:var(--cream);}
.bonus-pts-avail{font-size:.82rem;margin-bottom:12px;color:var(--text-dim);}
.bonus-pts-avail strong{color:var(--gold-light);font-size:1rem;}
.bonus-bet-opts{display:flex;gap:6px;margin-bottom:10px;}
.bb-btn{flex:1;background:rgba(0,0,0,.25);border:1px solid rgba(201,168,76,.2);color:var(--text-dim);padding:8px 4px;cursor:pointer;text-align:center;transition:.15s;font-family:'Josefin Sans',sans-serif;font-size:.65rem;letter-spacing:.06em;border-radius:5px;}
.bb-btn:hover{border-color:rgba(201,168,76,.5);color:var(--cream);}
.bb-btn.sel{background:rgba(201,168,76,.15);border-color:var(--gold);color:var(--gold-light);font-weight:600;}
.btn-bonus{width:100%;background:linear-gradient(135deg,var(--rich-green),#0f3d1e);border:1px solid rgba(201,168,76,.4);color:var(--gold-light);font-family:'Josefin Sans',sans-serif;font-size:.72rem;font-weight:600;letter-spacing:.2em;text-transform:uppercase;padding:12px 8px;cursor:pointer;transition:.2s;border-radius:7px;box-shadow:0 4px 16px rgba(26,92,48,.4);}
.btn-bonus:hover:not(:disabled){background:linear-gradient(135deg,#216b38,#174d26);transform:translateY(-1px);}
.btn-bonus:disabled{opacity:.35;cursor:not-allowed;transform:none;}
.bonus-note{font-size:.62rem;color:var(--text-dim);margin-top:8px;line-height:1.5;}

/* ── RULES ── */
.rules-area{margin-top:16px;}
summary{font-size:.6rem;letter-spacing:.22em;text-transform:uppercase;color:var(--text-dim);cursor:pointer;padding:5px 0;display:inline-block;}
summary:hover{color:var(--gold-light);}
.rules-body{margin-top:10px;background:linear-gradient(160deg,#0d2215,#0a1f14);border:1px solid rgba(201,168,76,.15);border-radius:6px;padding:15px 18px;font-size:.78rem;line-height:1.9;color:var(--text-dim);}
.rules-body strong{color:var(--cream);}

@media(max-width:760px){
  .arena{grid-template-columns:1fr;}
  .right-col{position:static;}
  .hands{grid-template-columns:1fr;gap:20px;}
  .vs-div{display:none;}
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
      <div class="game-tag">Casino · Pattern Edition</div>
      <h1><em>BAC</em>CARAT <em>ROYALE</em></h1>
    </div>
    <div class="hdr-right">
      <div class="stat">
        <div class="stat-lbl">BP</div>
        <div class="stat-val pts"><?= number_format($score) ?> pts</div>
      </div>
      <div class="stat">
        <div class="stat-lbl">Chips</div>
        <div class="stat-val" id="balance-Display">₱<?= number_format($chips) ?></div>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="reset">
        <button type="submit" class="btn-reset" onclick="return confirm('Reset to $1,000?')">&#8635; Reset</button>
      </form>
    </div>
  </header>

  <!-- Shoe status bar -->
  <div class="shoe-bar">
    <div class="shoe-seg">
      <span class="shoe-pip">♠</span>
      <span>Shoe</span>
    </div>
    <div class="shoe-seg">
      <span><?= $shoeRemaining ?> / 52 cards remaining</span>
    </div>
    <div class="shoe-seg">
      <span>Round <?= $shoeRound ?> / 8</span>
      <div class="shoe-dots">
        <?php for ($i=1;$i<=8;$i++):
          $cls = $i < $shoeRound ? 'used' : ($i === $shoeRound ? 'current' : '');
        ?>
        <div class="shoe-dot <?=$cls?>"></div>
        <?php endfor; ?>
      </div>
    </div>
    <?php if ($shoeReshuffled): ?>
    <div class="shoe-seg" style="color:var(--gold);letter-spacing:.1em;">
      ↺ New shoe shuffled
    </div>
    <?php endif; ?>
  </div>

  <div class="arena">

    <!-- ═══ LEFT ═══ -->
    <div class="left-col">

      <!-- Banner -->
      <?php if ($message):
        $bc = 'lose';
        if ($lastGame) {
            if ($lastGame['resultType']==='jackpot' && $lastGame['bet']==='tie') $bc='jp';
            elseif ($lastGame['resultType']==='win') $bc='win';
            elseif ($lastGame['resultType']==='jackpot') $bc='lose';
        } elseif (strpos($message,'🌟')!==false || strpos($message,'💫')!==false) $bc='info';
      ?>
      <div class="banner <?=$bc?>">
        <?= htmlspecialchars($message) ?>
        <?php if ($lastGame && $lastGame['pointsEarned'] > 0): ?>
          <span class="pts-badge"><span>+<?=$lastGame['pointsEarned']?> pts</span></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Fate reveal -->
      <?php if ($lastGame):
        $fn    = $lastGame['fateNumber'];
        $isJP  = ($fn % 7 === 0);
        $isOdd = (!$isJP && $fn % 2 !== 0);
        $isEv  = (!$isJP && $fn % 2 === 0);
      ?>
      <div class="fate-reveal">
        <div>
          <div class="fate-lbl">Fate Number</div>
          <div class="fate-num"><?= $fn ?></div>
        </div>
        <div class="fate-rules">
          <span class="<?=$isJP ?'on':''?>">÷ 7 → Jackpot Tie</span>
          <span class="<?=$isOdd?'on':''?>">Odd → Player wins</span>
          <span class="<?=$isEv ?'on':''?>">Even → Banker wins</span>
        </div>
      </div>
      <?php endif; ?>

      <!-- Card table -->
      <div class="tbl-wrap">
        <div class="tbl-inner">
          <div class="hands">
            <?php if ($lastGame): ?>
              <?php foreach (['player'=>'Player','banker'=>'Banker'] as $k=>$lbl):
                $isW  = ($lastGame['winner']===$k);
                $sc   = handScore($lastGame['hands'][$k]);
                $hKey = $k==='player' ? 'p' : 'b';
              ?>
              <div class="hand <?=$isW?'win':''?>">
                <div class="w-tag"><?=$isW?'★ winner':''?></div>
                <div class="h-name"><?=$lbl?></div>
                <div class="h-score"><?=$sc?></div>
                <div class="cards">
                  <?php foreach($lastGame['hands'][$k] as $i=>$c) echo renderCard($c, $i, $hKey); ?>
                </div>
              </div>
              <?php if($k==='player'):?><div class="vs-div">VS</div><?php endif;?>
              <?php endforeach;?>
            <?php else: ?>
              <div class="empty-felt">Place your bet &amp; deal to begin</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Road map (bead road) -->
      <?php if (!empty($road)):
        $rows = 6;
        $cols = max((int) ceil(count($road) / $rows), 10);
        $grid = array_fill(0, $rows, array_fill(0, $cols, null));
        foreach ($road as $idx => $w) {
            $grid[$idx % $rows][(int)floor($idx / $rows)] = $w;
        }
      ?>
      <div class="road-wrap">
        <div class="road-head">
          <span>Bead road</span>
          <span><?= count($road) ?> rounds</span>
        </div>
        <div class="road-grid">
          <?php for ($r=0;$r<$rows;$r++): ?>
          <div class="road-row">
            <?php for ($c=0;$c<$cols;$c++):
              $w = $grid[$r][$c] ?? null;
              if ($w==='player')     { $cls='P'; $lbl='P'; }
              elseif ($w==='banker') { $cls='B'; $lbl='B'; }
              elseif ($w==='tie')    { $cls='T'; $lbl='T'; }
              else                   { $cls='empty'; $lbl=''; }
            ?>
            <div class="bead <?=$cls?>"><?=$lbl?></div>
            <?php endfor; ?>
          </div>
          <?php endfor; ?>
        </div>
        <div class="road-legend">
          <span><i class="p"></i>Player</span>
          <span><i class="b"></i>Banker</span>
          <span><i class="t"></i>Tie</span>
        </div>
      </div>
      <?php endif; ?>

      <!-- Rules -->
      <div class="rules-area">
        <details>
          <summary>How to play — Rules &amp; Payouts</summary>
          <div class="rules-body">
            <p><strong>Fate number:</strong> Each round a random number 1–30 decides the outcome.</p>
            <p><strong>Odd:</strong> Player wins → +10 pts, 1:1 chips.</p>
            <p><strong>Even:</strong> Banker wins → +10 pts, 0.95:1 chips.</p>
            <p><strong>Divisible by 7</strong> (7,14,21,28): Jackpot Tie → +20 pts, 8:1 chips.</p>
            <p><strong>Consolation:</strong> Any loss earns +2 pts.</p>
            <p><strong>Natural:</strong> If either hand totals 8 or 9 from the first two cards, the game ends immediately — no third cards.</p>
            <p><strong>Player third card:</strong> Total 0–5 → draws. Total 6–7 → stands.</p>
            <p><strong>Banker third card (Player stood):</strong> Total 0–5 → draws. Total 6–7 → stands.</p>
            <p><strong>Banker third card (Player drew):</strong> Player drew 2–3 → Banker draws on 0–4. Player drew 4–5 → draws on 0–5. Player drew 6–7 → draws on 0–6. Player drew 8 → draws on 0–2. Player drew A/9/10/face → draws on 0–3.</p>
            <p><strong>Bonus bet:</strong> Spend bonus points as a wager (1 pt = $1). Win and chips are added; lose and only points are spent.</p>
            <p><strong>Bead road:</strong> Grid tracks past winners — blue = Player, red = Banker, green = Tie.</p>
          </div>
        </details>
      </div>

    </div><!-- /left-col -->

    <!-- ═══ RIGHT ═══ -->
    <div class="right-col">

      <!-- Bet panel -->
      <?php if ($chips > 0): ?>
      <div class="bet-wrap">
        <div class="bet-inner">
          <div class="panel-title">Place Your Bet</div>
          <form method="post" id="betForm">
            <input type="hidden" name="action" value="deal">
            <input type="hidden" name="bet" id="betInput" value="player">

            <div class="bet-opts">
              <label class="bet-btn sel" id="lbl-player">
                <input type="radio" name="bc" value="player" checked>
                <span class="b-name">Player</span>
                <span class="b-hint">Odd fate → +10 pts · 1:1</span>
              </label>
              <label class="bet-btn tie-btn" id="lbl-tie">
                <input type="radio" name="bc" value="tie">
                <span class="b-name">Tie 🎰</span>
                <span class="b-hint">Fate ÷7 → +20 pts · 8:1</span>
              </label>
              <label class="bet-btn" id="lbl-banker">
                <input type="radio" name="bc" value="banker">
                <span class="b-name">Banker</span>
                <span class="b-hint">Even fate → +10 pts · 0.95:1 (5% commision)</span>
              </label>
            </div>

            <div id="confirm-box" class="confirm-box">
              Bet: <strong id="cn-bet">Player</strong> · Wager: <strong id="cn-wager">$50</strong>
            </div>

            <div class="wager-row">
              <span class="wager-lbl">Wager</span>
              <input type="number" name="wager" id="wager" class="wager-in"
                     min="1" max="<?=$chips?>" value="<?=min(50,$chips)?>" required>
            </div>
            <div class="chips-row">
              <?php foreach([100,250, 500] as $c): if($c<=$chips): ?>
                <button type="button" class="chip" onclick="setWager(<?=$c?>)">$<?=$c?></button>
              <?php endif; endforeach; ?>
              <button type="button" class="chip" onclick="setWager(<?=$chips?>)">All in</button>
            </div>

            <div class="deal-row">
              <button type="button" class="btn-preview" onclick="previewBet()">Preview</button>
              <button type="submit" class="btn-deal" id="btnDeal">
                <span>Deal Cards</span>
              </button>
            </div>
          </form>
        </div>
      </div>
      <?php else: ?>
      <div class="bet-wrap">
        <div class="bet-inner" style="text-align:center;padding:26px 18px">
          <div style="font-family:'DM Serif Display',serif;font-size:1.2rem;color:var(--dim);margin-bottom:14px;font-style:italic">
            Out of chips…<br>but never out of luck.
          </div>
          <form method="post">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="btn-deal"><span>New Session — $1,000</span></button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- Bonus Bet panel -->
      <div class="bonus-wrap">
        <div class="bonus-inner">
          <div class="bonus-title">Bet with Bonus Points</div>
          <div class="bonus-desc">
            Spend your bonus points as a wager.<br>
            <strong>Win</strong> → chip equivalent added to balance.<br>
            <strong>Lose</strong> → points spent, no chips lost.
          </div>
          <div class="bonus-pts-avail">
            Available: <strong><?= number_format($score) ?> pts</strong>
          </div>
          <?php if ($score >= 10): ?>
          <form method="post" id="bonusForm">
            <input type="hidden" name="action" value="bonus_bet">
            <input type="hidden" name="bet" id="bbBetInput" value="player">
            <div class="bonus-bet-opts">
              <button type="button" class="bb-btn sel" data-val="player" onclick="selectBB(this)">Player</button>
              <button type="button" class="bb-btn" data-val="tie" onclick="selectBB(this)">Tie</button>
              <button type="button" class="bb-btn" data-val="banker" onclick="selectBB(this)">Banker</button>
            </div>
            <div class="bonus-wager-row">
              <span class="wager-lbl">Pts wager</span>
              <input type="number" name="pts_wager" id="bbWager" class="bonus-wager-in"
                     min="10" max="<?=$score?>" value="<?=min(50,$score)?>" step="10">
            </div>
            <button type="submit" class="btn-bonus" id="btnBonus">Place Bonus Bet</button>
            <div class="bonus-note">1 pt = $1 chip equivalent on win. Points always deducted.</div>
          </form>
          <?php else: ?>
          <div class="bonus-note" style="color:var(--dim);">Earn at least 10 bonus points to place a bonus bet.</div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /right-col -->
  </div><!-- /arena -->
</div><!-- /page -->

<script>
// ── CARD FLIP ─────────────────────────────────────────────────
// Alternating deal: P1(0s) → B1(0.5s) → P2(1s) → B2(1.5s) → 3rd card(s)(2s)
(function() {
  const cards = document.querySelectorAll('.card.face-down');
  if (!cards.length) return;

  cards.forEach(card => {
    const delay = parseFloat(card.style.animationDelay) || 0;

    const inner = document.createElement('div');
    inner.className = 'card-inner';
    while (card.firstChild) inner.appendChild(card.firstChild);
    card.appendChild(inner);

    setTimeout(() => card.classList.remove('face-down'), delay * 1000 + 100);
  });
})();

// ── BET PANEL ─────────────────────────────────────────────────
document.querySelectorAll('.bet-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.bet-btn').forEach(b => b.classList.remove('sel'));
    btn.classList.add('sel');
    document.getElementById('betInput').value = btn.querySelector('input').value;
    syncConfirm();
  });
});

document.getElementById('wager')?.addEventListener('input', syncConfirm);

function syncConfirm() {
  const bet   = document.getElementById('betInput').value;
  const wager = document.getElementById('wager').value;
  document.getElementById('cn-bet').textContent   = bet[0].toUpperCase() + bet.slice(1);
  document.getElementById('cn-wager').textContent = '$' + wager;
}

function previewBet() {
  document.getElementById('confirm-box').classList.toggle('show');
  syncConfirm();
}

function setWager(v) {
  document.getElementById('wager').value = v;
  syncConfirm();
}

document.getElementById('betForm')?.addEventListener('submit', () => {
  const b = document.getElementById('btnDeal');
  b.disabled = true;
  b.querySelector('span').textContent = 'Dealing…';
});

// ── BONUS BET PANEL ───────────────────────────────────────────
function selectBB(btn) {
  document.querySelectorAll('.bb-btn').forEach(b => b.classList.remove('sel'));
  btn.classList.add('sel');
  document.getElementById('bbBetInput').value = btn.dataset.val;
}

document.getElementById('bonusForm')?.addEventListener('submit', () => {
  const b = document.getElementById('btnBonus');
  b.disabled = true;
  b.textContent = 'Betting…';
});
</script>
</body>
</html>