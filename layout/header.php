<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php if(isset($title)){ echo h($title); }?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;700&family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1.5.11/css/pico.min.css" integrity="sha384-bnKrovjvRzFUSqtvDhPloRir5qWWcx0KhrlfLaR4RXO9IUC+zJBuvclXv/fSdVyk" crossorigin="anonymous">
    <link rel="stylesheet" href="style/main.css">
    <link rel="icon" type="image/png" href="assets/favicon.png">
</head>
<body>
<header class="site-header">
  <nav class="container-fluid">
    <ul>
      <li><strong><a href="index.php">CONQUISTAR 2020</a></strong></li>
    </ul>
    <ul>
      <li><a href="leaderboard.php">Leaderboard</a></li>
<?php if ($user->is_logged_in()): ?>
      <li><a href="memberpage.php">Dashboard</a></li>
      <li><a href="process.php">Puzzle</a></li>
      <li><a href="https://www.facebook.com/ConquistarHunt">Hints</a></li>
      <li><form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="outline">Logout</button></form></li>
<?php else: ?>
      <li><a href="login.php">Login</a></li>
      <li><a href="registerpage.php">Register</a></li>
<?php endif; ?>
    </ul>
  </nav>
</header>
