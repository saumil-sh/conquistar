<?php
include('password.php');
class User extends Password{

    private $_db;
	// Keep unknown-user failures on the same password-hash path.
	private $_dummyPasswordHash = '$2y$10$/WDO/9xdnJTUs9ro3.Rf8uJv7WdroxQuVJrisrg28F8zpAI1SP3Ny';

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
		$hash = $row ? $row['password'] : $this->_dummyPasswordHash;
		$passwordMatches = $this->password_verify($password, $hash);

		if($row && $passwordMatches){

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

		return false;
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
