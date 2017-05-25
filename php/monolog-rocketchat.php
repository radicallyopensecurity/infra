<?php

require_once(__DIR__ . '/common-includes.php');
require_once(dirname(__FILE__) . '/rosbot.php');

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;


class RocketChatHandler extends AbstractProcessingHandler
{
    private $room;

    public function __construct($room, $level = Logger::DEBUG, $bubble = true)
    {
        $this->room = $room;
        parent::__construct($level, $bubble);
    }

    protected function write(array $record)
    {
        tell_rosbot($record['formatted'], $this->room);
    }

    public function handleBatch(array $batch)
    {
        $buffer = array();
        foreach ($batch as $record)
        {
            if (!$this->isHandling($record))
            {
                continue;
            }
            if ($this->processors)
            {
                $record = $this->processRecord($record);
            }
            $buffer[] = $this->getFormatter()->format($record);
            $channel = $record['channel'];
        }
        if (count($buffer) == 0)
        {
            return false;
        }
        $output = 'Log from ' . $channel . ':' . PHP_EOL;
        $output .= implode('', $buffer);
        tell_rosbot($output, $this->room);
        return (false === $this->bubble);
    }
}

?>
