<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php if(isset($title)){ echo h($title); }?></title>
    <link rel="stylesheet" href="assets/css/pico.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
</head>
<body>
<header class="site-header">
  <nav class="container-fluid">
    <ul>
      <li><strong><a href="index.php"><?= h(SITENAME) ?></a></strong></li>
    </ul>
    <ul>
      <li><a href="leaderboard.php">Leaderboard</a></li>
<?php if ($user->is_logged_in()): ?>
      <li><a href="memberpage.php">Dashboard</a></li>
      <li><a href="process.php">Puzzle</a></li>
<?php if (HINTSURL !== ''): ?>
      <li><a href="<?= h(HINTSURL) ?>">Hints</a></li>
<?php endif; ?>
      <li><form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="outline">Logout</button></form></li>
<?php else: ?>
      <li><a href="login.php">Login</a></li>
      <li><a href="registerpage.php">Register</a></li>
<?php endif; ?>
    </ul>
  </nav>
</header>
