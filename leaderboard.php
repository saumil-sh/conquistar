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
        		$stmt = $db->prepare("SELECT memberID, level, lastsolvetime FROM members WHERE memberID = :member_id AND active = 'Yes'");
        		$stmt->execute(array(':member_id' => $_SESSION['memberID']));
        		$player = $stmt->fetch();
        	}

        	$stmt = $db->query("SELECT memberID, username, level, institute, lastsolvetime
        		FROM members
        		WHERE active = 'Yes'
        		ORDER BY level DESC, lastsolvetime ASC, memberID ASC
        		LIMIT 50");
        	$rank = 1;
        	$hasRows = false;

        	echo "<table class='leaderboard'>";
        	echo "<thead><tr>";
        	echo "<th>Rank</th><th>Name</th><th>Institution</th><th>Current Level</th><th>Time of Answer</th>";
        	echo "</tr></thead>";
        	while ($row = $stmt->fetch()) {
        		$hasRows = true;
        		$isPlayer = $player && (int) $player['memberID'] === (int) $row['memberID'];
        		echo "<tr" . ($isPlayer ? " class='table-primary'" : "") . ">";
        		echo "<td>" . $rank . "</td>";
        		echo "<td>" . h($row['username']) . "</td>";
        		echo "<td>" . h($row['institute']) . "</td>";
        		echo "<td>" . h($row['level']) . "</td>";
        		echo "<td>" . h($row['lastsolvetime']) . "</td>";
        		echo "</tr>";
        		$rank++;
        	}
        	echo "</table>";

        	if ($player) {
        		$stmt = $db->prepare("SELECT COUNT(*) + 1
        			FROM members
        			WHERE active = 'Yes' AND (
        				level > :player_level
        				OR (level = :level_for_time AND lastsolvetime < :player_time)
        				OR (level = :level_for_id AND lastsolvetime = :time_for_id AND memberID < :player_id)
        			)");
        		$stmt->execute(array(
        			':player_level' => $player['level'],
        			':level_for_time' => $player['level'],
        			':player_time' => $player['lastsolvetime'],
        			':level_for_id' => $player['level'],
        			':time_for_id' => $player['lastsolvetime'],
        			':player_id' => $player['memberID'],
        		));
        		echo "<p>Your rank: " . h($stmt->fetchColumn()) . "</p>";
        	}

        	if (!$hasRows) {
        		echo "No active players yet.";
        	}
        ?>
</main>
<?php require('layout/footer.php'); ?>
