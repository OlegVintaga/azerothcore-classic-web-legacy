<?php
require_once("func.php");

require_once("config/playermap_config.php");
require_once 'libs/data_lib.php';

// AzerothCore: realm выбирается параметром ?realm=<id_realm> (как в rest сайта);
// по умолчанию — первый реальм из website_realm_settings.
$realm_ids = array_keys($characters_db);
$realm_id  = isset($_GET['realm']) ? (int)$_GET['realm'] : (int)$realm_ids[0];
if (!isset($characters_db[$realm_id])) {
    $realm_id = (int)$realm_ids[0];
}

$server_arr = $server;

if (isset($_COOKIE["lang"])) {
    $lang = "en";
    if (!file_exists("map_".$lang.".php") && !file_exists("zone_names_".$lang.".php")) {
        $lang = $language;
    }
} else {
    $lang = $language;
}


$database_encoding = $realm_db["encoding"];

$server = $server_arr[$realm_id]["addr"];
$port = $server_arr[$realm_id]["game_port"];

$host = $characters_db[$realm_id]["addr"];
$user = $characters_db[$realm_id]["user"];
$password = $characters_db[$realm_id]["pass"];
$db = $characters_db[$realm_id]["name"];

// Подключение к game-серверу ($sql) здесь не нужно: realmlist/uptime/account_access —
// VIEW в веб-базе, pomm_play.php работает через них.

$gm_show_online = $gm_online;
$gm_show_online_only_gmoff = $map_gm_show_online_only_gmoff;
$gm_show_online_only_gmvisible = $map_gm_show_online_only_gmvisible;
$gm_add_suffix = $map_gm_add_suffix;
$gm_include_online = $gm_online_count;
$show_status = $map_show_status;
$time_to_show_uptime = $map_time_to_show_uptime;
$time_to_show_maxonline = $map_time_to_show_maxonline;
$time_to_show_gmonline = $map_time_to_show_gmonline;
$status_gm_include_all = $map_status_gm_include_all;
$time = $map_time;
$show_time = $map_show_time;

// points located on these maps(do not modify it)
$maps_for_points = "0,1,530,571,609";

// Карты расширений: AzerothCore 3.3.5 — Wrath of the Lich King.
if ($realm_id == 1) {
	$img_base = "img/map_vanilla/";
} elseif ($realm_id == 2) {
	$img_base = "img/map_tbc/";
} else {
	$img_base = "img/map_wotlk/";
}
$img_base2 = "img/c_icons/";

$PLAYER_FLAGS       = CHAR_DATA_OFFSET_FLAGS;
