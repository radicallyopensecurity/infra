<?php

require_once(__DIR__ . '/common-includes.php');
require_once(__DIR__ . '/log.php');

use GitWrapper\GitWrapper;
use GitWrapper\Event\GitLoggerListener;
use GitWrapper\Event\GitEvents;
use Psr\Log\LogLevel;

class GlobalGitWrapper
{
    static $git;

    static function init(&$log)
    {
        if (! isset(GlobalGitWrapper::$git))
        {
            GlobalGitWrapper::$git = new GitWrapper();
            $gitlogger = new GitLoggerListener($log);
            $gitlogger->setLogLevelMapping(GitEvents::GIT_PREPARE, LogLevel::DEBUG);
            $gitlogger->setLogLevelMapping(GitEvents::GIT_OUTPUT , LogLevel::DEBUG);
            $gitlogger->setLogLevelMapping(GitEvents::GIT_SUCCESS, LogLevel::DEBUG);
            $gitlogger->setLogLevelMapping(GitEvents::GIT_ERROR  , LogLevel::ERROR);
            $gitlogger->setLogLevelMapping(GitEvents::GIT_BYPASS , LogLevel::DEBUG);
            GlobalGitWrapper::$git->addLoggerListener($gitlogger);
            GlobalGitWrapper::$git->setPrivateKey(__DIR__ . '/../rosbot.rsa');
        }
    }
}

function cloneRepo($path, $repoDir)
{
    # We would like to perform init() once statically, but the log is not yet
    # available then.
    GlobalGitWrapper::init(GlobalLog::$log);

    $repoInternalSSH = 'ssh://git@' . GITLAB_SERVER . '/' . $path;
    $repo = GlobalGitWrapper::$git->clone($repoInternalSSH, $repoDir);
    # Configure this repository.
    $repo
      ->config('user.name', 'Rosbot')
      ->config('user.email', 'js@radical.sexy')
      ->config('push.default', 'simple');
    return $repo;
}

?>
