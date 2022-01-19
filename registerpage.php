<?php require('includes/config.php');

//if logged in redirect to members page
if( $user->is_logged_in() ){ header('Location: memberpage.php'); }

//if form has been submitted process it
if(isset($_POST['submit'])){

	//very basic validation
	if(strlen($_POST['username']) < 3){
		$error[] = 'Username is too short.';
	} else {
		$stmt = $db->prepare('SELECT username FROM members WHERE username = :username');
		$stmt->execute(array(':username' => $_POST['username']));
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if(!empty($row['username'])){
			$error[] = 'Username provided is already in use.';
		}

	}

	if(strlen($_POST['password']) < 3){
		$error[] = 'Password is too short.';
	}

	if(strlen($_POST['passwordConfirm']) < 3){
		$error[] = 'Confirm password is too short.';
	}

	if($_POST['password'] != $_POST['passwordConfirm']){
		$error[] = 'Passwords do not match.';
	}
	
	if(strlen($_POST['ins']) < 3){
		$error[] = 'Institute is too short.';
		
	}

	//email validation
	if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)){
	    $error[] = 'Please enter a valid email address';
	} else {
		$stmt = $db->prepare('SELECT email FROM members WHERE email = :email');
		$stmt->execute(array(':email' => $_POST['email']));
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if(!empty($row['email'])){
			$error[] = 'Email provided is already in use.';
		}

	}


	//if no errors have been created carry on
	if(!isset($error)){
		$stmt = $db->prepare('SELECT max(memberID) as nid FROM members');
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$nid=$row['nid'];
		if(!empty($nid))
			$nid+=1;
		else
			$nid=1;

		//hash the password
		$hashedpassword = $user->password_hash($_POST['password'], PASSWORD_BCRYPT);

		//create the activasion code
		$activasion = md5(uniqid(rand(),true));
        //$activasion = 'YES';
		try {
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


						$userip = getUserIP();
			
			date_default_timezone_set('Europe/Berlin');
			$now = date("Y-m-d H:i:s");

			//insert into database with a prepared statement
			$stmt = $db->prepare('INSERT INTO members (memberID,username,password,email,active,ip,institute,lastsolvetime) VALUES (:memid, :username, :password, :email, :active, :ip, :inst, :time)');
			$stmt->execute(array(
				':memid' => $nid,
				':username' => $_POST['username'],
				':password' => $hashedpassword,
				':email' => $_POST['email'],
				':active' => $activasion,
				':ip' => $userip,
				':inst' => $_POST['ins'],
				':time' => $now,
			));
			$id=$nid;
			#$id = $db->lastInsertId('memberID');

			//send email
			echo "Senidng mail";
			$to = $_POST['email'];
			$subject = "Registration Confirmation";
			$body = "<p>Thank you for registering for Conquistar 2020</p>
			<p>To activate your account, please click on this link: <a href='".DIR."activate.php?x=$id&y=$activasion'>".DIR."activate.php?x=$id&y=$activasion</a></p>
			<p>Regards Saumil.</p>";

			$mail = new Mail();
			$mail->setFrom(SITEEMAIL);
			$mail->addAddress($to);
			$mail->subject($subject);
			$mail->body($body);
			$mail->send();
			//print("Mail sent!");

			//redirect to index page
			header('Location: registerpage.php?action=joined');
			exit;

		//else catch the exception and show the error.
		} catch(PDOException $e) {
		    $error[] = $e->getMessage();
		}

	}

}

//define page title
$title = 'Conquistar 2020 - An online treasure hunt';

//include header template
require('layout/header.php');
?>


<div class="container">

	<div class="row">

	    <div class="col-xs-12 col-sm-8 col-md-6 col-sm-offset-2 col-md-offset-3">
			<form role="form" method="post" action="" autocomplete="off">
				<h2>Please Sign Up</h2>
				<p>Already a member? <a href='login.php'>Login</a></p>
				<hr>

				<?php
				//check for any errors
				if(isset($error)){
					foreach($error as $error){
						echo '<p class="bg-danger">'.$error.'</p>';
					}
				}

				//if action is joined show sucess
				if(isset($_GET['action']) && $_GET['action'] == 'joined'){
					echo "<div class='alert alert-success'>Registration successful!</div>";
				}
				?>

				<div class="form-group">
					<input type="text" name="username" id="username" class="form-control input-lg" placeholder="User Name" value="<?php if(isset($error)){ echo $_POST['username']; } ?>" tabindex="1">
				</div>
				<div class="form-group">
					<input type="email" name="email" id="email" class="form-control input-lg" placeholder="Email Address, this will be used to contact the winner" value="<?php if(isset($error)){ echo $_POST['email']; } ?>" tabindex="2">
				</div>
				<div class="form-group">
					<input type="text" name="ins" id="ins" class="form-control input-lg" placeholder="Institution"  tabindex="3">
				</div>
				<div class="row">
					<div class="col-xs-6 col-sm-6 col-md-6">
						<div class="form-group">
							<input type="password" name="password" id="password" class="form-control input-lg" placeholder="Password" tabindex="4">
						</div>
					</div>
					<div class="col-xs-6 col-sm-6 col-md-6">
						<div class="form-group">
							<input type="password" name="passwordConfirm" id="passwordConfirm" class="form-control input-lg" placeholder="Confirm Password" tabindex="5">
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-xs-6 col-md-6"><input type="submit" name="submit" value="Register" class="btn btn-primary btn-block btn-lg" tabindex="6"></div>
				</div>
			</form>
			<h3>General Instructions</h3>
			<ul>
			    <li>Register with your IISER email IDs only as it will be officially used by us and Karavaan for contacting the winner.</li>
			    <li>Teams can have <b>a maximum of 2 players</b>. You can also register individually.</li>
			    <li> If playing as a team, register with one of your teammates' IISER ID. Further details about the other participant will be asked on that ID only.</li>
			    <li>Do <b>NOT</b> use special characters, spaces, etc. in your username. Please use only letters and numbers.</li>
			    <li>After sign up you will receive a verification link on the  email registered. Verify by clicking on the link and then login. <u>You won't be able to login unless you have verified your account.</u></li>
			    <li>Non-IISER players can play but will not be eligible for prizes</li>
			    <li>Also, try to use a desktop or laptops as much as possible. Some levels might require it.</li>
			</ul>
		</div>
	</div>

</div>

<?php
//include header template
require('layout/footer.php');
?>
