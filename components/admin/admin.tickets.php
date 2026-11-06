<?php
if(INCLUDED!==true)exit;
// ==================== //
$pathway_info[] = array('title'=>'GM Ticket Manager', 'link'=>'index.php?n=admin&sub=tickets');
// ==================== //
?>

<?php
$MANG = new Mangos;
$query = array();
$realm_info_new = get_realm_byid($user['cur_selected_realmd']);
 
$cc = 0;
if(!check_port_status($realm_info_new['address'], $realm_info_new['port'])===true)
{
    output_message('alert','Realm <b>'.$realm_info_new['name'].'</b> is offline <img src="images/downarrow2.gif" border="0" align="top">');
}

$action = array();

// AzerothCore: тикеты в gm_ticket (в старом MWE был character_ticket)
$action = $CHDB->select("SELECT `id`, `name`, `description`, `comment`, `lastModifiedTime` FROM `gm_ticket` WHERE `completed` = 0 ORDER BY `id` ");
$ticket = array();
$cc1 = 0;
$result1 = array();
if (is_array($action) && count($action) > 0){
foreach ($action as $result1) {
	    if($color==1) {
      $color=2;
    }
    else
      $color=1;
    $cc1++;

	$ticket[$cc1]["id"] = $cc1;
	$ticket[$cc1]["ticket_id"] = $result1['id'];
	$ticket[$cc1]["player_name"] = $result1['name'];
	$ticket[$cc1]["message"] = $result1['description'];
	$ticket[$cc1]["comment"] = $result1['comment'];
	$ticket[$cc1]["stamp"] = $result1['lastModifiedTime'];
	$ticket[$cc1]["color"] = $color;
	}
}
unset($action, $result1);
?>