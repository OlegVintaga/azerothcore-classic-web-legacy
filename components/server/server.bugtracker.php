<?php
if(INCLUDED!==true)exit;

$pathway_info[] = array('title'=>'Bugtracker','link'=>'');

$items_per_page = 20;
$bugs_forum_id = (int)$MW->getConfig->generic_values->forum->bugs_forum_id;
if($bugs_forum_id == 0)output_message('alert','Please define forum id for bugtracker (in config/config.xml)');

// MySQL 8 (ONLY_FULL_GROUP_BY): вместо GROUP BY берём первый пост топика через MIN(post_id)
$alltopics = $DB->select("
    SELECT f_topics.*,f_posts.*
    FROM f_topics
    JOIN f_posts ON f_posts.post_id = (SELECT MIN(p2.post_id) FROM f_posts p2 WHERE p2.topic_id = f_topics.topic_id)
    WHERE f_topics.forum_id=?d AND f_topics.closed!=1 AND f_topics.sticky!=1
    ORDER BY f_topics.topic_posted DESC,f_posts.posted
    LIMIT ?d,?d",$bugs_forum_id,0,$items_per_page);

?>
