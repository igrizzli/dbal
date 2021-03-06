<?php

declare(strict_types=0);

namespace Doctrine\DBAL\Driver\IBMDB2;

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use stdClass;
use const DB2_AUTOCOMMIT_OFF;
use const DB2_AUTOCOMMIT_ON;
use function db2_autocommit;
use function db2_commit;
use function db2_connect;
use function db2_escape_string;
use function db2_exec;
use function db2_last_insert_id;
use function db2_num_rows;
use function db2_pconnect;
use function db2_prepare;
use function db2_rollback;
use function db2_server_info;

class DB2Connection implements ServerInfoAwareConnection
{
    /** @var resource */
    private $conn = null;

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $driverOptions
     *
     * @throws DB2Exception
     */
    public function __construct(array $params, string $username, string $password, array $driverOptions = [])
    {
        if (isset($params['persistent']) && $params['persistent'] === true) {
            $conn = db2_pconnect($params['dbname'], $username, $password, $driverOptions);
        } else {
            $conn = db2_connect($params['dbname'], $username, $password, $driverOptions);
        }

        if ($conn === false) {
            throw DB2Exception::fromConnectionError();
        }

        $this->conn = $conn;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion() : string
    {
        /** @var stdClass $serverInfo */
        $serverInfo = db2_server_info($this->conn);

        return $serverInfo->DBMS_VER;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion() : bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $sql) : DriverStatement
    {
        $stmt = @db2_prepare($this->conn, $sql);
        if (! $stmt) {
            throw DB2Exception::fromStatementError();
        }

        return new DB2Statement($stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql) : ResultStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function quote(string $input) : string
    {
        return "'" . db2_escape_string($input) . "'";
    }

    /**
     * {@inheritdoc}
     */
    public function exec(string $statement) : int
    {
        $stmt = @db2_exec($this->conn, $statement);

        if ($stmt === false) {
            throw DB2Exception::fromStatementError();
        }

        return db2_num_rows($stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(?string $name = null) : string
    {
        return db2_last_insert_id($this->conn);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction() : void
    {
        if (! db2_autocommit($this->conn, DB2_AUTOCOMMIT_OFF)) {
            throw DB2Exception::fromConnectionError($this->conn);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function commit() : void
    {
        if (! db2_commit($this->conn)) {
            throw DB2Exception::fromConnectionError($this->conn);
        }

        if (! db2_autocommit($this->conn, DB2_AUTOCOMMIT_ON)) {
            throw DB2Exception::fromConnectionError($this->conn);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack() : void
    {
        if (! db2_rollback($this->conn)) {
            throw DB2Exception::fromConnectionError($this->conn);
        }

        if (! db2_autocommit($this->conn, DB2_AUTOCOMMIT_ON)) {
            throw DB2Exception::fromConnectionError($this->conn);
        }
    }
}
