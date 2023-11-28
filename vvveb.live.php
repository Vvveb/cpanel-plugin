<?php
require '/usr/local/cpanel/php/cpanel.php';

$cpanel = new CPANEL();
echo $cpanel->header( 'Vvveb CMS' );
$accountName = $cpanel->cpanelprint('$user');
$hostname = $cpanel->cpanelprint('$hostname');

$domainData = [];
// Call the API
$response = $cpanel->uapi(
    'DomainInfo',
    'domains_data'
);

// Handle the response
if ($response['cpanelresult']['result']['status']) {
    $data = $response['cpanelresult']['result']['data'];
    // Do something with the $data
    // So you can see the data shape we print it here.
    //var_dump($data);
    $domainData = $data['addon_domains'] ?? [];

    if (isset($data['main_domain'])) {
		$domainData['main'] = $data['main_domain'];
		if (isset($data['sub_domains'])) {
			$domainData['main']['sub_domains'] = $data['sub_domains'];
		}
	} else {
		if (isset($data['sub_domains'])) {
			$domainData = $data['sub_domains'];
		}
	}
	
}
else {
    // Report errors:
    echo '<pre>';
    var_dump($response['cpanelresult']['result']['errors']);
    echo '</pre>';
}

//get mysql host name
$hostname = '';
$response = $cpanel->uapi(
    'Mysql',
    'get_server_information'
);

// Handle the response
if ($response['cpanelresult']['result']['status']) {
    $data = $response['cpanelresult']['result']['data'];
    // Do something with the $data
    // So you can see the data shape we print it here.
    $hostname = $data['host'];
} else {
    // Report errors:
    echo '<pre>';
    var_dump($response['cpanelresult']['result']['errors']);
    echo '</pre>';
}

function randomStr($length = 16) {
	$string = '';

	while (($len = strlen($string)) < $length) {
		$size = $length - $len;

		$bytes = random_bytes($size);

		$string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
	}

	return $string;
}

function download($url) {
	$result = false;

	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		$result = curl_exec($ch);
		curl_close($ch);
	} else {
		if (ini_get('allow_url_fopen') == '1') {
			$context_options = [
				'http' => [
					'timeout'       => 5,
					'ignore_errors' => 1,
				],
			];
			$context  = stream_context_create($context_options);
			$result   = file_get_contents($url, 'r', $context);
		}
	}

	return $result;
}


function saveDownload($url) {
	//$temp = tmpfile();
	$f    = false;
	$temp = tempnam(sys_get_temp_dir(), 'vvveb_cms');

	if ($content = download($url)) {
		$f  = file_put_contents($temp, $content, LOCK_EX);

		return $temp;
	}

	return $f;
}

function printStatus($status) {
	echo "$status<br>";
}

//generate mysql database defaults, limit database name and user to max 30 chars
$database               = substr($accountName, 0, 15) . '_vvveb_' . randomStr(8);
$database_user          = substr($accountName, 0, 15) . '_vvveb_' . randomStr(8);
$database_user_password = randomStr(30);

function installVvveb($cpanel) {
	global $domainData;
	
	$documentroot    = $_POST['documentroot'];
	$domain          = $_POST['domain'];
	$title           = $_POST['title'];
	$email           = $_POST['email'];
	$password        = $_POST['password'];
	$username        = $_POST['username'];
	$database_engine = $_POST['database_engine'];
	
	$database               = $_POST['database'];
	$database_user          = $_POST['database_user'];
	$database_user_password = $_POST['database_user_password'];
	$hostname               = $_POST['hostname'];
	$port                   = $_POST['port'] ?? 3306;
	
	
	$url = 'https://www.vvveb.com/latest.zip';
	$temp = tempnam(sys_get_temp_dir(), 'vvveb_cms');
	printStatus("Downloading $url");
	
	if ($zipFile = saveDownload($url)) {

		$zip = new ZipArchive;
		$res = $zip->open($zipFile);
		if ($res === TRUE) {
		  $zip->extractTo($documentroot);
		  printStatus("Unzip successful!");
		  $zip->close();
		} else {
		  printStatus("Error unzipping $temp to $documentroot");
		  return;
		}
	} else {
		  printStatus("Error downloading $url");
		  return;
	}

	/*
	// setup_db_and_user not supported yet
	$database               = '';
	$database_user          = '';
	$database_user_password = '';
	$hostname               = '';
	$port               = '';
	if ($database_engine == 'mysqli') { 
		// Call the API
		$response = $cpanel->uapi(
			'Mysql',
			'setup_db_and_user',
			array (
				'prefix' => 'vvveb',
			)
		);

		// Handle the response
		if ($response['cpanelresult']['result']['status']) {
			$data = $response['cpanelresult']['result']['data'];
			// Do something with the $data
			// So you can see the data shape we print it here.
			//var_dump($data);
			$database               = $data['database'];
			$database_user          = $data['database_user'];
			$database_user_password = $data['database_user_password'];
			$hostname               = $data['hostname'];
			$port                   = $data['port'];
		}
		else {
			// Report errors:
			printStatus("Mysql database create error");
			echo '<pre>';
			var_dump($response['cpanelresult']['result']['errors']);
			echo '</pre>';
			return;
		}
	} */
	if ($database_engine == 'mysqli') { 
			// create database
			$response = $cpanel->uapi(
				'Mysql',
				'create_database',
				array (
					'name' => $database,
				)
			);

			// Handle the response
			if ($response['cpanelresult']['result']['status']) {
				$data = $response['cpanelresult']['result']['data'];

					printStatus("Mysql database <strong>$database</strong> created");

					// create user
					$response = $cpanel->uapi(
						'Mysql',
						'create_user',
						array (
							'name' => $database_user,
							'password' => $database_user_password,
						)
					);

					// Handle the response
					if ($response['cpanelresult']['result']['status']) {
						$data = $response['cpanelresult']['result']['data'];
							
							printStatus("Mysql user <strong>$database_user</strong> created");

							// add user to database
							$response = $cpanel->uapi(
								'Mysql',
								'set_privileges_on_database',
								array (
									'user' => $database_user,
									'database' => $database,
									'privileges' => 'ALL PRIVILEGES',
								)
							);

							// Handle the response
							if ($response['cpanelresult']['result']['status']) {
								$data = $response['cpanelresult']['result']['data'];
								// Do something with the $data
								// So you can see the data shape we print it here.
								
								printStatus("Mysql user <strong>$database_user</strong> assigned to <strong>$database</strong>");
							}
							else {
								printStatus("Failed to set privileges for <strong>$database_user</strong> to <strong>$database</strong>");
								// Report errors:
								echo '<pre>';
								var_dump($response['cpanelresult']['result']['errors']);
								echo '</pre>';
								return;
							}

					}
					else {
						printStatus("Failed to create Mysql user <strong>$database_user</strong>");
						echo '<pre>';
						var_dump($response['cpanelresult']['result']['errors']);
						echo '</pre>';
						return;
					}
				
			}
			else {
				// Report errors:
				printStatus("Failed to create Mysql database <strong>$database</strong>'");
				echo '<pre>';
				var_dump($response['cpanelresult']['result']['errors']);
				echo '</pre>';
				return;
			}
	}

	echo '<hr>';
	printStatus("Please wait, installing ...");
	$command = "/usr/local/bin/php $documentroot/cli.php install host=$hostname user=$database_user port=$port password=$database_user_password database=$database admin[email]=$email admin[password]=$password engine=$database_engine settings[title]='$title'";
	printStatus('Running install command: ' .str_replace("[password]=$password", '[password]=******', $command));
	echo '<hr>';
	if ($result = shell_exec($command)) {
		$data = json_decode($result, true);
		if ($data && isset($data['success'])) {
			$url = '';
			foreach ($domainData as $domain) {
				if ($domain['documentroot'] == $documentroot) {
					$url = $domain['domain'];
					break;
				}
								
				if (isset($domain['sub_domains'])) {
					foreach ($domain['sub_domains'] as $subdomain) {
						if ($subdomain['documentroot'] == $documentroot) {
							$url = $subdomain['domain'];
							break 2;
						}
					}
				}
			}
			if (isset($data['success'])) {
				foreach ($data['success'] as $message) echo printStatus($message);
			}
			printStatus('Vvveb CMS Installed!');
			echo '<hr>';
			printStatus("<a href='//$url/admin/' target='_blank'>Login to admin dashboard</a> | <a href='//$url' target='_blank'>View website</a>");
			echo '<hr>';
			if (isset($data['errors'])) {
				foreach ($data['errors'] as $message) echo printStatus($message);
			}
			if (isset($data['requirements'])) {
				foreach ($data['requirements'] as $message) echo printStatus($message);
			}
			//echo $result;
		} else {
			printStatus('Installation failed with:');
			echo '<pre>';
			print_r($data);			
			echo '</pre>';
			echo $result;
		}
	} else {
		printStatus('Error running cli.php!');
		echo $result;
	}
}

if ($_POST) {
	installVvveb($cpanel);
} else { 
?>
<form method="post">
    <h3>Install Vvveb CMS</h3>
    <hr>
    <div class="form-group">
        <label for="documentroot">Domain</label>

        <select id="documentroot" name="documentroot" value="Vvveb" class="form-control" onchange="domain.value=this.value" required>
        <?php foreach ($domainData as $domain) { ?>
		<option value="<?php echo $domain['documentroot'] ?? '';?>"><?php echo $domain['domain'];?></option>
		<?php if (isset($domain['sub_domains'])) { ?>
			<optgroup label="<?php echo $domain['domain'];?>">
			 <?php foreach ($domain['sub_domains'] as $subDomain) { ?>
			 <option value="<?php echo $subDomain['documentroot'] ?? '';?>"><?php echo $subDomain['domain'];?></option>
			 <?php } ?>
			</optgroup>
			<?php  
			}
		} ?>
       </select>
       <input type="hidden" id="domain" name="domain" value=""/>
    </div>

    <div class="form-group">
        <label for="database_engine">Database</label>
        <select id="database_engine" name="database_engine" class="form-control" onchange="toggleMysql(this)" required>
		  <option value="mysqli">MySql</option>
		  <option value="sqlite">SQLite</option>
      </select>
      
    </div>

	<div id="mysql-configuration">
    
		<a href="#" style="margin: 0rem 0 1rem;display: block;" onclick="toggleAdvanced()">Advanced</a>

		<div id="advanced-configuration" class="well" style="display:none">
			<h5>Mysql configuration</h5>
			<div class="form-group">
				<label for="hostname">Host name</label>
				<input type="text" id="hostname" name="hostname" value="<?php echo $hostname;?>" class="form-control" required/>
			</div>
			<div class="form-group">
				<label for="database">Database</label>
				<input type="text" id="database" name="database" value="<?php echo $database;?>" class="form-control" required/>
			</div>
			<div class="form-group">
				<label for="database_user">Database user</label>
				<input type="text" id="database_user" name="database_user" value="<?php echo $database_user;?>" class="form-control" required/>
			</div>
			<div class="form-group">
				<label for="database_user_password">Database user password</label>
				<input type="text" id="database_user_password" name="database_user_password" value="<?php echo $database_user_password;?>" class="form-control" required/>
			</div>
		</div>
    </div>
    
    <div class="form-group">
        <label for="title">Site title</label>
        <input type="text" id="title" name="title" value="Vvveb" class="form-control" required/>
    </div>
    

    <div class="form-group">
        <label for="username">Administration username</label>
        <input type="text" name="username" id="username" value="admin" class="form-control" required/>
    </div>

	<div class="form-group">
		<label for="email">Administration email</label>
		<input type="email" name="email" id="email" value="" class="form-control" required/>
	</div>

	<div class="form-group">
		<label for="password">Password</label>
		<input type="password" id="password" name="password" value="" class="form-control" required/>
	</div>

	<div class="form-group">
		<button type="submit" class="btn btn-primary">Install</button>
	</div>

</form>
<script>
function toggleMysql(element) {
	let mysqlConfiguration = document.getElementById("mysql-configuration");
	if (element.value == "mysqli") {
		mysqlConfiguration.style.display = "block"
	} else {
		mysqlConfiguration.style.display = "none"
	}
	return false;
}

function toggleAdvanced() {
	let advancedConfiguration = document.getElementById("advanced-configuration");
	if (advancedConfiguration.style.display == "none") {
		advancedConfiguration.style.display = "block"
	} else {
		advancedConfiguration.style.display = "none"
	}
	
	return false;
}
</script> 
<?php	
}

echo $cpanel->footer();
$cpanel->end();
?>
