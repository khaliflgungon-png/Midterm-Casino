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
