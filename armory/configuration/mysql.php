<?php
// ============================================================================
// Armory (MBA) — доступы к БД. Выводятся из конфигурации сайта, хардкода нет:
//   - auth (аккаунты: account, account_access, ip_banned)
//       — $realmd из config/config-protected.php (пишет установщик);
//   - world / characters — таблица website_realm_settings веб-базы
//       (админка сайта -> Realms);
//   - armory (справочники DBC, кэши, conf_client/conf_lang)
//       — $websitedb (та же веб-база, туда установщик импортирует armory-дампы);
//   - имена реальмов — VIEW realmlist в веб-базе (read-only VIEW в auth-базу).
// Маркер дополнения в $realms = 3 (WotLK): обёртка рассчитана на
// AzerothCore 3.3.5, от него зависят скины/меню в index.php.
// ============================================================================

$__siteConf = dirname(__FILE__) . '/../../config/config-protected.php';
if (!is_readable($__siteConf)) {
    die('Armory: файл config/config-protected.php не найден или не читается. Запусти установщик (install/).');
}
include $__siteConf;
if (!isset($realmd, $websitedb) || !is_array($realmd) || !is_array($websitedb)) {
    die('Armory: config/config-protected.php в старом формате (нет $websitedb). Перезапусти установщик (install/).');
}

$__link = @mysqli_connect($websitedb['db_host'], (string)$websitedb['db_username'], (string)$websitedb['db_password'],
    $websitedb['db_name'], (int)$websitedb['db_port']);
if (!$__link) {
    die('Armory: не удалось подключиться к веб-базе: ' . mysqli_connect_error());
}
mysqli_set_charset($__link, 'utf8mb4');

$realms        = array(); // "Имя реальма" => array(realmd_DB, characters_DB, mangosd_DB, armory_DB)
$realmd_DB     = array(); // индексы выше => array("host:port", "user", "pass", "dbname")
$characters_DB = array();
$mangosd_DB    = array();
$armory_DB     = array();

$__res = $__link->query("SELECT w.`id_realm`, w.`dbuser`, w.`dbpass`, w.`dbhost`, w.`dbport`,
                                w.`dbname`, w.`chardbname`, r.`name`
                         FROM `website_realm_settings` AS w
                         LEFT JOIN `realmlist` AS r ON r.`id` = w.`id_realm`
                         ORDER BY w.`id_realm`");
if (!$__res) {
    die('Armory: запрос website_realm_settings/realmlist в веб-базе не выполнен: ' . $__link->error);
}
while ($r = $__res->fetch_assoc()) {
    $i = count($realms) + 1;
    $realmName = ($r['name'] !== null && $r['name'] !== '') ? $r['name'] : ('Realm #' . $r['id_realm']);
    $realms[$realmName] = array(3, $i, $i, $i); // 3 = WotLK (AzerothCore 3.3.5)
    $characters_DB[$i]  = array($r['dbhost'] . ':' . $r['dbport'], $r['dbuser'], $r['dbpass'], $r['chardbname']);
    $mangosd_DB[$i]     = array($r['dbhost'] . ':' . $r['dbport'], $r['dbuser'], $r['dbpass'], $r['dbname']);
    $armory_DB[$i]      = array($websitedb['db_host'] . ':' . $websitedb['db_port'],
                                $websitedb['db_username'], $websitedb['db_password'], $websitedb['db_name']);
}
mysqli_close($__link);
if (!$realms) {
    die('Armory: в website_realm_settings нет ни одного реальма — добавь реальм (установщик/админка сайта).');
}

// Единая auth-база AzerothCore на все реальмы; ключи 1..3 — под маркер дополнения.
$__authConn = array($realmd['db_host'] . ':' . $realmd['db_port'], $realmd['db_username'],
                    $realmd['db_password'], $realmd['db_name']);
$realmd_DB[1] = $realmd_DB[2] = $realmd_DB[3] = $__authConn;

// Realm по умолчанию (используется в index.php, формах логина/регистрации)
if (!defined('DefaultRealmName'))
    define('DefaultRealmName', (string)array_key_first($realms));

/* Don't touch anything beyond this point. */
set_time_limit(0);
ini_set("default_charset", "UTF-8");
function execute_query($db_name, $query, $method = 0, $error = "")
{
    global $DB, $WSDB, $CHDB, $ARDB, $PBDB;
    $query_result = false;
    if ($method == 0) // query (array of assoc arrays)
    {
        if ($db_name == "realm")
            $query_result = $DB->query($query);
        elseif ($db_name == "armory")
            $query_result = $ARDB->query($query);
        elseif($db_name == "char")
            $query_result = $CHDB->query($query);
        elseif($db_name == "world")
            $query_result = $WSDB->query($query);
    }
    elseif ($method == 1) // row (single associated array)
    {
        if ($db_name == "realm")
            $query_result = $DB->selectRow($query);
        elseif ($db_name == "armory")
            $query_result = $ARDB->selectRow($query);
        elseif($db_name == "char")
            $query_result = $CHDB->selectRow($query);
        elseif($db_name == "world")
            $query_result = $WSDB->selectRow($query);
    }
    elseif ($method == 2) // cell (no array)
    {
        if ($db_name == "realm")
            $query_result = $DB->selectCell($query);
        elseif ($db_name == "armory")
            $query_result = $ARDB->selectCell($query);
        elseif($db_name == "char")
            $query_result = $CHDB->selectCell($query);
        elseif($db_name == "world")
            $query_result = $WSDB->selectCell($query);
    }
    if (!$db_name)
        die($error."Database not chosen");

	if($query_result)
		return $query_result;
	elseif (!$query_result && $error)
	{
		die($error);
        return false;
	}
    return false;
}
function switchConnection($db_type, $realm_name)
{
    // Наследие старых MangosWeb-доступов: переключение выполняется
    // в initialize_realm() (configuration/functions.php), здесь — заглушка.
}
?>
