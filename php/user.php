<?php

require_once(__DIR__ . '/common-includes.php');
require_once(__DIR__ . '/log.php');
require_once(__DIR__ . '/gitlab.php');
require_once(__DIR__ . '/rocketchat.php');
require_once(__DIR__ . '/db.php');

class GlobalRosUserCache
{
    static $cacheByName = array();
    static $cacheByID   = array();
    static $botUser     = null;
}

class RosUser
{
    public $userID;
    public $username;
    public $email;
    public $rocketchatID;
    public $rocketchatName;
    public $gitlabID;
    public $kanboardID;
    public $authDetails;

    public function __construct($userID, $username, $email, $rocketchatID, $rocketchatName, $gitlabID, $kanboardID, $authDetails)
    {
        $this->userID = $userID;
        $this->username = $username;
        $this->email = $email;
        $this->rocketchatID = $rocketchatID;
        $this->rocketchatName = $rocketchatName;
        $this->gitlabID = $gitlabID;
        $this->kanboardID = $kanboardID;
        $this->authDetails = $authDetails;
    }

    static public function createNew($username, $password, $fullName, $email, $expire, $sms, $createRocketchatUser, $createGitlabUser, $createKanboardUser, $roles, $authDetails)
    {
        $log = GlobalLog::$log;

        $rocketchatID = null;
        if ($createRocketchatUser)
        {
            # Create corresponding user in rocketchat.
            try
            {
                $rocketchatID = GlobalRocketchatClient::$client->createUser($username, $password, $fullName, $email);
                $log->info('Created rocketchat user ' . $username);
            }
            catch (Exception $e)
            {
                $log->warning('Creating rocketchat user failed: ```' . $e->getMessage() . '```');
            }
        }

        $gitlabID = null;
        if ($createGitlabUser)
        {
            # Create corresponding user in gitlab.
            try
            {
                $params = array(
                    'username' => $username,
                    'name' => $fullName,
                    'confirm' => false
                );
                $gitlabUser = GlobalGitlabClient::$client->api('users')->create($email, $password, $params);
                $gitlabID = $gitlabUser['id'];
                $log->info('Created gitlab user: https://gitlabs.radicallyopensecurity.com/admin/users/' . $username);
            }
            catch (Exception $e)
            {
                $log->warning('Creating gitlab user failed: ```' . $e->getMessage() . '```');
            }
        }

        $kanboardID = null;
        if ($createKanboardUser)
        {
            # Create corresponding user in kanboard.
            try
            {
                $kanboardUser = KanboardUser::createNew($username, $password, $fullName, $email);
                $kanboardID = $kanboardUser->ID;
                $log->info('Created kanboard user: https://kanboard.radicallyopensecurity.com/user/show/' . $kanboardID);
            }
            catch (Exception $e)
            {
                $log->warning('Creating kanboard user failed: ```' . $e->getMessage() . '```');
            }
        }

        $db = new DB('rosbotuser');
        $params = array(
            ':user_name' => $username,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':realname' => $fullName,
            ':email' => $email,
            ':expire' => $expire,
            ':sms' => ($sms == '') ? null : $sms,
            ':rocketchat_id' => $rocketchatID,
            ':gitlab_id' => $gitlabID,
            ':kanboard_id' => $kanboardID
        );
        $db->query('insert into user (`user_name`, `password`, `realname`, `email`, `expire`, `sms`, `rocketchat_id`, `gitlab_id`, `kanboard_id`) values (:user_name, :password, :realname, :email, :expire, :sms, :rocketchat_id, :gitlab_id, :kanboard_id);', $params);
        # The PDO lastInsertId function gives zero here, not sure why.
        // $userID = $db->lastInsertId();
        $userID = $db->querySingle('select last_insert_id() as id;')['id'];
        $log->info('Inserted user info into database under user_id: ' . $userID);

        foreach ($roles as $role)
        {
            $log->debug('Adding role ' . $role);
            $db->query('insert into user_role (`user_id`, `role_id`) select :user_id, role_id from role where name = :role_name;', array(
                ':user_id' => $userID,
                ':role_name' => $role
            ));
        }

        foreach ($authDetails as $detail)
        {
            $log->debug('Inserting auth detail ' . $detail);
            $db->query('insert into auth_detail (`user_id`, `appname`) values (:user_id, :appname);', array(
                ':user_id' => $userID,
                ':appname' => $detail
            ));
        }

        $log->info('Done.');

        return RosUser::fromUserID($userID);
    }

    static public function fromSQL($sql, $params)
    {
        $log = GlobalLog::$log;

        # Look up user info from database.
        $db_rosbotuser = new DB('rosbotuser');
        $user = $db_rosbotuser->querySingle($sql, $params);
        if ($user === false)
        {
            $log->error('User not found in database.' . PHP_EOL
              . 'sql: ' . $sql . PHP_EOL
              . 'params: ' . print_r($params, true)
            );
            throw new Exception('user not found');
        }

        $sql = 'select * from auth_detail where user_id = :user_id;';
        $params = array(
            'user_id' => $user['user_id']
        );
        $results = $db_rosbotuser->queryList($sql, $params);
        if ($results === false)
        {
            $log->error('Fetching auth details failed: ' . $stmt->errorInfo()[2]);
            throw new Exception('user auth details not found');
        }
        $authDetails = array_map(function($row) { return $row['appname']; }, $results);

        $rcclient = GlobalRocketchatClient::$client;
        $chatname = $rcclient->userNamefromID($user['rocketchat_id']);

        return new RosUser(
            $user['user_id'],
            $user['user_name'],
            $user['email'],
            $user['rocketchat_id'],
            $chatname,
            $user['gitlab_id'],
            $user['kanboard_id'],
            $authDetails
        );
    }

    static public function fromRocketchatName($rocketchatName)
    {
        # Get the user from the cache, if available.
        if (isset(GlobalRosUserCache::$cacheByName[$rocketchatName]))
        {
            return GlobalRosUserCache::$cacheByName[$rocketchatName];
        }

        $rocketchatID = GlobalRocketchatClient::$client->userIDFromName($rocketchatName);
        $user = RosUser::fromRocketchatID($rocketchatID);

        GlobalRosUserCache::$cacheByName[$rocketchatName] = $user;
        return $user;
    }

    static public function fromUserID($userID)
    {
        $sql = 'select * from `user` where `user_id` = :user_id;';
        $params = array(
            ':user_id' => $userID
        );
        $user = RosUser::fromSQL($sql, $params);
        return $user;
    }

    static public function fromRocketchatID($rocketchatID)
    {
        # Get the user from the cache, if available.
        if (isset(GlobalRosUserCache::$cacheByID[$rocketchatID]))
        {
            return GlobalRosUserCache::$cacheByID[$rocketchatID];
        }

        $sql = 'select * from `user` where `rocketchat_id` = :rocketchat_id;';
        $params = array(
            ':rocketchat_id' => $rocketchatID
        );
        $user = RosUser::fromSQL($sql, $params);

        GlobalRosUserCache::$cacheByID[$rocketchatID] = $user;
        return $user;
    }

    static public function havingRole($roleName)
    {
        $db = new DB('rosbotuser');
        $userIDs = $db->queryList('select user.user_id from user natural join user_role natural join role where role.name = :roleName',
            array(':roleName' => $roleName)
        );
        $users = array_map(function($row) { return RosUser::fromUserID($row['user_id']); }, $userIDs);
        return $users;
    }

    static public function botUser()
    {
        if (! is_null(GlobalRosUserCache::$botUser))
        {
            return GlobalRosUserCache::$botUser;
        }

        $botUser = RosUser::fromSQL('select * from user where user_name = :user_name;', array(
            ':user_name' => BOTUSER_USERNAME
        ));
        GlobalRosUserCache::$botUser = $botUser;
        return $botUser;
    }
}

class RosCustomer
{
    public $customerID;
    public $customerAlias;
    public $customerName;

    public function __construct($customerID, $customerAlias, $customerName)
    {
        $this->customerID = $customerID;
        $this->customerAlias = $customerAlias;
        $this->customerName = $customerName;
    }

    static public function fromAlias($customerAlias)
    {
        $db = new DB('rosbotuser');
        $customer = $db->querySingle('select * from customer where customer_alias = :customer_alias;', array(
            ':customer_alias' => $customerAlias
        ));
        if ($customer === false)
        {
            throw new Exception('customer with alias ' . $customerAlias . ' not found');
        }
        return new RosCustomer($customer['customer_id'], $customer['customer_alias'], $customer['customer_name']);
    }

    public function newProjectMembers()
    {
        $db = new DB('rosbotuser');
        $members = $db->queryList('select * from user natural join customer_user natural join customer where customer_id = :customer_id and autoadd_newproject = 1;', array(
            ':customer_id' => $this->customerID
        ));
        $members = array_map(function($row) { return RosUser::fromRocketchatID($row['rocketchat_id']); }, $members);
        return $members;
    }
}

?>
