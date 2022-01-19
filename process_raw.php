<?php require('includes/config.php');
include 'database.php';
		if(!$user->is_logged_in() ){ header('Location: login.php'); }
		$query = "SELECT level,email FROM members WHERE username LIKE '".$_SESSION['username']."' ";
        $result = $mysqli->query($query) or die($mysqli->error);
        $res = $result->fetch_assoc();
    echo "Session ID: ".session_id();
    echo '<ul>
    <li><a class="active" href="process.php">Home</a></li>  
    <li><a href="memberpage.php">Dashboard</a></li>
    <li><a href="leaderboard.php">Leaderboard</a></li>
    <li><a href="logout.php">Logout</a></li>
    </ul>';
    if($_POST["ans"])
        {
            $hans=$genpass->password_hash($_POST['ans'], PASSWORD_BCRYPT);
            echo $hans."\n";
            /*$query = "SELECT qID,Answer FROM questions WHERE qID = ".$res["level"];
            $result = $mysqli->query($query) or die($mysqli->error);
            $res2 = $result->fetch_assoc();*/
            //echo $query."\n";
            
            /*if($genpass->password_verify($_POST["ans"],$res2["Answer"]) == 1)
                echo "Correct answer";
            else
                echo "Wrong Answer";*/
        }
    echo "<h2 align='center'>Welcome <b>".$res['username']."</b><br/><br/></h2>";
    echo "<h4 align='center'>You're on level ".$res["level"]."</h4><br/>";
    //readfile("questions/q_".$res["level"].".html")[0];
	    
	    
	    echo '<form role="form" method="post" action="tp.php" autocomplete="off"> <p> Enter Answer: <input type="text" name="ans" id="ans" class="form-control input-lg" placeholder="Answer" value="" tabindex="1"/></p> <input type="submit" name="submit" value="Submit" class="btn btn-primary btn-block btn-lg" tabindex="6"><br/> </form>';
		echo '</div>';
		
require('layout/footer.php'); 
?>
