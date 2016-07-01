<?php


// minimal sanitation only.
// we're taking this roundabout way since we're lazy - easiest way to shell-escape 
// sanitizing input

// and the reason we're throwing it to a temp file first is to prevent some slowing down
// of chat while the search runs

unset($argv[0]);
unset($argv[1]);
unset($argv[2]);

$vFeedLocation = "/opt/vFeed/";

$search_string = implode(" ",$argv);
$search_string  = escapeshellarg(trim(str_replace('"','',$search_string)));
unlink("/tmp/cveout.txt");
passthru("cd $vFeedLocation;python vfeedcli.py -s " . $search_string . " > /tmp/cveout.txt");
$outp = file("/tmp/cveout.txt");

// and escape that stuff too:
foreach($outp as $line) print htmlentities($line) ;
?>