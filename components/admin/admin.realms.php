<?php
if(INCLUDED!==true)exit;
// ==================== //
$pathway_info[] = array('title'=>$lang['realms_manage'],'link'=>'index.php?n=admin&sub=realms');
// ==================== //
// AzerothCore: игровая realmlist (auth-база) — только чтение (VIEW).
// Здесь редактируются только веб-настройки реальма: доступ к базам
// world/characters и данные Remote Access (website_realm_settings).
$realm_type_def = array(
    0 => 'Normal',
    1 => 'PVP',
    4 => 'Normal',
    6 => 'RP',
    8 => 'RPPVP',
    16 => 'FFA_PVP'
);
$realm_timezone_def = array(
     0 => 'Unknown',
     1 => 'Development',
     2 => 'United States',
     3 => 'Oceanic',
     4 => 'Latin America',
     5 => 'Tournament',
     6 => 'Korea',
     7 => 'Tournament',
     8 => 'English',
     9 => 'German',
     10 => 'French',
     11 => 'Spanish',
     12 => 'Russian',
     13 => 'Tournament',
     14 => 'Taiwan',
     15 => 'Tournament',
     16 => 'China',
     17 => 'CN1',
     18 => 'CN2',
     19 => 'CN3',
     20 => 'CN4',
     21 => 'CN5',
     22 => 'CN6',
     23 => 'CN7',
     24 => 'CN8',
     25 => 'Tournament',
     26 => 'Test Server',
     27 => 'Tournament',
     28 => 'QA Server',
     29 => 'CN9',
);

if(!$_GET['action']){

    $items = $DB->select("
        SELECT r.id, r.name, r.address, r.port, r.icon, r.timezone,
               s.id_realm, s.dbhost, s.dbport, s.dbuser, s.dbname, s.chardbname, s.template,
               s.ra_address, s.ra_port, s.ra_user
        FROM realmlist AS r
        LEFT JOIN website_realm_settings AS s ON r.id=s.id_realm
        ORDER BY r.name");

}elseif($_GET['action']=='edit' && $_GET['id']){
    $pathway_info[] = array('title'=>$lang['editing'],'link'=>'');
    $item = $DB->selectRow("
        SELECT r.id, r.name, r.address, r.port, r.icon, r.timezone,
               s.id_realm, s.dbhost, s.dbport, s.dbuser, s.dbpass, s.dbname, s.chardbname, s.template,
               s.ra_address, s.ra_port, s.ra_user, s.ra_pass
        FROM realmlist AS r
        LEFT JOIN website_realm_settings AS s ON r.id=s.id_realm
        WHERE r.id=?d",$_GET['id']);
}elseif($_GET['action']=='update' && $_GET['id']){
    // только веб-колонки; игровая realmlist не меняется
    $web_fields = array('dbhost','dbport','dbuser','dbpass','dbname','chardbname','template',
                        'ra_address','ra_port','ra_user','ra_pass');
    $set = array();
    foreach($web_fields as $wf){
        if(isset($_POST[$wf])) $set[$wf] = $_POST[$wf];
    }
    if($set){
        $DB->query("REPLACE INTO website_realm_settings SET ?a, id_realm=?d",$set,$_GET['id']);
    }
    redirect('index.php?n=admin&sub=realms',1);
}

?>
