<!--
Bañganan, John Mcnesse (Front-end)
Butuhan, Nick Andrei (Quality Assurance)
Delacruz, Aljen Peter (Business Analyst)
Gungon, Khalif (Back-end)
>
<?php
session_start();

function generateFateNumber(): int {
    return rand(1, 30);
}

// Evaluate the fate number against Baccarat bet patterns.
function evaluateBaccarat(string $bet, int $number): array {
    // Jackpot check first — divisible by 7 always fires a Tie
    if ($number % 7 === 0) {
        $winner       = 'tie';
        $resultType   = 'jackpot';
        $pointsEarned = ($bet === 'tie') ? 20 : 0;
        return [$winner, $resultType, $pointsEarned];
    }

    // Odd → Player wins; Even → Banker wins
    $winner     = ($number % 2 !== 0) ? 'player' : 'banker';
    $resultType = ($bet === $winner)   ? 'win'    : 'lose';
    $pointsEarned = ($bet === $winner) ? 10       : 0;

    return [$winner, $resultType, $pointsEarned];
}

// Build a readable result message.
function buildResultMessage(string $bet, string $winner, string $resultType, int $number, int $points): string {
    $betLabel    = ucfirst($bet);
    $winnerLabel = ucfirst($winner);

    if ($resultType === 'jackpot' && $bet === 'tie') {
        return "🎰 JACKPOT! Fate #{$number} is divisible by 7 — Tie wins! +{$points} pts!";
    }
    if ($resultType === 'jackpot') {
        return "🎰 Jackpot Tie! Fate #{$number} — But you bet {$betLabel}. No points this round.";
    }
    if ($resultType === 'win') {
        return "🏆 {$winnerLabel} wins! Fate #{$number} — Your {$betLabel} bet is correct. +{$points} pts!";
    }
    return "💸 {$winnerLabel} wins! Fate #{$number} — You bet {$betLabel}. Better luck next round.";
}

// Chip payout (separate from the points score system)
function calculatePayout(string $bet, string $winner, string $resultType, int $wagered): int {
    if ($resultType === 'jackpot' && $bet === 'tie') return $wagered * 8;
    if ($resultType === 'jackpot')                   return -$wagered;
    if ($bet !== $winner)                            return -$wagered;
    if ($bet === 'tie')                              return $wagered * 8;
    if ($bet === 'banker')                           return (int) floor($wagered * 0.95);
    return $wagered;
}

/* 
 * Build visual card hands from the fate number.
 * Seeded so same fate number → same visual cards.
 */
function buildVisualHands(int $number, string $winner): array {
    $suits = ['♥','♦','♣','♠'];
    $ranks = ['A','2','3','4','5','6','7','8','9'];

    srand($number);

    if ($winner === 'tie') {
        $score = $number % 9 ?: 9;
        $p = makeHand($score, $suits, $ranks);
        $b = makeHand($score, $suits, $ranks);
    } elseif ($winner === 'player') {
        $pScore = rand(6, 9);
        $bScore = rand(0, max(0, $pScore - 1));
        $p = makeHand($pScore, $suits, $ranks);
        $b = makeHand($bScore, $suits, $ranks);
    } else {
        $bScore = rand(6, 9);
        $pScore = rand(0, max(0, $bScore - 1));
        $p = makeHand($pScore, $suits, $ranks);
        $b = makeHand($bScore, $suits, $ranks);
    }

    srand();
    return ['player' => $p, 'banker' => $b];
}

function makeHand(int $target, array $suits, array $ranks): array {
    $a = rand(0, 8);
    $b = ($target - $a + 10) % 10;
    $b = min($b, 8);
    $redSuits = ['♥','♦'];
    $s1 = $suits[array_rand($suits)];
    $s2 = $suits[array_rand($suits)];
    return [
        ['rank'=>$ranks[$a], 'suit'=>$s1, 'red'=>in_array($s1,$redSuits)],
        ['rank'=>$ranks[$b], 'suit'=>$s2, 'red'=>in_array($s2,$redSuits)],
    ];
}
