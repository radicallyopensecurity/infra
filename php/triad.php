<?php

require_once(dirname(__FILE__) . '/../secrets.php');

class Triad
{
    public $ID;
    public $type;
    public $name;
    public $rocketchatID;
    public $gitlabID;
    public $kanboardProjectID;
    public $kanboardTaskID;
    const OFF = 'off';
    const PEN = 'pen';

    public function __construct($ID, $type, $name, $rocketchatID, $gitlabID, $kanboardProjectID, $kanboardTaskID)
    {
        $this->ID = $ID;
        $this->type = $type;
        $this->name = $name;
        $this->rocketchatID = $rocketchatID;
        $this->gitlabID = $gitlabID;
        $this->kanboardProjectID = $kanboardProjectID;
        $this->kanboardTaskID = $kanboardTaskID;
    }
    
    static function fromRoomID($rocketchatID)
    {
        $sql = "select * from `triad` where `rocketchat_id` = :rocketchat_id";
        $params = array(
            ':rocketchat_id' => $rocketchatID
        );
        return Triad::fromSQL($sql, $params);
    }

    static function fromAlias($alias)
    {
        $project_parts = explode('-', $alias, 2);
        return Triad::fromTypeName($project_parts[0], $project_parts[1]);
    }

    static function fromTypeName($type, $name)
    {
        $sql = "select * from `triad` where `prefix` = :prefix and `name` = :name";
        $params = array(
            ':prefix' => $type,
            ':name'   => $name
        );
        return Triad::fromSQL($sql, $params);
    }

    static function newFromParts($alias, $room, $repo, $kb_proj, $kb_task)
    {
        $aliasparts = explode('-', strtolower($alias), 2);
        $prefix = $aliasparts[0];
        $projectName = $aliasparts[1];

        $db_planning = new PDO('mysql:host=127.0.0.1;dbname=planning;charset=utf8mb4', 'nginx', MYSQL_PASSWORD);
        $sql = "insert into `triad` (`prefix`, `name`, `rocketchat_id`, `gitlab_id`, `kanboard_proj_id`, `kanboard_task_id`) values (:prefix, :name, :rocketchat_id, :gitlab_id, :kanboard_proj_id, :kanboard_task_id);";
        $stmt2 = $db_planning->prepare($sql);
        $params = array(
            ':prefix'           => $prefix,
            ':name'             => $projectName,
            ':rocketchat_id'    => $room,
            ':gitlab_id'        => $repo,
            ':kanboard_proj_id' => $kb_proj,
            ':kanboard_task_id' => $kb_task
        );
        $stmt2->execute($params);

        # This does an extra roundtrip to the database to get the data we just
        # inserted. Although not logically necessary, this reuses some code
        # and makes sure that we notice if the database insertion failed.
        return Triad::fromAlias($alias);
    }

    private static function fromSQL($sql, $params)
    {
        $db_planning = new PDO('mysql:host=localhost;dbname=planning;charset=utf8mb4', 'nginx', MYSQL_PASSWORD);
        $stmt = $db_planning->prepare($sql);
        $stmt->execute($params);
        $triad = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($triad === false)
        {
            throw new Exception('Triad not found in database; params: ' . print_r($params, true));
        }
        return new Triad($triad['id'], $triad['prefix'], $triad['name'], $triad['rocketchat_id'], $triad['gitlab_id'], $triad['kanboard_proj_id'], $triad['kanboard_task_id']);
    }

    public function alias()
    {
        $alias = $this->type . '-' . $this->name;
        return $alias;
    }

    public function chatURL()
    {
        $alias = $this->alias();
        $chatURL = 'https://chat.radicallyopensecurity.com/group/' . $alias;
        return $chatURL;
    }

    public function gitlabURL()
    {
        $alias = $this->alias();
        $gitlabURL = 'https://gitlabs.radicallyopensecurity.com/ros/' . $alias;
        return $gitlabURL;
    }
    public function info()
    {
        $output = array();
        $alias = $this->alias();
        $output[] = 'Project ' . $alias;
        $output[] = 'chat: ' . $this->chatURL();
        $output[] = 'repo: ' . $this->gitlabURL();
        return $output;
    }
}

?>
