<?php
if(INCLUDED!==true)exit;
// ==================== //
$content = '';
if(isset($_GET['text']) && $_GET['text']=='license'){
    $pathway_info[] = array('title'=>'License','link'=>'');
    $content = lang_resource('gnu_gpl.html');
}
?>
