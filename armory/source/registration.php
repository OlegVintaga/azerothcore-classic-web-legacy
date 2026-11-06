<?php
if(!defined("Armory") || !$config["Registration"] || $config["ExternalRegistrationPage"])
{
	header("Location: ../error.php");
	exit();
}
?>
<br /><br /><br />
<script type="text/javascript">
function validate_form(thisform)
{
	with (thisform)
	{
		if (name.value.length < 4 || name.value.length > 25)
			{alert("<?php echo $lang["length_name"] ?>");name.focus();return false;}
		if (pass.value.length < 5 || pass.value.length > 30)
			{alert("<?php echo $lang["length_pass"] ?>");pass.focus();return false;}
		if (pass2.value!=pass.value)
			{alert("<?php echo $lang["pass_verifipass"] ?>");pass2.focus();return false;}
		var pattern=/^[0-9a-z_.]+@[0-9a-z_^\.]+\.[a-z]{2,10}$/i;
		if (!pattern.test(email.value))
			{alert("<?php echo $lang["invalid_email"] ?>");email.focus();return false;}
	}
}
</script>
<center><span class="csearch-results-header"><?php echo $lang["create_acc"] ?></span><br /><br />
<form method="post" action="?searchType=registration" onsubmit="return validate_form(this)">
<table border="0">
<tr>
<td></td>
</tr>
<tr>
<td><span class="csearch-results-header"><?php echo $lang["realm"] ?>:</span></td>
<td><select class="reg" name="realm">
<?php
foreach($realms as $key => $data)
	echo "<option value=\"",urlencode($key),"\">",$key,"</option>";
?>
</select></td>
</tr>
<tr>
<td><span class="csearch-results-header"><?php echo $lang["acc_expansion"] ?></span></td>
<td><input type="radio" class="reg" name="expansion" value="0"><span class="reg"><?php echo $lang["classic"] ?></span>
<input type="radio" class="reg" name="expansion" value="1" checked><span class="reg"><?php echo $lang["tbc"] ?></span>
<input type="radio" class="reg" name="expansion" value="2"><span class="reg"><?php echo $lang["wotlk"] ?></span></td>
</tr>
<tr>
<td><span class="csearch-results-header"><?php echo $lang["acc_name"] ?>:</span></td>
<td><input class="reg" maxlength="25" type="text" name="name" size="30"></td>
</tr>
<tr>
<td><span class="csearch-results-header"><?php echo $lang["acc_password"] ?>:</span></td>
<td><input class="reg" maxlength="30" type="password" name="pass" size="30"></td>
</tr>
<tr>
<td><span class="csearch-results-header"><?php echo $lang["verify_pass"] ?>:</span></td>
<td><input class="reg" maxlength="30" type="password" name="pass2" size="30"></td>
</tr>
<tr>
<td><span class="csearch-results-header"><?php echo $lang["email"] ?>:</span></td>
<td><input class="reg" maxlength="60" type="text" name="email" size="30"></td>
</tr>
</table>
<br />
<center><input name="reg" value="<?php echo $lang["create"] ?>" style="border: 1px solid; font-weight: bold; background-color: rgb(0, 0, 0); color: rgb(255, 172, 4);" type="submit"></center>
</form>
</center>
<?php
// ============================================================================
// Регистрация по канону AzerothCore: SRP6, salt/verifier (binary(32)) в
// auth-базе (account); gmlevel — в account_access (RealmID = -1); карточка
// сайта (website_accounts) — в веб-базе. Функции SRP6 те же, что в login.php.
// ============================================================================
if (!function_exists('getRegistrationData'))
{
    function calculateSRP6Verifier($username, $password, $salt)
    {
        $g = gmp_init(7);
        $N = gmp_init('894B645E89E1535BBDAD5B8B290650530801B18EBFBF5E8FAB3C82872A3E9BB7', 16);
        $h1 = sha1(strtoupper($username . ':' . $password), TRUE);
        $h2 = sha1($salt . $h1, TRUE);
        $h2 = gmp_import($h2, 1, GMP_LSW_FIRST);
        $verifier = gmp_powm($g, $h2, $N);
        $verifier = gmp_export($verifier, 1, GMP_LSW_FIRST);
        $verifier = str_pad($verifier, 32, chr(0), STR_PAD_RIGHT);
        return $verifier;
    }
    function getRegistrationData($username, $password)
    {
        $salt = random_bytes(32);
        $verifier = calculateSRP6Verifier($username, $password, $salt);
        return array($salt, $verifier);
    }
}
if(isset($_POST["reg"]) && isset($_POST["name"]) && isset($_POST["pass"]) && isset($_POST["email"]))
{
	$reg_ip = $_SERVER["REMOTE_ADDR"];
	if($DB->selectCell("SELECT `ip` FROM `ip_banned` WHERE `ip` = ? LIMIT 1", $reg_ip))
		echo "<center>",$lang["ip"]," ",$reg_ip," ",$lang["banned"],"</center>";
	else if($config["LockReg"] && $DB->selectCell("SELECT COUNT(*) FROM `account` WHERE `last_ip` = ?", $reg_ip) >= $config["LockReg"])
		echo "<center>",$lang["max_ip"]," (",$config["LockReg"],")</center>";
	else
	{
		$username = strtoupper(trim(stripslashes($_POST["name"])));
		$pass = trim(stripslashes($_POST["pass"]));
		$email = trim(stripslashes($_POST["email"]));
		$expansion = isset($_POST["expansion"]) ? min(2, max(0, (int)$_POST["expansion"])) : 2;
		$name_len = strlen($username);
		$pass_len = strlen($pass);
		if($name_len > 4 && $name_len < 25 && $pass_len > 5 && $pass_len < 30 && preg_match("|^[0-9a-z_.]+@[0-9a-z_^\.]+\.[a-z]{2,10}$|i", $email))
		{
			if($DB->selectCell("SELECT `username` FROM `account` WHERE `username` = ? LIMIT 1", $username))
				echo "<center>",$lang["name_exist"],".<center>";
			else
			{
				list($salt, $verifier) = getRegistrationData($username, $pass);
				$acc_id = $DB->query("INSERT INTO `account` SET `username` = ?, `salt` = ?, `verifier` = ?,
				                             `email` = ?, `reg_mail` = ?, `locked` = ?, `last_ip` = ?, `expansion` = ?",
				                     $username, $salt, $verifier, $email, $email,
				                     (int)$config["LockAcc"], $reg_ip, $expansion);
				if ($acc_id)
				{
					// gmlevel выше игрока — в account_access (доступ ко всем реальмам)
					if ((int)$config["GmLevel"] > 0)
						$DB->query("REPLACE INTO `account_access` SET `id` = ?, `gmlevel` = ?, `RealmID` = -1, `Comment` = 'registration'",
						           $acc_id, (int)$config["GmLevel"]);
					// карточка аккаунта сайта (веб-база = armory-подключение)
					$ARDB->query("INSERT INTO `website_accounts` SET `account_id` = ?, `registration_ip` = ?, `activation_code` = NULL",
					             $acc_id, $reg_ip);
					echo "<center>",$lang["acc_created"]," ",$username," ",$lang["complete"],".</center>";
				}
				else
					echo "<center>",$lang["name_exist"],".<center>";
			}
		}
	}
}
?>