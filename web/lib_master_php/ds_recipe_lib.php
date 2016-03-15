<?php

require('../vendor/autoload.php');
// Utilities for DocuSign Recipes

class DS_recipe_lib {
	
	public $ds_user_email; 
	public $ds_user_pw; 
	public $ds_integration_id;
	public $ds_account_id;
	public $ds_base_url;
	public $ds_auth_header;
	public $ds_api_url = "https://demo.docusign.net/restapi"; // change for production
	public $my_url; // url of the overall script
	public $temp_email_server = "mailinator.com"; // Used for throw-away email addresses
	public $email_count = 2; // Used to make email addresses unique.
	private $b64_pw_prefix="ZW5jb";
	private $b64_pw_clear_prefix="encoded";

	function __construct($ds_user_email, $ds_user_pw, $ds_integration_id, $ds_account_id = null) {
		// if $ds_account_id is null then the user's default account will be used
		// if $ds_user_email is "***" then environment variables are used
		
		if ($ds_user_email == "***") {
			$ds_user_email = $this->get_env("DS_USER_EMAIL");
			$ds_user_pw = $this->get_env("DS_USER_PW");
			$ds_integration_id = $this->get_env("DS_INTEGRATION_ID");
		}
			
		if (!is_string($ds_user_email) || strlen($ds_user_email) < 4) {
			exit ("<h3>No DocuSign login settings! Either set in the script or use environment variables DS_USER_EMAIL, DS_USER_PW, and DS_INTEGRATION_ID</h3>");
				// If the environment variables are set, check that the 
				// your http://us.php.net/manual/en/ini.core.php#ini.variables-order ini setting includes "E" in the string.
				// See http://php.net/manual/en/reserved.variables.environment.php				
		}

		// Decode the pw if it is in base64
		if ($this->b64_pw_prefix === substr($ds_user_pw, 0, strlen($this->b64_pw_prefix))) {
			// it was encoded
			$ds_user_pw = base64_decode($ds_user_pw);
			$ds_user_pw = substr($ds_user_pw, strlen($this->b64_pw_clear_prefix)); // remove prefix
		}
		$this->ds_user_pw = $ds_user_pw;

		$this->ds_user_email = $ds_user_email; 
		$this->ds_integration_id = $ds_integration_id;
		$this->ds_account_id = $ds_account_id;

		// construct the authentication header:
		$this->ds_auth_header = "<DocuSignCredentials><Username>" . $ds_user_email . 
			"</Username><Password>" . $ds_user_pw . "</Password><IntegratorKey>" . 
			$ds_integration_id . "</IntegratorKey></DocuSignCredentials>";
	}
	
		
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	
	public function login() {
		// // Login (to retrieve baseUrl and accountId)
		$config = new DocuSign\eSign\Configuration();
	 	$config->setHost($this->ds_api_url);
	 	$config->addDefaultHeader("X-DocuSign-Authentication", $this->ds_auth_header);
		
		$apiClient = new DocuSign\eSign\ApiClient($config);
	 	$authenticationApi = new DocuSign\eSign\Api\AuthenticationApi($apiClient);

		$options = new \DocuSign\eSign\Api\AuthenticationApi\LoginOptions();

	 	$loginInformation = $authenticationApi->login($options);

		if (!isset($loginInformation) || count($loginInformation) < 1) {
			return (['ok' => false, 'errMsg' => "Error calling DocuSign login"]);
		}

	 	$response = json_decode($loginInformation, true);
		// Example response:
		// { "loginAccounts": [ 
		//		{ "name": "DocuSign", "accountId": "1374267", 
		//		  "baseUrl": "https://demo.docusign.net/restapi/v2/accounts/1374267", 
		//        "isDefault": "true", "userName": "Recipe Login", 
		//        "userId": "d43a4a6a-dbe7-491e-9bad-8f7b4cb7b1b5", 
		//        "email": "temp2+recipe@kluger.com", "siteDescription": ""
		//      } 
		// ]}
		//
		
		$found = false;
		$errMsg = "";
		// Get account_id and base_url. 
		if ($this->ds_account_id == null) {
			// Get default
			foreach ($response["loginAccounts"] as $account) {
				if ($account["isDefault"] === "true") {
					$this->ds_account_id = $account["accountId"];
					$this->ds_base_url = $account["baseUrl"];
					$found = true;
					break;
				}
			}
			if (!$found) {
				$errMsg = "Could not find default account for the username.";
			}
		} else {
			// get the account's base_url
			foreach ($response["loginAccounts"] as $account) {
				if ($account["accountId"] == $this->ds_account_id) {
					$this->ds_base_url = $account["baseUrl"];
					$found = true;
					break;
				}
			}
			if (!$found) {
				$errMsg = "Could not find baseUrl for account " . $this->ds_account_id;
			}
		}
		return ['ok' => $found, 'errMsg' => $errMsg];
	}
		
	
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################

	public function curl_add_ca_info($curl) {
		// Add the bundle of trusted CA information to curl
		// In most environments, the list of trusted of CAs is set 
		// at the OS level. However, some PAAS services such as 
		// MS Azure App Service enable you to trust just the CAs that you
		// choose. So that's what we're doing here.
		// The usual list of trusted CAs is from Mozilla via the Curl
		// people. See 
	
		curl_setopt($curl, CURLOPT_CAINFO, getcwd() . "/assets_master/ca-bundle.crt");
	}
	
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	
	public function get_signer_name($name) {
		if (!$name || $name == "***") {
			$name = $this->get_fake_name();
		}
		return $name;
	}
	
	public function get_signer_email($email) {
		if ($email && $email != "***") {
			return $email;
		} else {
			return $this->make_temp_email();
		}
	}
	
	public function make_temp_email() {
	 	// just create something unique to use with maildrop.cc
		// Read the email at http://maildrop.cc/inbox/<mailbox_name>	
		$ip = "100";
		$this->email_count = pow($this->email_count, 2);
		if (isset($_SERVER) && in_array('REMOTE_ADDR', $_SERVER)) {
			$ip = substr($_SERVER['REMOTE_ADDR'], -3);
		}
		
		$email = (string)$this->email_count . (string)time() . $ip;
		$email = base64_encode ($email);
		$email = "a" . preg_replace("/[^A-Za-z0-9]/", '', $email); // Strip non alphanumeric
		
		return $email . "@" . $this->temp_email_server;
	}
	
	public function get_temp_email_access($email) {
		// just create something unique to use with maildrop.cc
		// Read the email at https://mailinator.com/inbox2.jsp?public_to=<mailbox_name>
		$url = "https://mailinator.com/inbox2.jsp?public_to=";
		$parts = explode("@", $email);
		if ($parts[1] !== $this->temp_email_server) {
			return false;
		}
		return $url . $parts[0];
	}
	
	public function get_temp_email_access_qrcode($address) {
		// $url = "http://open.visualead.com/?size=130&type=png&data=";
		$url = "https://chart.googleapis.com/chart?cht=qr&chs=150x150&chl=";
		$url .= urlencode ($address);
		$size = 150;
		$html = "<img height='$size' width='$size' src='$url' alt='QR Code' style='margin:10px 0 10px;' />";
		return $html;
	}
	
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################

	public function get_my_url($url) {
		# Dynamically determine the script's url
		# For production use, this is not a great idea. Instead, set it
		# explicitly. Remember that for production, webhook urls must start with
		# https!
		if ($url) {
			# already set
			$this->my_url = $url;
		} else {
			$this->my_url = $this->rm_queryparameters($this->full_url($_SERVER));
		}
		return $this->my_url;
	}
	
	# See http://stackoverflow.com/a/8891890/64904
	private function url_origin( $s, $use_forwarded_host = false ) {
	    $ssl      = ( ! empty( $s['HTTPS'] ) && $s['HTTPS'] == 'on' );
	    $sp       = strtolower( $s['SERVER_PROTOCOL'] );
	    $protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
	    $port     = $s['SERVER_PORT'];
	    $port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
	    $host     = ( $use_forwarded_host && isset( $s['HTTP_X_FORWARDED_HOST'] ) ) ? 
			$s['HTTP_X_FORWARDED_HOST'] : ( isset( $s['HTTP_HOST'] ) ? $s['HTTP_HOST'] : null );
	    $host     = isset( $host ) ? $host : $s['SERVER_NAME'] . $port;
	    return $protocol . '://' . $host;
	}

	private function full_url( $s, $use_forwarded_host = false ) {
	    return $this->url_origin( $s, $use_forwarded_host ) . $s['REQUEST_URI'];
	}
	
	private function rm_queryparameters ($in) {
		$parts = explode ("?", $in);
		return $parts[0];
	}
	
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################	
    	
	public function get_fake_name() {
		$first_names = ["Verna", "Walter", "Blanche", "Gilbert", "Cody", "Kathy",
		"Judith", "Victoria", "Jason", "Meghan", "Flora", "Joseph", "Rafael",
		"Tamara", "Eddie", "Logan", "Otto", "Jamie", "Mark", "Brian", "Dolores",
		"Fred", "Oscar", "Jeremy", "Margart", "Jennie", "Raymond", "Pamela",
		"David", "Colleen", "Marjorie", "Darlene", "Ronald", "Glenda", "Morris",
		"Myrtis", "Amanda", "Gregory", "Ariana", "Lucinda", "Stella", "James",
		"Nathaniel", "Maria", "Cynthia", "Amy", "Sylvia", "Dorothy", "Kenneth",
		"Jackie"];
		$last_names = ["Francisco", "Deal", "Hyde", "Benson", "Williamson", 
		"Bingham", "Alderman", "Wyman", "McElroy", "Vanmeter", "Wright", "Whitaker", 
		"Kerr", "Shaver", "Carmona", "Gremillion", "O'Neill", "Markert", "Bell", 
		"King", "Cooper", "Allard", "Vigil", "Thomas", "Luna", "Williams", 
		"Fleming", "Byrd", "Chaisson", "McLeod", "Singleton", "Alexander", 
		"Harrington", "McClain", "Keels", "Jackson", "Milne", "Diaz", "Mayfield", 
		"Burnham", "Gardner", "Crawford", "Delgado", "Pape", "Bunyard", "Swain", 
		"Conaway", "Hetrick", "Lynn", "Petersen"];

		$first = $first_names[mt_rand(0, count($first_names) - 1)];
		$last = $last_names[mt_rand(0, count($last_names) - 1)];
		return $first . " " . $last;
	}	
		
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################
	########################################################################	
    	
	private function var_dump_ret($mixed = null) {
	  ob_start();
	  var_dump($mixed);
	  $content = ob_get_contents();
	  ob_end_clean();
	  return $content;
	}
	
	private function get_env($name) {
		// Turns out that sometimes the environment variables are
		// passed by $_SERVER for Apache. ?!
		
		if ($_ENV[$name] != null) {
			$result = $_ENV[$name];
		} else {
			$result = $_SERVER[$name];
		}
		return $result;
	}
	
	## FIN ##
}	
	
