<?php
if(INCLUDED!==true)exit;

$pathway_info[] = array('title'=>$lang['playermap'],'link'=>'');

// Карта онлайн-игроков (PomM) рендерится шаблоном через iframe на
// components/pomm/playermap.php — PomM самостоятелен и читает конфиг сайта сам.
$realm_param = (isset($_GET['realm']) && (int)$_GET['realm'] > 0) ? (int)$_GET['realm'] : (int)$user['cur_selected_realmd'];
