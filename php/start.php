<?php

require_once(__DIR__ . '/common-includes.php');
require_once(__DIR__ . '/log.php');
require_once(__DIR__ . '/kanboard.php');
require_once(__DIR__ . '/rocketchat.php');
require_once(__DIR__ . '/gitlab.php');
require_once(__DIR__ . '/git.php');
require_once(__DIR__ . '/user.php');
require_once(__DIR__ . '/triad.php');
require_once(__DIR__ . '/webhook.php');

require_once(__DIR__ . '/../roles.php');

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

function startproject($type, $projectName, $basedOn = null)
{
    $log = GlobalLog::$log;
    $kanboardClient = GlobalKanboardClient::$client;
    $rocketchatClient = GlobalRocketChatClient::$client;
    $glclient = GlobalGitlabClient::$client;

    $projectName = validate($projectName);
    $alias = $type . '-' . $projectName;
    $customerName = explode('-', $projectName, 2)[0];

    # Check if the corresponding quote project can be found in case of startpentest.
    if ($type == Triad::PEN)
    {
        # Get corresponding quote repo.
        try
        {
            if (is_null($basedOn))
            {
                $lookingFor = Triad::OFF . '-' . $projectName;
                $quoteTriad = Triad::fromTypeName(Triad::OFF, $projectName);
            }
            else
            {
                $basedOn = validate($basedOn);
                $lookingFor = $basedOn;
                $quoteTriad = Triad::fromAlias($basedOn);
            }
        }
        catch (Exception $e)
        {
            $log->error('[-] We could not find the quote project to base this pentest on (looking for *' . $lookingFor . '*).');
            $log->info('You can specify this quote project as a parameter to startpentest: `startpentest company-project off-company-older-project`');
            throw new Exception('quote not found');
        }
    }

    # Add staff members based on roles.
    switch ($type)
    {
        case Triad::OFF:
            $members = RosUser::havingRole(ROLE_MEMBER_NEW_OFFERTES);
            break;
        case Triad::PEN:
            $members = RosUser::havingRole(ROLE_MEMBER_NEW_PENTESTS);
            break;
        default:
            $members = array();
    }

    # Add rosbot.
    $members[] = RosUser::botUser();

    # Add customers.
    try
    {
        $customerAlias = explode('-', $projectName, 2)[0];
        $customer = RosCustomer::fromAlias($customerAlias);
        $customerMembers = $customer->newProjectMembers();
        $log->info('This is customer *' . $customer->customerName . '*, so automatically adding members ' .
            implode(', ', array_map(function($user) { return $user->rocketchatName; }, $customerMembers)));
        $members = array_merge($members, $customerMembers);
    }
    catch (Exception $e)
    {
        $log->debug('Auto-adding customer members failed: ' . $e->getMessage());
    }

    $memberNames = implode(', ', array_map(function($user) { return $user->rocketchatName; }, $members));
    $log->info('All members of this new project: ' . $memberNames);

    # Create gitlab project.
    $log->info('[+] setting up new gitlab repo');
    $project = createProject($alias);
    $log->info('[+] successfully created gitlab project ' . $alias . ' with id ' . $project['id']);

    # Add gitlab project members.
    define('GITLAB_MASTER_ACCESS', 40);
    $log->info('[+] adding members ' . $memberNames . ' to gitlab...');
    foreach($members as $user)
    {
        $userID = $user->gitlabID;
        if (is_null($userID))
        {
            $log->warning('User ' . $user->username . ' has no gitlab ID recorded, so will not be added to the repo.');
            continue;
        }
        $result = $glclient->api('projects')->addMember($project['id'], $userID, GITLAB_MASTER_ACCESS);
    }

    # Create rocketchat channel
    $channelName = $alias;
    $rocketchatMembers = array_map(function($user) { return $user->rocketchatName; }, $members);
    $log->info("[+] Creating rocketchat channel $channelName, with members " . implode(', ', $rocketchatMembers));
    $newChannelID = $rocketchatClient->createChatroom($channelName, $rocketchatMembers);
    if (is_null($newChannelID))
    {
        $log->error('[-] Creating new channel failed.');
        throw new Exception('Starting project failed.');
    }
    $chatURL = 'https://chat.radicallyopensecurity.com/group/' . $channelName;
    $log->info("[+] Visit here: $chatURL");

    # Create task on kanboard.
    $log->info('[+] Adding task to kanboard...');
    $taskInfo = $kanboardClient->projectTask($type, $projectName, $project['ssh_url_to_repo'], $project['web_url'], $chatURL);
    
    # Insert triad metadata into database.
    $log->info('[+] Inserting project info into database...');
    $triad = Triad::newFromParts($alias, $newChannelID, $project['id'], $taskInfo['proj_id'], $taskInfo['task_id']);

    # Create webhook to inject gitlab events into rocketchat channel.
    $log->info('[+] Adding gitlab/rocketchat integration...');
    addWebhook($triad);

    # Initialise repo from templates, and possibly related repositories.
    switch($type)
    {
        case Triad::OFF:
            initOfferteRepo($projectName, $project);
            break;
        case Triad::PEN:
            initPentestRepo($projectName, $project, $quoteTriad);
            break;
        case 'test':
            initOfferteRepo($projectName, $project);
            break;
        default:
            $log->error('Unknown project type ' . $type);
            throw new Exception('unknown project type');
    }
    
    # Send initial welcome message
    $message = '@all Hello!';
    $rocketchatClient->sendChatMessage($newChannelID, $message);
    
    $log->info('[+] Done.');
}

function initOfferteRepo($projectName, $project)
{
    $log = GlobalLog::$log;
    $glclient = GlobalGitlabClient::$client;

    $repoDir = exec('mktemp -d');
    register_shutdown_function('cleanup', $repoDir);
    
    $log->debug('Updating template repos.');
    updateRepo(PENTEXT_DIR);
    updateRepo(GENERAL_INFO_DIR);
    
    $log->info('[+] generating repo contents from templates...');

    $repo = cloneRepo($project['path_with_namespace'], $repoDir);

    system('cp -r ' . PENTEXT_DIR . '/xml/* ' . $repoDir);
    system('rm -r ' . $repoDir . '/doc');
    $readmeFile = $repoDir . '/README.md';
    $projectOverview = file_get_contents($readmeFile);
    $readme = 'clone url: ' . $project['ssh_url_to_repo'];
    $readme .= PHP_EOL . PHP_EOL;
    $readme .= file_get_contents(GENERAL_INFO_DIR . '/Onboarding_manuals/Kanban/kanban-offerte-checklist.md');
    $readme .= PHP_EOL . PHP_EOL;
    $readme .= $projectOverview;
    file_put_contents($readmeFile, $readme);

    $repo
      ->add('.')
      ->commit('Set up quote from templates.')
      ->push();

    $gitlabRepo = new \Gitlab\Model\Project($project['id'], $glclient);
    addRetrospectiveIssue(Triad::OFF, $gitlabRepo);
    
    $log->info('[+] visit ' . $project['web_url']);
    $log->info('[+] or clone ' . $project['ssh_url_to_repo']);
}

function initPentestRepo($projectName, $project, $quoteTriad)
{
    $log = GlobalLog::$log;
    $glclient = GlobalGitlabClient::$client;

    $log->info('Collecting info from corresponding quote...');
    
    $repoDir = exec('mktemp -d');
    register_shutdown_function('cleanup', $repoDir);
    
    $branch = 'master';
    $target = 'quote';
    
    if (! file_exists(SAXON))
    {
        $log->error('[-] The saxon jar file was not found at ' . SAXON);
        throw new Exception('saxon not found');
    }
    
    $quoteRepo = new GitlabRepo($glclient, $quoteTriad->gitlabID, $branch);
    
    # Collect quote and client info from corresponding quote.
    $quote = $quoteRepo->readFileMaybe('source/quote.xml');
    if (is_null($quote))
        $quote = $quoteRepo->readFileMaybe('source/offerte.xml');
    if (is_null($quote))
    {
        $log->error('[-] Could not find quote.xml or offerte.xml in git repo of ' . $quoteTriad->type . '-' . $quoteTriad->name);
        throw new Exception('[-] Project creation aborted.');
    }
    $clientInfo = $quoteRepo->readFileMaybe('source/client_info.xml');
    if (is_null($clientInfo))
    {
        $log->error('[-] Could not find client_info.xml in git repo of ' . $quoteTriad->type . '-' . $quoteTriad->name);
        throw new Exception('[-] Project creation aborted.');
    }
    
    $gitlabRepo = new \Gitlab\Model\Project($project['id'], $glclient);
    addRetrospectiveIssue(Triad::PEN, $gitlabRepo);
    
    updateRepo(PENTEXT_DIR);
    
    # Clone new project repo from gitlab.
    $repo = cloneRepo($project['path_with_namespace'], $repoDir);
    
    # Copy the pentext framework.
    system('cp -r ' . PENTEXT_DIR . '/xml/* ' . $repoDir);
    # remove the docs
    system('rm -r ' . $repoDir . '/doc');
    
    file_put_contents($repoDir . '/source/quote.xml', $quote);
    file_put_contents($repoDir . '/source/client_info.xml', $clientInfo);

    # Convert quote to report.
    $log->info('Converting quote to report template...');
    $cmd = 'cd ' . $repoDir . '/source';
    $cmd .= ' && java -jar ' . SAXON . ' -s:quote.xml -xsl:../xslt/off2rep.xsl -o:report.xml';
    $cmd .= ' 2>&1';
    $output = shell_exec($cmd);
    $log->debug('Saxon output: ' . $output);
    if (! file_exists($repoDir . '/source/report.xml'))
    {
        $log->error('[-] hmmm... failed to convert quote into report.xml');
        throw new Exception('Starting pentest project failed.');
    }
    
    # Push initial commit to gitlab repo.
    $log->info('Adding initial repo contents to gitlab...');
    $repo->add('.');
    $repo->commit('Initialized pentest repository from PenText');
    $repo->push();
}

function updateRepo($path)
{
    $log = GlobalLog::$log;

    $process = new Process('git pull');
    $process->setWorkingDirectory($path);

    try
    {
        $process->mustRun();
    }
    catch (ProcessFailedException $e)
    {
        $log->warning($e->getMessage() . PHP_EOL . $process->getOutput() . PHP_EOL . $process->getErrorOutput());
    }
}

function createProject($alias)
{
    $log = GlobalLog::$log;
    $glclient = GlobalGitlabClient::$client;

    try
    {
        $project = $glclient->api('projects')->create($alias, array(
          'namespace_id' => GITLAB_NAMESPACE_ID,
          'visibility' => 'private',
          'description' => '',
          'wiki_enabled' => true,
          'snippets_enabled' => true,
          'issues_enabled' => true,
          'merge_requests_enabled' => true
        ));
    }
    catch (Exception $e)
    {
        $msg = $e->getMessage();
        if ($msg == '"route.path" has already been taken, "route" is invalid, "name" has already been taken, "path" has already been taken')
        {
            $newMsg = '[-] Project name ' . $alias . ' is already taken.';
            $log->warning($newMsg);
            throw(new Exception('project name taken'));
        }
        else
        {
            $log->error('[-] There was a problem creating the gitlab repo.');
            throw($e);
        }
    }

    return $project;
}

function addRetrospectiveIssue($type, $project)
{
    $issue = $project->createIssue('Retrospective: add your feedback HERE', array(
      'description' => "Please drop all your positive/negative comments here, so that we can keep on improving our processes. It's important that we learn from **what**. No need for namecalling, *who* is unimportant :wink:\n# Thumbs up\n\n# Improvement\n\n## Not project related\n\n## Project related"
    ));

    switch($type)
    {
        case Triad::PEN:
            $project->addLabel('documentation', '#0000FF');
            $project->addLabel('finding'      , '#00C800');
            $project->addLabel('lead'         , '#E4D700');
            $project->addLabel('non-finding'  , '#C80000');
            $project->addLabel('future-work'  , '#F8B7B2');
            break;
        default:
    }
}

function cleanup($repoDir)
{
    system('rm -rf ' . $repoDir);
}

function validate($input)
{
    $log = GlobalLog::$log;

    $lower = strtolower($input);
    if ($lower != $input)
    {
        $log->warning('Changing project name to lower case: ' . $lower);
        $input = $lower;
    }

    if (1 !== preg_match('/[a-z0-9_-]+/', $input))
    {
        $log->error('Input contains invalid characters.');
        throw new Exception('Invalid input.');
    }

    return $input;
}

?>
