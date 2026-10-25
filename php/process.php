<?php require('includes/config.php'); 
 ob_start();
 if( !$user->is_logged_in() ){ header('Location: login.php'); exit; }
?>
<?php
$title = 'Puzzle';
require('layout/header.php');
echo '<main class="container">';


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
		$questionPages = available_question_pages();
		$totalQuestions = count($questionPages);
		$questionLevel = filter_var($res['level'], FILTER_VALIDATE_INT);
		$questionPath = $questionLevel !== false && $questionLevel >= 0
		    ? __DIR__ . '/questions/q_' . $questionLevel . '.html'
		    : '';
		$hasCurrentQuestion = $questionPath !== '' && is_file($questionPath);
		$isComplete = $totalQuestions > 0 && $questionLevel !== false && $questionLevel >= $totalQuestions;
		$eventOpen = game_event_is_open($db);
    if($eventOpen && $hasCurrentQuestion && is_string($_POST['ans'] ?? null) && $_POST['ans'] !== '')
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
                $logstmt = $db->prepare('INSERT INTO answerlog (memberID,answer,TimeOfAnswer,ip) VALUES (:memid,:ans,UTC_TIMESTAMP(),:ipadd)');
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
                    $stmt = $db->prepare("UPDATE members SET level = level + 1, lastsolvetime = UTC_TIMESTAMP(6) WHERE memberID = :member_id AND active = 'Yes' AND level = :level AND EXISTS (SELECT 1 FROM event_state WHERE singleton_id = 1 AND is_open = 1)");
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
    echo "<h2>Welcome <b>".h($res['username'])."</b></h2>";
    if (!$eventOpen) {
        echo '<p>The event is closed.</p>';
    } elseif ($totalQuestions === 0) {
        echo '<p>The quiz is temporarily unavailable while questions are being updated.</p>';
    } elseif ($isComplete) {
        echo '<p>You have completed every puzzle. Congratulations!</p>';
    } elseif ($hasCurrentQuestion) {
        readfile($questionPath);
    } else {
        echo '<p>This puzzle is currently unavailable.</p>';
    }
?>
<?php if ($eventOpen && $hasCurrentQuestion): ?>
<form action="process.php" method="post">
	<?= csrf_field() ?>
	<input type="text" placeholder="Answer" name="ans" aria-label="Answer">
	<button type="submit">Submit</button>
</form>
<p class="muted">Your IP address is being logged for security reasons.</p>
<?php endif; ?>
</main>
<?php require('layout/footer.php'); ?>
