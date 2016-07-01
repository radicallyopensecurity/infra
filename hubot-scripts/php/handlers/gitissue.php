<?php


// NOTE: JSONclient from Frederic Guillot
// see https://github.com/fguillot/JsonRPC
require "JSONclient.php";
require_once 'vendor/autoload.php';

$room_id = $argv[1];

$GITLABSURL = 'https://gitlabs.radicallyopensecurity.com';
$GITLABSTOKEN = 'YOURSECRETTOKENHERE';


// note that this pulls the right name right of the chat mongo database, not using any api here
$MONGO_URL_FOR_ROCKETCHAT = "mongodb://YOURROCKETCHATMONGO:3001";



$issue_text= implode(' ',array_slice($argv ,6   ));

$m = new MongoClient($MONGO_URL_FOR_ROCKETCHAT);
$gitclient = new \Gitlab\Client($GITLABSURL . '/api/v3/');
$gitclient->authenticate($GITLABSTOKEN, \Gitlab\Client::AUTH_URL_TOKEN); 

// select a database
$db = $m->meteor;

// select a collection 
$collection = $db->rocketchat_room;


// find everything in the collection
$cursor = $collection->find( array('_id' => $room_id) );
$room = $cursor->getNext();
$room_name = $room['name'];
print "[+] Hello room " . $room_name;

// ok let's search for a git repo with the same name
try {

	$projects = $gitclient->api('projects')->search($room_name);
}
catch (Exception $e) {
	print "I am sorry, I cannot find a git project' " . $room_name . "'";
	exit;
}

if (count($projects) == 0)
{
	print "I am sorry, I cannot find a git project' " . $room_name . "'";
	exit;
}

$project_id=0;
foreach($projects as $p)
{
	if ($p['name'] == $room_name)
	{
		$project_id = $p['id'];
	}
}
if ($project_id == 0)
{
	print "I am sorry, I cannot find a git project' " . $room_name . "'";
	exit;
}


// found it, create an issue
$project = new \Gitlab\Model\Project($project_id, $gitclient);

$issue = $project->createIssue($issue_text, array(
  'description' => '(auto-created by rosbot)'
));
print " issue created";

?>