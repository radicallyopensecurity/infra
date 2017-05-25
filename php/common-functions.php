<?php


define('INPUT_PROJECT', '/^[A-Za-z0-9_-]*$/');
define('INPUT_FREEFORM', '/^.*$/');

function verifyInput($input, $inputType = null)
{
    if (is_null($inputType))
    {
        $inputType = INPUT_PROJECT;
    }

    if (!preg_match($inputType, $input))
    {
        throw new Exception('Input contains illegal character.');
    }
    return $input;
}

?>
