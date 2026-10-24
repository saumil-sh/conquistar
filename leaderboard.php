<?php require('includes/config.php');
?>

<html>
<head lang="en">
    <meta charset="UTF-8">
    <title>Conquistar | Leaderboard</title>
    
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="assets/favicon.png">
  
</head>
<body>
  
  <nav class="navbar navbar-inverse bg-primary navbar-toggleable-md navbar-fixed-top" style="font-weight:300">
  <button class="navbar-toggler navbar-toggler-right collapsed" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <a class="navbar-brand" href="http://iplug.website/">CONQUISTAR 2020</a>

  <div class="navbar-collapse collapse" id="navbarSupportedContent" aria-expanded="false">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item">
        <a class="nav-link" href="index.php">Home</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="memberpage.php">Dashboard</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="https://www.facebook.com/ConquistarHunt">Hints</a>
      </li>
    <li class="nav-item">
        <a class="nav-link" href="leaderboard.php">Leaderboard</a>
      </li>
    <li class="nav-item">
      <form method="post" action="logout.php"><?= csrf_field() ?><button class="nav-link btn btn-link" type="submit">Logout</button></form>
      </li>
    </ul>
  </div>
</nav>
  
<div class="container"><center>
  <br>
    <h1>Conquistar 2020</h1>
  <h2>Leaderboard</h2><br></center>
    <div class="row">
        <div class="col-md-12">
    

<?php
 $user=$_GET['user'] ?? '';
// Attempt select query execution with order by clause
$sql = "select username,level,institute,lastsolvetime from members ORDER BY level DESC,lastsolvetime asc";
$result = $db->query($sql);
  $rank=1;
  $myrank=0;
  $hasRows=false;
    echo "<table class='table table-hover'>";
    echo "<thead class='thead-inverse'><tr class='table-header'>";
        echo "<th>Rank</th>";
        echo "<th>Name</th>";
        echo "<th>Institution</th>";
        echo "<th>Current Level</th>";
        echo "<th>Time of Answer</th>";
        echo "</tr></thead>";
        while($row = $result->fetch()){
            $hasRows=true;
            if($user==$row["username"]) {echo "<b>";}
            echo "<tr>";
        echo "<td> $rank </td>";
                echo "<td>" . h($row['username']) . "</td>";
      echo "<td>" . h($row['institute']) . "</td>";
                echo "<td>" . h($row['level']) . "</td>";
                echo "<td>" . h($row['lastsolvetime']) . "</td>";
            echo "</tr>";
            if($user==$row["username"]) {echo "</b>"; $myrank=$rank;}
      $rank++;
        }
        echo "</table>";
        if($user) echo "Your rank: ".h($myrank);
        if(!$hasRows) echo "No records matching your query were found.";
 
?>
  
            
      </div>
    </div>
</div>
  <script src="https://code.jquery.com/jquery-3.1.1.slim.min.js" integrity="sha384-A7FZj7v+d/sdmMqp/nOQwliLvUsJfDHW+k9Omg/a/EheAdgtzNs3hpfag6Ed950n" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js" integrity="sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb" crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>
</body>
</html>
      
