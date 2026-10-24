<?php require_once('includes/config.php');
include 'increment.php';
 ob_start();
 if(!$user->is_logged_in() ){ header('Location: login.php'); exit; }
 require('layout/header.php');
echo '<head>
<style>
ul {
  list-style-type: none;
  margin: 0;
  padding: 0;
  overflow: hidden;
  background-color: #333;
}

li {
  float: left;
}

li a {
  display: block;
  color: white;
  text-align: center;
  padding: 20px 22px;
  text-decoration: none;
}

li a:hover {
  background-color: #3BA1AF;
  color: #fff;
  text-decoration: none;
}
</style>
</head>';


		$stmt = $db->prepare('SELECT username,level,email FROM members WHERE username = :username');
		$stmt->execute(array(':username' => $_SESSION['username']));
        $res = $stmt->fetch();
    echo '<ul>
    <li><a class="active" href="process.php">Home</a></li>  
    <li><a href="memberpage.php">Dashboard</a></li>
    <li><a href="leaderboard.php">Leaderboard</a></li>
    <li><form method="post" action="logout.php">' . csrf_field() . '<button type="submit">Logout</button></form></li>
    </ul>';
    if(is_string($_POST['ans'] ?? null) && $_POST['ans'] !== '')
        {
            $acans=strtolower($_POST['ans']);
            //echo $hans."\n";
            $stmt = $db->prepare('SELECT qID,Answer FROM questions WHERE qID = :level');
            $stmt->execute(array(':level' => $res['level']));
            $res2 = $stmt->fetch();
            //echo $query."\n";
            
            if($genpass->password_verify($acans,$res2["Answer"]) == 1)
                echo "Correct answer";
            else
                echo "Wrong Answer";
        }
    echo "<h2 align='center'>Welcome <b>".h($res['username'])."</b><br/><br/></h2>";
    echo "<h4 align='center'>You're on level ".h($res["level"])."</h4><br/>";
    $questionLevel = filter_var($res['level'], FILTER_VALIDATE_INT);
    if ($questionLevel !== false && $questionLevel >= 0) {
        $questionPath = __DIR__ . '/questions/q_' . $questionLevel . '.html';
        if (is_file($questionPath)) {
            readfile($questionPath);
        }
    }
	    
	    
	    echo '<form role="form" method="post" action="process.php" autocomplete="off">' . csrf_field() . ' <p> Enter Answer: <input type="text" name="ans" id="ans" class="form-control input-lg" placeholder="Answer" value="" tabindex="1"/></p> <input type="submit" name="submit" value="Submit" class="btn btn-primary btn-block btn-lg" tabindex="6"><br/> </form>';
		echo '</div>';
		
require('layout/footer.php'); 
?>