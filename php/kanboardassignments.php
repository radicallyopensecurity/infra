<?php

require_once(__DIR__ . '/kanboard.php');
require_once(__DIR__ . '/rosbot.php');
require_once(__DIR__ . '/log.php');

# Set up logging.

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\BufferHandler;
use Monolog\Formatter\LineFormatter;

$log = new Logger('kanboard-cron');
//$log->pushHandler(new StreamHandler(__DIR__ . '/../kanboard.log', Logger::DEBUG));

$formatter = new LineFormatter("> %message%\n");

// TODO: use FingersCrossedHandler or similar to show debug messages in case of failure.
//$errorlog = new RocketChatHandler('ros-errorlog', Logger::ERROR);
$errorlog = new RocketChatHandler('pen-test-ariep-start', Logger::DEBUG);
//$errorlog->setFormatter($formatter);
$log->pushHandler(new BufferHandler($errorlog));

//$pm = 'ros-projectmanagement';
$pm = 'off-test-ariep-start';


$client = GlobalKanboardClient::$client;

define('STATUS_SCOPING', 50);

$notices = array();
$offertes = $client->getAllTasks(Triad::OFF);
foreach($offertes as $task)
{
    $msg = '';
    if ($task->columnID == STATUS_SCOPING && is_null($task->assignee))
    {
        $msg .= 'Without owner but needs scoping: ' . $task->title;
        $msg .= ' (' . formatDue($task->due) . ')';
        $notices[] = $msg;
    }

    $msg = '';
    if ($task->columnID == STATUS_SCOPING && ! is_null($task->assignee))
    {
        $assignee = KanboardUser::fromID($task->assignee);
        $msg .= 'Being scoped: ' . $task->title . ' by ' .  $assignee->name;
        $msg .= ' (' . formatDue($task->due) . ')';
        $notices[] = $msg;
    }
}

$msg = implode("\n", $notices);
tell_rosbot($msg, $pm);

function formatDue($due)
{
    if (is_null($due))
    {
        $text =  'no deadline set';
    }
    else
    {
        $text = 'due ' . date('Y-m-d', $due);
    }
    return $text;
}

?>
