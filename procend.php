<?php require_once('includes/config.php');
    
    $result = $db->query('SELECT lockedq FROM members WHERE lockedq = 1');
    if(!$result->fetch())
        chmod("questions",0700); 
?>