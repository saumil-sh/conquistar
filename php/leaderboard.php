<?php require('includes/config.php');
?>

<?php
$title = SITENAME . ' | Leaderboard';
require('layout/header.php');
?>
  
<main class="container">
  <h1><?= h(SITENAME) ?></h1>
  <h2>Leaderboard</h2>
    

<?php
        	$player = null;
        	if ($user->is_logged_in()) {
        		$stmt = $db->prepare("SELECT memberID, level, hints_used, lastsolvetime FROM members WHERE memberID = :member_id AND active = 'Yes'");
        		$stmt->execute(array(':member_id' => $_SESSION['memberID']));
        		$player = $stmt->fetch();
        	}

        	$stmt = $db->query("SELECT memberID, username, level, institute, hints_used, lastsolvetime
        		FROM members
        		WHERE active = 'Yes'
        		ORDER BY level DESC, hints_used ASC, lastsolvetime ASC, memberID ASC
        		LIMIT 50");
        	$rank = 1;
        	$hasRows = false;

        	echo "<table class='leaderboard'>";
        	echo "<thead><tr>";
        	echo "<th>Rank</th><th>Name</th><th>Institution</th><th>Current Level</th><th>Hints Used</th><th>Time of Answer (UTC)</th>";
        	echo "</tr></thead>";
        	while ($row = $stmt->fetch()) {
        		$hasRows = true;
        		$isPlayer = $player && (int) $player['memberID'] === (int) $row['memberID'];
        		echo "<tr" . ($isPlayer ? " class='table-primary'" : "") . ">";
        		echo "<td>" . $rank . "</td>";
        		echo "<td>" . h($row['username']) . "</td>";
        		echo "<td>" . h($row['institute']) . "</td>";
        		echo "<td>" . h($row['level']) . "</td>";
        		echo "<td>" . h($row['hints_used']) . "</td>";
        		echo "<td>" . h($row['lastsolvetime']) . "</td>";
        		echo "</tr>";
        		$rank++;
        	}
        	echo "</table>";

        	if ($player) {
        		$stmt = $db->prepare("SELECT COUNT(*) + 1
        			FROM members
        			WHERE active = 'Yes' AND (
        				level > ?
        				OR (level = ? AND hints_used < ?)
        				OR (level = ? AND hints_used = ? AND lastsolvetime < ?)
        				OR (level = ? AND hints_used = ? AND lastsolvetime = ? AND memberID < ?)
        			)");
        		$level = $player['level'];
        		$hints = $player['hints_used'];
        		$time = $player['lastsolvetime'];
        		$stmt->execute(array($level, $level, $hints, $level, $hints, $time, $level, $hints, $time, $player['memberID']));
        		echo "<p>Your rank: " . h($stmt->fetchColumn()) . "</p>";
        	}

        	if (!$hasRows) {
        		echo "No active players yet.";
        	}
        ?>
</main>
<?php require('layout/footer.php'); ?>
