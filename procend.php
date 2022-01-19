<?php
    
    $query = "SELECT lockedq from members where lockedq=1";
    $result = $mysqli->query($query) or die($mysqli->error);
    if(!$result->fetch_assoc())
        chmod("questions",0700); 
?>