<?php

# The channel can be specified by either room name or room ID.
function tell_rosbot($msg, $channel)
{
    $data = array
    (
      "msg" =>  ($msg)
    );
    $data_string =  json_encode($data);

    $url = 'http://localhost:8080/hubot/automessage/' . $channel;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    return ($result == 'OK');
}

?>
