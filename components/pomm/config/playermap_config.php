<?php
// ============================================================================
// POMM (карта онлайн-игроков) — конфигурация веб-обвязки AzerothCore.
// НИЧЕГО не хардкодим: доступы к БД берутся из конфига сайта
// (config/config-protected.php, установщик), доступ к world/characters —
// из веб-таблицы website_realm_settings (админка -> Realms).
// realmlist/uptime/account_access — VIEW в веб-базе на auth-базу (read-only).
// ============================================================================

// Язык интерфейса карты (доступен map_english.php / zone_names_english.php)
$language         = "en";
$site_encoding    = "utf-8";

// ---- Доступы сайта (пишет установщик в config/config-protected.php) ----
$__siteConf = dirname(__FILE__).'/../../../config/config-protected.php';
$__auth     = null;  // auth-база ($realmd)
$__web      = null;  // веб-база  ($websitedb)
if (is_readable($__siteConf)) {
    include($__siteConf);
    if (isset($realmd) && is_array($realmd))     $__auth = $realmd;
    if (isset($websitedb) && is_array($websitedb)) $__web = $websitedb;
    unset($realmd, $websitedb);
}
if (!$__web) $__web = $__auth; // старый конфиг без $websitedb: fallback на $realmd
if (!$__web) {
    die('PomM: не найден config/config-protected.php — сначала запусти установщик сайта.');
}

// ---- Подключение к ВЕБ-базе (realmlist/uptime/account_access — VIEW) ----
$realm_db['addr']     = $__web['db_host'];
$realm_db['port']     = isset($__web['db_port']) ? (int)$__web['db_port'] : 3306;
$realm_db['user']     = $__web['db_username'];
$realm_db['pass']     = $__web['db_password'];
$realm_db['name']     = $__web['db_name'];
$realm_db['encoding'] = 'utf8mb4';

// ---- Веб-настройки реальмов (website_realm_settings) через то же подключение ----
$__con = @mysqli_connect($realm_db['addr'], $realm_db['user'], $realm_db['pass'],
                         $realm_db['name'], $realm_db['port']);
if (!$__con) {
    die('PomM: не удалось подключиться к веб-базе сайта.');
}
mysqli_set_charset($__con, 'utf8mb4');
$__rs = mysqli_query($__con, "SELECT id_realm, dbhost, dbport, dbuser, dbpass, chardbname
                              FROM website_realm_settings");
$world_db      = array();
$characters_db = array();
$server        = array();
while ($__r = mysqli_fetch_assoc($__rs)) {
    $__id = (int)$__r['id_realm'];
    // DBLayer принимает "host:port" — собираем адрес как в старом формате
    $world_db[$__id]['addr']     = $__r['dbhost'].':'.((int)$__r['dbport'] ?: 3306);
    $world_db[$__id]['user']     = $__r['dbuser'];
    $world_db[$__id]['pass']     = $__r['dbpass'];
    $world_db[$__id]['name']     = $__r['chardbname']; // игровые world-таблицы карте не нужны
    $world_db[$__id]['encoding'] = 'utf8mb4';

    $characters_db[$__id]['addr']     = $world_db[$__id]['addr'];
    $characters_db[$__id]['user']     = $__r['dbuser'];
    $characters_db[$__id]['pass']     = $__r['dbpass'];
    $characters_db[$__id]['name']     = $__r['chardbname'];
    $characters_db[$__id]['encoding'] = 'utf8mb4';

    // адрес/порт игрового сервера — из VIEW realmlist (auth-база, read-only)
    $__rl = mysqli_query($__con,
        "SELECT address, port FROM realmlist WHERE id=".$__id." LIMIT 1");
    $__rlr = $__rl ? mysqli_fetch_assoc($__rl) : null;
    $server[$__id]['addr']          = $__rlr ? $__rlr['address'] : '127.0.0.1';
    $server[$__id]['addr_wan']      = $server[$__id]['addr'];
    $server[$__id]['game_port']     = $__rlr ? (int)$__rlr['port'] : 8085;
    $server[$__id]['rev']           = '';
    $server[$__id]['both_factions'] = true;
}
mysqli_close($__con);
if (!$characters_db) {
    die('PomM: в website_realm_settings нет ни одного реальма (запусти установщик / проверь админку).');
}

// ---- Сервер ----
// AzerothCore: gmlevel хранится в account_access (как в TrinityCore) -> 1
$server_type        = 1;

// === Player Map configuration === //

// GM на карте
$gm_online                         = 1; // показывать GM-игроков на карте [1/0]
$gm_online_count                   = 1; // учитывать GM в общем счётчике онлайна [1/0]

$map_gm_show_online_only_gmoff     = 1; // скрывать точку GM, только если он в '.gm off' [1/0]
$map_gm_show_online_only_gmvisible = 1; // скрывать точку GM, только если он в '.gm visible off' [1/0]
$map_gm_add_suffix                 = 1; // добавлять '{GM}' к имени [1/0]
$map_status_gm_include_all         = 0; // учитывать всех GM в статусе [1/0]

// статус-окно:
$map_show_status =  1;                  // показывать окно статуса сервера [1/0]
$map_show_time   =  1;                  // показывать таймер автообновления [1/0]
$map_time        =  10;                 // период автообновления карты (сек), 0 — не обновлять

// времена показа строк статуса, мс (не ставь < 1500), 0 — выключить
$map_time_to_show_uptime    = 5000;
$map_time_to_show_maxonline = 5000;
$map_time_to_show_gmonline  = 5000;

$developer_test_mode =  false;

$multi_realm_mode    =  true;

?>
