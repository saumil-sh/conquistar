<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php if(isset($title)){ echo h($title); }?></title>
    <link href="//netdna.bootstrapcdn.com/bootstrap/3.1.0/css/bootstrap.min.css" rel="stylesheet">
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
