<?php

require_once(__DIR__ . '/common-includes.php');
require_once(__DIR__ . '/log.php');
require_once(__DIR__ . '/user.php');
require_once(__DIR__ . '/db.php');

# File with constants defining role names.
require_once(__DIR__ . '/../roles.php');

function userHasRole($rosUser, $roleName)
{
    $db = new DB('rosbotuser');
    $roles = $db->queryList('select 1
        from user
          natural join user_role
          natural join role
        where user.user_id = :user_id
          and role.name = :roleName;
    ', array(
        ':roleName' => $roleName,
        ':user_id' => $rosUser->userID
    ));
    if (count($roles) > 0)
    {
        return true;
    }
    return false;
}

?>
