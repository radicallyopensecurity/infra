<?php

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/../secrets.php');

function gitlab_init()
{
    $options = array('timeout' => 120);
    $gitclient = new \Gitlab\Client('https://gitlabs.radicallyopensecurity.com/api/v3/', null, $options);
    $gitclient->authenticate(GITLAB_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);
    return $gitclient;
}

class GlobalGitlabClient
{
    static $client;
}

GlobalGitlabClient::$client = gitlab_init();

class GitlabRepo
{
    public $repoID;
    public $branch;
    protected $client;

    public function __construct($client, $repoID, $branch = 'master')
    {
        $this->client = $client;
        $this->repoID = $repoID;
        $this->branch = $branch;
    }

    public function readFile($path)
    {
        $file = $this->client->api('repositories')->getFile($this->repoID, $path, $this->branch);
        if ($file['encoding'] == 'base64')
        {
            return base64_decode($file['content']);
        }
        else
        {
            throw new Exception('Unknown file encoding');
        }
    }

    public function readFileMaybe($path)
    {
        try
        {
            $file = $this->readFile($path);
        }
        catch (Exception $e)
        {
            $msg = $e->getMessage();
            if ($msg == '404 File Not Found')
            {
                return null;
            }
            else
            {
                throw $e;
            }
        }
        return $file;
    }

    public function writeFile($path, $content, $commitmsg = null)
    {
        if (is_null($commitmsg))
        {
            $commitmsg = 'Automatic commit';
        }
        return $this->client->api('repositories')->createFile($this->repoID, $path, $content, $this->branch, $commitmsg);
    }
}

?>
