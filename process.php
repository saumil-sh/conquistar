<?php require('includes/config.php'); 
 ob_start();
 if( !$user->is_logged_in() ){ header('Location: login.php'); exit; }
?>
<?php echo '
<head>
<meta charset="UTF-8">
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
        <a class="nav-link" href="leaderboard.php">Leaderboard</a>
      </li>
    <li class="nav-item">
        <form method="post" action="logout.php">' . csrf_field() . '<button type="submit" class="nav-link btn btn-link">Logout</button></form>
      </li>
    </ul>
  </div>
</nav>';

		$memberId = filter_var($_SESSION['memberID'] ?? null, FILTER_VALIDATE_INT);
		if ($memberId === false || $memberId < 1) {
			$user->logout();
			header('Location: login.php');
			exit;
		}
		$stmt = $db->prepare("SELECT level,email,username,memberID FROM members WHERE memberID = :member_id AND active = 'Yes'");
		$stmt->execute(array(':member_id' => $memberId));
        $res = $stmt->fetch();
		if (!$res) {
			$user->logout();
			header('Location: login.php');
			exit;
		}
        $_SESSION["userlevel"]=$res["level"];
        $eventOpen = game_event_is_open($db);
    if($eventOpen && is_string($_POST['ans'] ?? null) && $_POST['ans'] !== '')
        {
            $answer = $_POST['ans'];
            $clientIp = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
            $canGuess = rate_limit_allow($db, 'guess-member', (string) $res['memberID'], 15, 60);
            if ($canGuess) {
                $canGuess = rate_limit_allow($db, 'guess-ip', $clientIp, 750, 60);
            }

            if (!$canGuess) {
                echo "Too many guesses. Please wait before trying again.";
            } else {
                $logstmt = $db->prepare('INSERT INTO answerlog (memberID,answer,TimeOfAnswer,ip) VALUES (:memid,:ans,ADDTIME(NOW(),"5:30"),:ipadd)');
				$logstmt->execute(array(
					':memid' => $res["memberID"],
					':ans' => $answer,
					':ipadd' => $clientIp,
				));

                $gans = strtolower(str_replace(' ', '', $answer));
                $stmt = $db->prepare('SELECT Answer FROM questions WHERE qID = :level');
                $stmt->execute(array(':level' => $res['level']));
                $res2 = $stmt->fetch();

                if ($res2 && password_verify($gans, $res2['Answer'])) {
                    $stmt = $db->prepare("UPDATE members SET level = level + 1, lastsolvetime = ADDTIME(NOW(), '5:30:00') WHERE memberID = :member_id AND active = 'Yes' AND level = :level AND EXISTS (SELECT 1 FROM event_state WHERE singleton_id = 1 AND is_open = 1)");
                    $stmt->execute(array(':member_id' => $res['memberID'], ':level' => $res['level']));
                    if ($stmt->rowCount() === 1) {
                        header('Location: process.php');
                        exit;
                    }
                    if (!game_event_is_open($db)) {
                        $eventOpen = false;
                        echo "The event is closed.";
                    } else {
                        echo "The game state changed. Reload before trying again.";
                    }
                } else {
                    echo "Wrong Answer";
                }
            }
        }
    echo "<br/>";
    echo "<h2 align='center'>Welcome <b>".h($res['username'])."</b><br/><br/></h2>";
    $questionLevel = filter_var($res['level'], FILTER_VALIDATE_INT);
    $questionPath = $questionLevel !== false && $questionLevel >= 0
        ? __DIR__ . '/questions/q_' . $questionLevel . '.html'
        : '';
    $hasCurrentQuestion = $questionPath !== '' && is_file($questionPath);
    if (!$eventOpen) {
        echo '<p>The event is closed.</p>';
    } elseif ($hasCurrentQuestion) {
        readfile($questionPath);
    } else {
        echo '<p>You have completed every puzzle. Congratulations!</p>';
    }
	    
	    
		
require('layout/footer.php'); 
?>
<?php if ($eventOpen && $hasCurrentQuestion): ?>
<form action="process.php" method="post">
                        <?= csrf_field() ?>

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
		<?php endif; ?>