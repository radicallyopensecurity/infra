<?php

require_once(__DIR__ . '/common-includes.php');
require_once(__DIR__ . '/log.php');

class GlobalRocketChatClient
{
    static $client;
}

class RocketChatClient
{
    private $client;
    public $MORE_MESSAGES_THAN_YOULL_HAVE = 1000000;

    public function __construct()
    {
        global $ROCKETCHAT_TOKEN;

        $this->client = new RocketChat\Client(ROCKETCHAT_API_URL);

        if (is_null($ROCKETCHAT_TOKEN))
        {
            $token = $this->client->api('user')->login(
                ROCKETCHAT_BOTUSER,
                ROCKETCHAT_PASSWORD
            );
            if ($token)
            {
                $this->client->setToken($token);
            }
            else
            {
                print_r($this->client->api('user')->getMessage());
                throw new Exception('Rocketchat authentication failed.');
            }
        }
        else
        {
            # Use fixed stored token.
            $this->client->setToken($ROCKETCHAT_TOKEN);
        }
    }

    function createChatroom($name, $members)
    {
        $channel_result = $this->client->api('group')->create($name, $members);
        if ($channel_result)
        {
            $id = $channel_result->_id;
            return $id;
        }
        else
        {
            $errorMsg = $this->client->api('group')->getMessage();
            throw new Exception('Creating rocketchat group failed: ' . $errorMsg);
        }
    }

    function createUser($username, $password, $name, $email)
    {
        $params = array(
            'verified' => true
        );
        $channel_result = $this->client->api('user')->create($username, $password, $name, $email, $params);
        if ($channel_result)
        {
            $id = $channel_result->_id;
            return $id;
        }
        else
        {
            $errorMsg = $this->client->api('user')->getMessage();
            throw new Exception('Creating rocketchat user failed: ' . $errorMsg);
        }
    }
    
    function findChatroom($name)
    {
        $result = $this->client->api('group')->findByName($name);
        if ($result)
        {
            $id = $result->_id;
            return $id;
        }
        else
        {
            $errorMsg = $this->client->api('group')->getMessage();
            throw new Exception('Looking up rocketchat group failed: ' . print_r($result, true));
        }
    }
    
    function sendChatMessage($roomID, $message)
    {
        $message_result = $this->client->api('group')->sendMessage($roomID, $message);
        if ($message_result)
        {
            return true;
        }
        else
        {
            $errorMsg = $this->client->api('group')->getMessage();
            throw new Exception('Sending rocketchat message failed: ' . $errorMsg);
        }
    }

    function addMember($roomID, $rosUser)
    {
        $result = $this->client->api('group')->addMember($roomID, $rosUser->rocketchatID);
        if ($result)
        {
            return true;
        }
        else
        {
            $errorMsg = $this->client->api('group')->getMessage();
            throw new Exception('Adding rocketchat group member failed: ' . $errorMsg);
        }
    }

    function removeMember($roomID, $user)
    {
        $result = $this->client->api('group')->removeMember($roomID, $rosUser->rocketchatID);
        if ($result)
        {
            return true;
        }
        else
        {
            $errorMsg = $this->client->api('group')->getMessage();
            throw new Exception('Removing rocketchat group member failed: ' . $errorMsg);
        }
    }

    function userIDFromName($rcUsername)
    {
        $rcUser = $this->client->api('user')->lookup($rcUsername);

        return $rcUser->_id;
    }

    function userNamefromID($rcUserID)
    {
        $rcUser = $this->client->api('user')->fromID($rcUserID);

        return $rcUser->username;
    }

    function getHistory($roomID)
    {
        $result = $this->client->api('group')->history($roomID, null, null, $this->MORE_MESSAGES_THAN_YOULL_HAVE);
        if ($result->success)
        {
            return $result->messages;
        }
        else
        {
            throw new Exception('Getting history for room ' . $roomID . ' failed: ' . $this->client->api('group')->getMessage());
        }
    }
}

GlobalRocketChatClient::$client = new RocketChatClient();

?>
