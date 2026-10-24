<?php
include('password.php');
class User extends Password{

    private $_db;

    function __construct($db){
    	parent::__construct();

    	$this->_db = $db;
    }

	private function get_user_hash($username){

		try {
			$stmt = $this->_db->prepare('SELECT password, username, memberID,level FROM members WHERE username = :username AND active="Yes" ');
			$stmt->execute(array('username' => $username));

			return $stmt->fetch();

		} catch(PDOException $e) {
			error_log($e->getMessage());
			return false;
		}
	}

	public function login($username,$password){

		$row = $this->get_user_hash($username);

		if($row && $this->password_verify($password,$row['password']) == 1){

		    if (!session_regenerate_id(true)) {
				return false;
		    }
		    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		    $_SESSION['loggedin'] = true;
		    $_SESSION['username'] = $row['username'];
		    $_SESSION['memberID'] = $row['memberID'];
		    $_SESSION['userlevel'] = $row['level'];
		    return true;
		}
	}

	public function logout(){
		$params = session_get_cookie_params();
		setcookie(session_name(), '', array(
			'expires' => time() - 3600,
			'path' => $params['path'],
			'domain' => $params['domain'],
			'secure' => $params['secure'],
			'httponly' => $params['httponly'],
			'samesite' => $params['samesite'],
		));
		$_SESSION = array();
		session_destroy();
	}

	public function is_logged_in(){
		if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true){
			return true;
		}
		else {return false;}
	}

}


?>
