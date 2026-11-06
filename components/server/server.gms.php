<?php
if(INCLUDED!==true)exit;

$pathway_info[] = array('title'=>'GM list','link'=>'');

$gmlevel_w = array('Users','Moderators','Game Masters','Administrators');

$result = $DB->select("
    SELECT a.username, MAX(aa.gmlevel) AS gmlevel
    FROM ".AUTH_DB.".account AS a
    JOIN ".AUTH_DB.".account_access AS aa ON a.id=aa.id
    WHERE aa.gmlevel>0
    GROUP BY a.id, a.username
    ORDER BY gmlevel,username 
");
$gm_groups = array();
foreach($result as $r){
    $gm_groups[$r['gmlevel']][] = $r['username'];
}
$gm_groups = array_reverse($gm_groups,true);
unset($result);
?>