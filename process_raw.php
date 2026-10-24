<?php require('includes/config.php');
		if(!$user->is_logged_in() ){ header('Location: login.php'); exit; }
		$stmt = $db->prepare('SELECT username,level,email FROM members WHERE username = :username');
		$stmt->execute(array(':username' => $_SESSION['username']));
        $res = $stmt->fetch();
    echo "Session ID: ".h(session_id());
    echo '<ul>
    <li><a class="active" href="process.php">Home</a></li>  
    <li><a href="memberpage.php">Dashboard</a></li>
    <li><a href="leaderboard.php">Leaderboard</a></li>
    <li><form method="post" action="logout.php">' . csrf_field() . '<button type="submit">Logout</button></form></li>
    </ul>';
    if(is_string($_POST['ans'] ?? null) && $_POST['ans'] !== '')
        {
            $hans=$genpass->password_hash($_POST['ans'], PASSWORD_BCRYPT);
            echo h($hans)."\n";
            /*if($genpass->password_verify($_POST["ans"],$res2["Answer"]) == 1)
                echo "Correct answer";
            else
                echo "Wrong Answer";*/
        }
    echo "<h2 align='center'>Welcome <b>".h($res['username'])."</b><br/><br/></h2>";
    echo "<h4 align='center'>You're on level ".h($res["level"])."</h4><br/>";
    //readfile("questions/q_".$res["level"].".html")[0];
	    
	    
	    echo '<form role="form" method="post" action="tp.php" autocomplete="off">' . csrf_field() . ' <p> Enter Answer: <input type="text" name="ans" id="ans" class="form-control input-lg" placeholder="Answer" value="" tabindex="1"/></p> <input type="submit" name="submit" value="Submit" class="btn btn-primary btn-block btn-lg" tabindex="6"><br/> </form>';
		echo '</div>';
		
require('layout/footer.php'); 
?>
