<?php
session_start();

// Initialize shared balance if not set
if (!isset($_SESSION['chips'])) {
    $_SESSION['chips'] = 1000; // Starting Chips
}
if (!isset($_SESSION['bp'])) {
    $_SESSION['bp'] = 0; // Starting Bonus Points
}

$chips = $_SESSION['chips'];
$bp    = $_SESSION['bp'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Royale Casino</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@300;400;600&display=swap" rel="stylesheet">
<style>
/* Reset & Basic */
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Josefin Sans', sans-serif; background: #0a1f14; color: #f0e6cc; display: flex; flex-direction: column; min-height: 100vh; text-align: center;justify-content: flex-start; }
a { text-decoration: none; }

/* Header & Balance */
header { display: flex; justify-content: space-between; align-items: center; padding: 20px 40px; background: #133a22; }
header .logo { font-size: 2rem; font-weight: 700; color: #c9a84c; text-transform: uppercase; }
header .balance { text-align: right; font-size: 0.9rem; }
header .balance span { display: block; }

/* Center Description */
.main-desc { flex: 0; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 150px 20px; }
.main-desc p { max-width: 750px; font-size: 1.5rem; line-height: 1.5; color: #e2c070; margin-top: 20px; }

/* Game Buttons Container */
.game-buttons {
    display: flex;
    justify-content: center;  /* centers the buttons horizontally */
    gap: 30px;                /* space between buttons */
    flex-wrap: wrap;
    margin-bottom: 40px;
    margin-top: 20px;
}

/* Each Button */
.game-buttons a {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 250px;
    height: 250px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.4);
    background: #133a22; /* fallback background */
    transition: transform 0.2s, box-shadow 0.2s;
    overflow: hidden;
}

/* Image inside the button */
.game-buttons a img {
    width: 80%;
    height: auto;
    object-fit: contain;
    border-radius: 10px;
}

/* Text below the image */
.game-buttons a span {
    margin-top: 10px;
    font-weight: 600;
    color: #f0e6cc;
    text-shadow: 0 1px 3px #000;
}

/* Hover effect */
.game-buttons a:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 22px rgba(201,168,76,0.6);
}

/* Responsive for small screens */
@media (max-width: 700px) {
    .game-buttons a {
        width: 150px;
        height: 150px;
    }
    .game-buttons a img {
        width: 70%;
    }
}
</style>
</head>
<body>

<header>
    <div class="logo">
    <img src="chips.png" alt="Casino Logo" style="height:40px; vertical-align:middle;">
    Royale Casino
</div>
    <div class="balance">
        <span>Chips: ₱<?= number_format($chips) ?></span>
        <span>BP: <?= $bp ?></span>
    </div>
</header>

<div class="main-desc">
    <h2>Welcome to the Casino Royale!</h2>
    <p>Play exciting games like Baccarat, Mines, and Plinko. Your Chips and Bonus Points are shared across all games. Have fun, test your luck, and watch your balance grow!</p>
</div>

<div class="game-buttons">
    <div class="game-buttons">
    <a href="Baccarat.php">
        <img src="baccarat.png" alt="Baccarat">
        <span>Baccarat</span>
    </a>
    <a href="Mines.php">
        <img src="mines.png" alt="Mines">
        <span>Mines</span>
    </a>
    <a href="Plinko.php">
        <img src="plinko.png" alt="Plinko">
        <span>Plinko</span>
    </a>
</div>
</div>

</body>
</html>