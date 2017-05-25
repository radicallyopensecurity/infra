<?php

require_once(__DIR__ . '/common-includes.php');
require_once(__DIR__ . '/gitlab.php');

function addWebhook($triad)
{
    $glclient = GlobalGitlabClient::$client;
    $channelName = $triad->type . '-' . $triad->name;
    
    $project = new \Gitlab\Model\Project($triad->gitlabID, $glclient);
    $webhook = ROCKETCHAT_API_URL . 'hooks/ros-chatops/ros-chatopstoken';
    $project->setService('slack', array(
      'webhook' => $webhook,
      'channel' => '#' . $channelName
    ));
}

?>
