<?php require('includes/config.php'); 
    include 'database.php';
    include 'increment.php';
 ob_start();
 ob_implicit_flush(true);
 if( !$user->is_logged_in() ){ header('Location: login.php'); }
 else //Remove this block to allow process.php to work normally
 {
     //if($SESSION["username"]!="venkata") {header('Location: memberpage.php');}
 }
//require('layout/header.php'); 
?>
<?php echo '
<head>
<meta charset="UTF-8">
<!--<title>Conquistar | Questions</title>-->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
<link rel="icon" type="image/png" href="assets/favicon.png">
</head>
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
        <a class="nav-link" href="leaderboard.php?user="'.$_SESSION["username"].' >Leaderboard</a>
      </li>
    <li class="nav-item">
        <a class="nav-link" href="logout.php">Logout</a>
      </li>
    </ul>
  </div>
</nav>';

		$query = "SELECT level,email,username,memberID FROM members WHERE username LIKE '".$_SESSION['username']."' ";
        $result = $mysqli->query($query) or die($mysqli->error);
        $res = $result->fetch_assoc();
        //if($res["memberID"]!=1) {header('Location: memberpage.php');} Uncomment to stop process
        $_SESSION["userlevel"]=$res["level"];
    if($_POST["ans"])
        {
            $uIP=$_SERVER['REMOTE_ADDR'];
            $logstmt = $db->prepare('INSERT INTO answerlog (memberID,answer,TimeOfAnswer,ip) VALUES (:memid,:ans,ADDTIME(NOW(),"5:30"),:ipadd)');
			$logstmt->execute(array(
				':memid' => $res["memberID"],
				':ans' => $_POST["ans"],
				':ipadd' => $uIP,
			));
            $gans=str_replace(" ","",$_POST['ans']);
            $gans=strtolower($gans);
            $query = "SELECT qID,Answer FROM questions WHERE qID = ".$res["level"];
            $result = $mysqli->query($query) or die($mysqli->error);
            $res2 = $result->fetch_assoc();
            
            if($genpass->password_verify($gans,$res2["Answer"]) == 1)
            {
                $query = "UPDATE members set level=".$res["level"]."+1,lastsolvetime=ADDTIME(NOW(),'5:30:00') WHERE username LIKE '".$_SESSION['username']."' ";
                $result = $mysqli->query($query) or die($mysqli->error);
                header('Location: process.php');
            }
            else
                echo "Wrong Answer";
        }
    echo "<br/>";
    echo "<h2 align='center'>Welcome <b>".$res['username']."</b><br/><br/></h2>";
    readfile("questions/q_".$res["level"].".html")[0];
	    
	    
	    /*echo '<form role="form" method="post" action="process.php" autocomplete="off"> <p> Enter Answer: <input type="text" name="ans" id="ans" class="form-control input-lg" placeholder="Answer" value="" tabindex="1"/></p> <input type="submit" name="submit" value="Submit" class="btn btn-primary btn-block btn-lg" tabindex="6"><br/> </form>';
		echo '</div>';*/
		
require('layout/footer.php'); 
?>
<form action="process.php" method="post">

                        <div class="col-xs-15" style = "margin-left:100px; margin-right:100px;">
                          <input class="form-control" type="text" placeholder="Answer" name="ans">
                        </div><br>
                        <div class="col-xs-15">
                            <center>
                            <button type="submit" class="btn btn-default btn-lg">
                              <span class="glyphicon glyphicon-check" aria-hidden="true">
                              </span> Submit
                            </button>
                            </center>
                        </div>
</form> 
                        </div>
<div class="alert alert-warning alert-dismissable" style = "margin-left:50px; margin-right:50px;">
			<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
		  <center>Your IP address is being logged for security reasons.</center>
		</div>
<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>