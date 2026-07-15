<?php
declare(strict_types=1);

class Database
{
    private $host;
    private $name;
    private $user;
    private $pass;
    private $charset;

    public function __construct(array $config)
    {
        $this->host = $config['host'];
        $this->name = $config['name'];
        $this->user = $config['user'];
        $this->pass = $config['pass'];
        $this->charset = $config['charset'];
    }

    public function getConnection(): PDO
    {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $this->host, $this->name, $this->charset);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ];

        return new PDO($dsn, $this->user, $this->pass, $options);
    }
}
