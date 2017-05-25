<?php

require_once(dirname(__FILE__) . '/common-includes.php');
require_once(dirname(__FILE__) . '/JSONclient.php');
require_once(dirname(__FILE__) . '/log.php');
require_once(dirname(__FILE__) . '/triad.php');

define('KB_ACTIVE'  , 1);
define('KB_INACTIVE', 0);

class GlobalKanboardClient
{
    static $client;
}

GlobalKanboardClient::$client = new KanboardClient('jsonrpc', KB_APIKEY);

class KanboardClient
{
    public $client;
    private $log;

    public function __construct($username, $apikey)
    {
        $this->client = new JsonRPC\Client('http://10.1.1.4/jsonrpc.php');
        $this->client->authentication($username, $apikey);
        $this->log = &GlobalLog::$log;
    }

    static public function userClient($username)
    {
        if (! array_key_exists($username, Secrets::$kanboardUserApiKeys))
        {
            throw new Exception('No kanboard apikey known for user ' . $username);
        }
        $apikey = Secrets::$kanboardUserApiKeys[$username];
        return new KanboardClient($username, $apikey);
    }

    public function addTask($board, $name, $contents)
    {
        $proj_id = $board;
        
        $params = array(
          "title"       => $name,
          "project_id"  => $proj_id,
          "creator_id"  => KB_BOTUSERID,
          "description" => $contents
        );
        
        try
        {
            $task_id = kanboardRetry(array($this->client, 'createTask'), $params);
        }
        catch (Exception $e)
        {
            $this->log->error('[-] problem with creating kanboard task: ' . $e->getMessage());
            throw new Exception('kanboard task creation failed');
        }

        return(array(
          'task_id' => $task_id,
          'proj_id' => $proj_id
        ));
    }

    public function projectTask($type, $projectName, $sshURL, $gitlabURL, $chatURL)
    {
        $body = "## URLs

git clone $sshURL

$gitlabURL

$chatURL


";

        switch ($type)
        {
            case Triad::OFF:
                $body .= file_get_contents(GENERAL_INFO_DIR . '/Onboarding_manuals/Kanban/kanban-offerte-checklist.md');
                break;
            case Triad::PEN:
                $body .= file_get_contents(GENERAL_INFO_DIR . '/Onboarding_manuals/Kanban/kanban-pentesting-checklists.md');
                break;
            default:
        }

        $proj = KanboardProject::fromType($type);
        $projID = $proj->id;
        
        $taskInfo = $this->addTask($projID, $projectName, $body);
        $taskID = $taskInfo['task_id'];

        $kbURL = "https://kanboard.radicallyopensecurity.com/project/{$projID}/task/{$taskID}";
        $this->log->info('[+] View here: ' . $kbURL);

        return $taskInfo;
    }

    public function addComment($taskID, $content, $user = null)
    {
        $content = str_replace("\n", "\n\n", $content);
        $userID = is_null($user) ? KB_BOTUSERID : $user;
        $params = array(
            "task_id" => $taskID,
            "user_id" => $userID,
            "content" => $content
         );
        try
        {
            $task = kanboardRetry(array($this->client, 'createComment'), $params);
        }
        catch (Exception $e)
        {
            $this->log->error('Adding kanboard comment failed: ' . $e->getMessage());
        }
    }

    public function getAllTasks($type)
    {
        $proj = KanboardProject::fromType($type);
        $tasks = array();
        $params = array(
            'project_id' => $proj->id,
            'status_id'  => KB_ACTIVE
        );
        $jsonTasks = kanboardRetry(array($this->client, 'getAllTasks'), $params);
        foreach ($jsonTasks as $task)
        {
            $tasks[] = KanboardTask::fromJSON($task);
        }
        return $tasks;
    }

    public function getSubtasks()
    {
        $dashboard = kanboardRetry(array($this->client, 'getMyDashBoard'), array());
        return $dashboard['subtasks'];
    }
}

class KanboardProject
{
    public $id;
    public $name;
    public $columns;

    public function __construct($id, $name, $columns)
    {
        $this->id = $id;
        $this->name = $name;
        $this->columns = $columns;
    }

    static public function fromType($type)
    {
        $client = GlobalKanboardClient::$client;
        switch ($type)
        {
            case Triad::OFF:
                $proj = kanboardRetry(array($client->client, 'getProjectByName'), 'Offerte');
                break;
            case Triad::PEN:
                $proj = kanboardRetry(array($client->client, 'getProjectByName'), 'Pentesting');
                break;
            case 'test':
                $proj = kanboardRetry(array($client->client, 'getProjectByName'), 'Testing-pentesting');
                break;
            default:
                throw new Exception('Don\'t know the kanban board corresponding to unknown project type ' . $type);
        }
        return KanboardProject::fromJSON($proj);
    }

    static public function fromID($ID)
    {
        $client = GlobalKanboardClient::$client;
        $proj = kanboardRetry(array($client->client, 'getProjectByID'), $ID);
        return KanboardProject::fromJSON($proj);
    }

    static private function fromJSON($project)
    {
        $columns = KanboardProject::getColumns($project['id']);
        return new KanboardProject(
          $project['id'],
          $project['name'],
          $columns
        );
    }

    static private function getColumns($projectID)
    {
        $client = GlobalKanboardClient::$client;
        $columns = kanboardRetry(array($client->client, 'getColumns'), $projectID);
        return $columns;
    }

    public function lookupColumn($columnName)
    {
        foreach($this->columns as $column)
        {
            if (strtolower($column['title']) == strtolower($columnName))
            {
                return $column;
            }
        }
        throw new Exception('Column ' . $columnName . ' not found in kanboard.');
    }

    public function columnByID($columnID)
    {
        foreach($this->columns as $column)
        {
            if ($column['id'] == $columnID)
            {
                return $column;
            }
        }
        throw new Exception('Column ' . $columnName . ' not found in kanboard.');
    }
}

class KanboardTask
{
    public $taskID;
    public $projID;
    public $title;
    public $assignee;
    public $columnID;
    public $due;
    public $description;
    public $statusLine;

    public function __construct($taskID, $projID, $title, $columnID, $assignee, $due, $description, $statusLine)
    {
        $this->taskID = $taskID;
        $this->projID = $projID;
        $this->title = $title;
        $this->columnID = $columnID;
        $this->assignee = $assignee;
        $this->due = $due;
        $this->description = $description;
        $this->statusLine = $statusLine;
    }

    static public function fromJSON($task)
    {
        return new KanboardTask(
          $task['id'],
          $task['project_id'],
          $task['title'],
          $task['column_id'],
          ($task['owner_id'] == 0) ? null : $task['owner_id'],
          ($task['date_due'] == 0) ? null : $task['date_due'],
          $task['description'],
          KanboardTask::extractStatusLine($task['description'])
        );
    }

    static public function fromID($taskID)
    {
        $params = array('task_id' => $taskID);
        $task = kanboardRetry(array(GlobalKanboardClient::$client->client, 'getTask'), $params);
        return KanboardTask::fromJSON($task);
    }

    public function move($client, $newColumnName)
    {
        $project = KanboardProject::fromID($this->projID);
        $newColumn = $project->lookupColumn($newColumnName);
        $params = array(
            'project_id'  => $this->projID,
            'task_id'     => $this->taskID,
            'column_id'   => $newColumn['id'],
            'position'    => 1,
            'swimlane_id' => 0
        );
        $result = kanboardRetry(array($client->client, 'moveTaskPosition'), $params);
        return $result;
    }

    public function assign($client, $kanboardUser)
    {
        if (is_null($kanboardUser))
        {
            $userID = 0;
        }
        else
        {
            $userID = $kanboardUser->ID;
        }
        $params = array(
            'id' => $this->taskID,
            'owner_id' => $userID
         );
        $result = kanboardRetry(array($client->client, 'updateTask'), $params);
        if (! $result)
        {
            throw new Exception('Assigning kanboard task failed.');
        }
    }

    # $date is a php timestamp (seconds since unix epoch).
    public function setDeadline($client, $date)
    {
        $params = array(
            'id'       => $this->taskID,
            'date_due' => date(DATE_ATOM, $date)
         );
        $result = kanboardRetry(array($client->client, 'updateTask'), $params);
        if (! $result)
        {
            throw new Exception('Changing deadline of kanboard task failed.');
        }
    }

    public function addSubtask($client, $text, $assignee = null, $once = false)
    {
        $assignee = is_null($assignee) ? 0 : $assignee;
        if ($once)
        {
            if (! is_null($this->lookupSubtask($text)))
            {
                # Subtask with this name already exists; don't add it again.
                return false;
            }
        }
        $params = array(
            'task_id'  => $this->taskID,
            'title'    => $text,
            'user_id'  => $assignee
         );
        $result = kanboardRetry(array($client->client, 'createSubtask'), $params);
        if (! $result)
        {
            throw new Exception('Adding subtask to kanboard task failed.');
        }
    }

    public function getAllSubtasks()
    {
        $params = array(
            'task_id'  => $this->taskID
         );
        return kanboardRetry(array(GlobalKanboardClient::$client->client, 'getAllSubtasks'), $params);
    }

    public function lookupSubtask($text)
    {
        $subtasks = $this->getAllSubtasks();
        foreach ($subtasks as $subtask)
        {
            if ($subtask['title'] == $text)
            {
                return $subtask;
            }
        }
        return null;
    }

    public function setStatusLine($client, $statusLine)
    {
        $description = KanboardTask::injectStatusLine($statusLine, $this->description);
        $params = array(
            'id'          => $this->taskID,
            'description' => $description
         );
        $result = kanboardRetry(array($client->client, 'updateTask'), $params);
        if (! $result)
        {
            throw new Exception('Setting kanboard task status line failed.');
        }
    }

    static function extractStatusLine($description)
    {
        $matches = array();
        $result = preg_match('/^Status: (.*)$/m', $description, $matches);
        if ($result === 1)
        {
            return $matches[1];
        }
        else
        {
            return null;
        }
    }

    static function injectStatusLine($statusLine, $description)
    {
        $log = &GlobalLog::$log;
        $count = 0;
        $result = preg_replace('/^Status: (.*)$/m', 'Status: ' . $statusLine, $description, 1, $count);
        if (is_null($result))
        {
            # Something went wrong in the preg_replace.
            # Log this, and return the original description.
            $log->warning('KanboardTask::injectStatusLine: preg_replace failed');
            return $description;
        }

        if ($count == 1)
        {
            # An existing status line was succesfully replaced.
            # Return the resulting description.
            return $result;
        }
        else
        {
            # No status line was found to replace.
            # Insert a new one at the top of the description.
            $result = '## Status' . PHP_EOL .
              'Status: ' . $statusLine . PHP_EOL . PHP_EOL .
              $description;
            return $result;
        }
    }

    public function getTags()
    {
        $params = array(
            'task_id'  => $this->taskID
         );
        return kanboardRetry(array(GlobalKanboardClient::$client->client, 'getTaskTags'), $params);
    }

    public function setTags($tags)
    {
        $params = array(
            'project_id' => $this->projID,
            'task_id'    => $this->taskID,
            'tags'       => $tags
         );
        kanboardRetry(array(GlobalKanboardClient::$client->client, 'setTaskTags'), $params);
    }

    # Cautionary note about case sensitivity:
    # kanboard is being smart with us: if you try to add a tag to a task, and
    # any other task in the same project differs from it only by case, kanboard
    # will reuse that existing tag! So basically we must treat tags as case
    # insensitive.
    public function addTag($tag)
    {
        $tags = $this->getTags();
        if (in_array($tag, $tags))
        {
            return;
        }
        $tags[] = $tag;
        $this->setTags($tags);
    }

    public function removeTag($tag)
    {
        $tags = $this->getTags();

        $needle = strtolower($tag);
        $haystack = array_map('strtolower', $tags);

        $pos = array_search($needle, $haystack);
        if ($pos === false)
        {
            return;
        }
        $tags = array_splice($tags, $pos, 1);
        $this->setTags($tags);
    }

    public function raw()
    {
        $params = array(
            'task_id'  => $this->taskID
         );
        return kanboardRetry(array(GlobalKanboardClient::$client->client, 'getTask'), $params);
    }
}

class KanboardUser
{
    public $ID;
    public $name;

    public function __construct($ID, $name)
    {
        $this->ID = $ID;
        $this->name = $name;
    }

    static public function fromJSON($user)
    {
        return new KanboardUser($user['id'], $user['username']);
    }

    static public function fromID($ID)
    {
        $user = kanboardRetry(array(GlobalKanboardClient::$client->client, 'getUser'), $ID);
        return KanboardUser::fromJSON($user);
    }

    static public function fromName($username)
    {
        $params = array('username' => $username);
        $user = kanboardRetry(array(GlobalKanboardClient::$client->client, 'getUserByName'), $params);
        return KanboardUser::fromJSON($user);
    }

    static public function createNew($username, $password, $name, $email)
    {
        $params = array(
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'name' => $name
        );
        $userID = kanboardRetry(array(GlobalKanboardClient::$client->client, 'createUser'), $params);
        return KanboardUser::fromID($userID);
    }

    public function addToProject($project)
    {
        $params = array(
            'project_id' => $project->id,
            'user_id'    => $this->ID,
            'role'       => 'project-member'
        );
        kanboardRetry(array(GlobalKanboardClient::$client->client, 'addProjectUser'), $params);
    }
}

function kanboardRetry($function, $args)
{
    $log = &GlobalLog::$log;
    $tries = 3;
    while ($tries > 0)
    {
        try
        {
            $result = call_user_func($function, $args);
        }
        catch (Exception $e)
        {
            if ($e->getMessage() == 'Unable to establish a connection')
            {
                $log->warning('Kanboard connection problem. Retrying...');
                $tries--;
                continue;
            }
            else
            {
                throw $e;
            }
        }
        return $result;
    }
    throw new Exception('Could not connect to kanboard api after 3 tries, aborting.');
}

?>
