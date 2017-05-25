<?php

require_once(dirname(__FILE__) . '/../secrets.php');
require_once(dirname(__FILE__) . '/../config.php');

class DB
{
    private $connection;

    public function __construct($dbName)
    {
        $db = new PDO('mysql:host=' . MYSQL_HOST . ';dbname=' . $dbName . ';charset=utf8mb4', MYSQL_USER, MYSQL_PASSWORD);
        $this->connection = $db;
    }

    public function query($sql, $params = array())
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
    }

    public function querySingle($sql, $params = array())
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    }

    public function queryList($sql, $params = array())
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    }
}

?>
