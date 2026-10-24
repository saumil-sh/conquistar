<?php
require_once('includes/config.php');
function getUserIP()
{
	$client  = @$_SERVER['HTTP_CLIENT_IP'];
	$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
	$remote  = $_SERVER['REMOTE_ADDR'];
	
	if(filter_var($client, FILTER_VALIDATE_IP))
	{
		$ip = $client;
	}
	elseif(filter_var($forward, FILTER_VALIDATE_IP))
	{
		$ip = $forward;
	}
	else
	{
		$ip = $remote;
	}
	
	return $ip;
}
function generateKey()
{
	$ky="";
	for($i=0;$i<8;$i++)
	        $ky.=chr(mt_rand(65,90));
	return $ky;
}
function increment($memid,$pno,$db,$fid,$streak)
{
    $s=1;
	$pno++;
	if($streak>=3)
	    $s=$s+1;
	$stmt = $db->prepare('UPDATE members SET noPeople = :people, streaks = streaks + 1, noPasses = 0, score = score + :score WHERE username = :username');
	$stmt->execute(array(':people' => $pno, ':score' => $s, ':username' => $_SESSION['username']));
	/*$nk=generateKey();
	$sql = "UPDATE `members` SET `publicKey` = '$nk' WHERE `members`.`memberID` = '$fid' ";
	$db->exec($sql);*/
}
function allowedPass($memid,$db)
{
	$stmt = $db->prepare('SELECT memberID,TIMESTAMPDIFF(MINUTE,NOW(),lastsolvetime) AS mingap,noPasses FROM members WHERE memberID = :member_id');
	$stmt->execute(array(':member_id' => $memid));
	$res=$stmt->fetch();
	if($res['noPasses']==0)
		return 0;
	$mg=abs($res['mingap']);
	return (pow(2,$res['noPasses']-1)-$mg); #If this is negative, pass allowed.
}
function getFindID($memid,$missid,$db,$allowrep)
{
	$uns=[];
	$query = "SELECT memberID FROM members WHERE active = 'Yes' AND memberID NOT IN (SELECT foundID FROM matched WHERE finderID = :finder_id)";
	$params = array(':finder_id' => $memid);
	if($allowrep==0) {
		$query .= ' AND memberID NOT IN (SELECT foundID FROM passed WHERE finderID = :passed_finder_id)';
		$params[':passed_finder_id'] = $memid;
	}
	$stmt = $db->prepare($query);
	$stmt->execute($params);
	$i=-1;
	while($row = $stmt->fetch())
	{
		$i++;
		$uns[$i]=$row['memberID'];
	}
	$i++;
	if($i==0)
	{
		if($allowrep==0)
			return getFindID($memid,$missid,$db,1);
		else
			return -1;
	}
	else
	{
		$rn=mt_rand(0,$i-1);
		$x=$uns[$rn];
		if($x==$missid)
		{
			if($i==1)
			{
				if($allowrep==0) #This condition if you want to allow passing the last person and cycling into unpassed people.
					return getFindID($memid,$missid,$db,1);
				else
					return $x;
			}
			return getFindID($memid,$missid,$db,$allowrep);
		}
		else
			return $x;
	}
}
function makeUpdate($db,$query) {$db->exec($query);}
?>
