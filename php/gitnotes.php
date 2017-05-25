<?php

require_once(dirname(__FILE__) . '/../secrets.php');
require_once(dirname(__FILE__) . '/gitlab.php');
require_once(dirname(__FILE__) . '/kanboard.php');
require_once(dirname(__FILE__) . '/rosbot.php');

function handle_gitnote($repoID, $kb, $roomID, $date, $subject, $body, $attachments)
{
    $report = "";
    $info = array();

    # Add email and attachments to notes directory in gitlab repo.
    if ($repoID > 0)
    {
        $glclient = GlobalGitlabClient::$client;
        $repo = new GitlabRepo($glclient, $repoID);

        $report .= ' added mail as note to git';
        $noteadded = repo_add_note($repo, $date, $subject, $body);

        $x = $glclient->api('projects')->show($repoID);
        $repoNoteURL = $x['web_url'] . '/blob/master' . $noteadded['filepath'];
        $info['repoNoteURL'] = $repoNoteURL;
        $report .= ' ' . $repoNoteURL . ' ';

        foreach ($attachments as $f)
        {
            $repofilename = '/notes/' . $noteadded['filename'] . '_attachments/' . sanitize_filename($f['filename']);
            $commitmsg = 'gitnotes: add attachment ' . $f['filename'];
            $repo->writeFile($repofilename, $f['attachment'], $commitmsg);
            $report .= ', added file ' . $f['filename'] . ' to git';
        }
    }

    if (strlen($kb) > 0)
    {
        list($board, $task) = explode('|', $kb);
        $board = (int) $board;
        $task  = (int) $task;
    }

    # Report note addition to the relevant Rocketchat channel.
    if (strlen($roomID) > 0)
    {
        $report .= ', all from an email with subject ' . $subject;
        if (isset($board) && $board > 0 && isset($task) && $task > 0)
        {
            $kanboardTaskURL = 'https://kanboard.radicallyopensecurity.com/project/' . $board . '/task/' . $task;
            $info['kanboardTaskURL'] = $kanboardTaskURL;
            $report .= '   ' . $kanboardTaskURL;
        }
        tell_rosbot($report, $roomID);
    }

    if (isset($board) && $board > 0 && isset($task) && $task > 0)
    {
        $kbclient = GlobalKanboardClient::$client;
        $kbclient->addComment($task, $body);
    }

    return $info;
}

# Helper functions

function repo_add_note($repo, $date, $subject, $content)
{
    $name = date('Y-m-d\TH_i_s', $date) . "-" . sanitize_filename($subject);
    $subject = 'gitnotes: ' . $subject;
    $notename = '/notes/' . $name . ".txt";
    $repo->writeFile($notename, $content, $subject);
    $info = array("filename" => $name, "filepath" => $notename);
    return $info;
}

function sanitize_filename($x)
{
    $search = array (' ','\t','[',']','{','}','/');
    $repl = array ('-','-','-','-','-','-','-');
    # Remove any nonstandard characters.
    $sanitized = preg_replace('~[^A-Za-z0-9_@.-]~', '', str_replace($search, $repl, $x));
    # Remove any multiple dots and dots at start or end.
    # (Gitlab will not create files with a double dot in the name.)
    $sanitized = preg_replace('~^[.]+~', '', $sanitized);
    $sanitized = preg_replace('~[.]+$~', '', $sanitized);
    $sanitized = preg_replace('~\.[.]+~', '', $sanitized);
    return $sanitized;
}

?>
