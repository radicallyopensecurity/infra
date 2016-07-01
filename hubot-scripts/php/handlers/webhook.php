<?php

// NOTE: JSONclient from Frederic Guillot
// see https://github.com/fguillot/JsonRPC


require "./JSONclient.php";
require_once 'vendor/autoload.php';

$GITLABSURL = 'https://gitlabs.radicallyopensecurity.com';
$GITLABSTOKEN = 'YOURSECRETTOKENHERE';
$MONGO_URL_FOR_ROCKETCHAT = "mongodb://YOURROCKETCHATMONGO:3001";
$ROBOT_ID = "966n232acuZr7keAvtr8N"; // you will need the id rocket chat uses for your robot user account
$ROCKETCHAT = "https://chat/";

$m = new MongoClient($MONGO_URL_FOR_ROCKETCHAT);

$gitclient = new \Gitlab\Client($GITLABSURL . '/api/v3/');
$gitclient->authenticate($GITLABSTOKEN, \Gitlab\Client::AUTH_URL_TOKEN); 


$project_name = $argv[1];
$channelname= $argv[1];

try {

	$projects = $gitclient->api('projects')->search($project_name);
}
catch (Exception $e) {
	print "I am sorry, I cannot find a git project'" . $project_name . "'";
	exit;
}

if (count($projects) == 0)
{
	print "I am sorry, I cannot find a git project'" . $project_name . "'";
	exit;

}
$project = new \Gitlab\Model\Project($projects[0]['id'], $gitclient);



$db = $m->meteor;

$collection = $db->rocketchat_integrations;

// create a new "incoming" integration webhook in rochet chat
$doc = array(
"_id" => $channelname, 
"channel" => "#" . $channelname,
"name" => $channelname, 
"username" => "rosbot", 
"type" => "webhook-incoming", 
"token" => md5($channelname), 
"userId" => $ROBOT_ID, 
"_createdAt" => date(DATE_ATOM), 
"_createdBy" => array( "_id" => $ROBOT_ID, "username" => "rosbot" ), 
"avatar" => null, 
"emoji" => null, 
"alias" => null, 
"script" => null, 
"scriptEnabled" => false, 
"scriptCompiled" => null, 
"scriptError" => null,
"enabled" => true 
);
	;

$collection->insert( $doc );


# and tell gitlabs about it
$webhook = $ROCKETCHAT ."hooks/" . $channelname . "/" .  md5($channelname);
$ignore=($project->setService('slack',array ( 'webhook'=> $webhook , "channel" => $channelname       ) ));

?>