<?php
include 'database.php';
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
function increment($memid,$pno,$mysqli,$fid,$streak)
{
    $s=1;
	$pno++;
	if($streak>=3)
	    $s=$s+1;
	$sql = "UPDATE `members` SET `noPeople` = '$pno',streaks=streaks+1,noPasses=0,score=score+$s WHERE `members`.`username` = '$_SESSION[username]' ";
	$mysqli->query($sql);
	/*$nk=generateKey();
	$sql = "UPDATE `members` SET `publicKey` = '$nk' WHERE `members`.`memberID` = '$fid' ";
	$mysqli->query($sql);*/
}
function allowedPass($memid,$mysqli)
{
	$query = "select memberID,TIMESTAMPDIFF(MINUTE,NOW(),lastsolvetime) as mingap,noPasses from members where memberID=$memid";
	$result = $mysqli->query($query) or die($mysqli->error);
	$res=$result->fetch_assoc();
	if($res['noPasses']==0)
		return 0;
	$mg=abs($res['mingap']);
	return (pow(2,$res['noPasses']-1)-$mg); #If this is negative, pass allowed.
}
function getFindID($memid,$missid,$mysqli,$allowrep)
{
	$uns=[];
	if($allowrep==0)
		$query = "select memberID from members WHERE active='Yes' AND memberID NOT IN (SELECT foundID from matched where finderID=".$memid.") AND memberID NOT IN (SELECT foundID from passed WHERE finderID=$memid)";
	else
		$query = "select memberID from members WHERE active='Yes' AND memberID NOT IN (SELECT foundID from matched where finderID=".$memid.")";
	$result2 = $mysqli->query($query) or die($mysqli->error);
	$i=-1;
	while($row = $result2->fetch_assoc())
	{
		$i++;
		$uns[$i]=$row['memberID'];
	}
	$i++;
	if($i==0)
	{
		if($allowrep==0)
			return getFindID($memid,$missid,$mysqli,1);
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
					return getFindID($memid,$missid,$mysqli,1); 
				else
					return $x;
			}
			return getFindID($memid,$missid,$mysqli,$allowrep);
		}
		else
			return $x;
	}
}
function makeUpdate($mysqli,$query) {$mysqli->query($query) or die($mysqli->error);}
?>
