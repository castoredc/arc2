<?php

/**
 * Adapter to enable usage of PDO functions.
 *
 * @author Benjamin Nowack <bnowack@semsol.com>
 * @author Konrad Abicht <konrad.abicht@pier-and-peer.com>
 * @license W3C Software License and GPL
 * @homepage <https://github.com/semsol/arc2>
 */

namespace ARC2\Store\Adapter;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;

/**
 * Doctrine Adapter - Handles database operations using Doctrine Connections.
 */
class DoctrineAdapter extends AbstractAdapter
{
    /** @var Connection */
    private $connection;

    public function __construct(array $configuration = array())
    {
        parent::__construct($configuration);

        $this->connection = $configuration['connection'];
    }

    public function checkRequirements()
    {
        if (false == \extension_loaded('pdo_mysql')) {
            throw new \Exception('Extension pdo_mysql is not loaded.');
        }
    }

    public function getAdapterName()
    {
        return 'doctrine';
    }

    public function connect($existingConnection = null)
    {
        return null;
    }

    /**
     * @return void
     */
    public function disconnect()
    {
        // FYI: https://stackoverflow.com/questions/18277233/pdo-closing-connection
        $this->connection->close();
    }

    public function escape($value)
    {
        $quoted = $this->connection->quote($value);

        /*
         * fixes the case, that we have double quoted strings like:
         *      ''x1''
         *
         * remember, this value will be surrounded by quotes later on!
         * so we don't send it back with quotes around.
         */
        if ("'" == \substr($quoted, 0, 1)) {
            $quoted = \substr($quoted, 1, \strlen($quoted)-2);
        }

        return $quoted;
    }

    /**
     * @param string $sql
     *
     * @return array
     */
    public function fetchList($sql)
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'fetchList'
        ];

        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute();
        $rows = $stmt->fetchAllAssociative();

        return $rows;
    }

    public function fetchRow($sql)
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'fetchRow'
        ];

        $row = false;
        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute();
        $rows = $stmt->fetchAllAssociative();
        if (0 < \count($rows)) {
            $row = \array_values($rows)[0];
        }

        return $row;
    }

    public function getCollation()
    {
        $row = $this->fetchRow('SHOW TABLE STATUS LIKE "'.$this->getTablePrefix().'setting"');

        if (isset($row['Collation'])) {
            return $row['Collation'];
        } else {
            return '';
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getConnectionId()
    {
        return $this->connection->executeQuery('SELECT CONNECTION_ID()')->fetchAssociative();
    }

    public function getDBSName()
    {
        $this->connection->getDatabasePlatform()->getName();
    }

    public function getServerInfo()
    {
        $connection = $this->connection->getWrappedConnection();

        // Automatic platform version detection.
        if ($connection instanceof ServerInfoAwareConnection && ! $connection->requiresQueryForServerVersion()) {
            return $connection->getServerVersion();
        }

        // Unable to detect platform version.
        return null;
    }

    public function getServerVersion()
    {
        $res = \preg_match(
            "/([0-9]+)\.([0-9]+)\.([0-9]+)/",
            $this->getServerInfo(),
            $matches
        );

        return 1 == $res
            ? \sprintf('%02d-%02d-%02d', $matches[1], $matches[2], $matches[3])
            : '00-00-00';
    }

    public function getErrorCode()
    {
        return $this->connection->errorCode();
    }

    public function getErrorMessage()
    {
        return end($this->errors);
    }

    public function getLastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    public function getNumberOfRows($sql)
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'getNumberOfRows'
        ];

        try {
            $stmt = $this->connection->prepare($sql);
            $result = $stmt->execute();
            $rowCount = \count($stmt->fetchAllAssociative());
            return $rowCount;
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->errors[] = $e->getMessage();
            return 0;
        }
    }

    public function getStoreName()
    {
        if (isset($this->configuration['store_name'])) {
            return $this->configuration['store_name'];
        }

        return 'arc';
    }

    public function getTablePrefix()
    {
        $prefix = '';
        if (isset($this->configuration['db_table_prefix'])) {
            $prefix = $this->configuration['db_table_prefix'].'_';
        }

        $prefix .= $this->getStoreName().'_';
        return $prefix;
    }

    /**
     * @param string $sql Query
     *
     * @return bool True if query ran fine, false otherwise.
     */
    public function simpleQuery($sql)
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'simpleQuery'
        ];

        $stmt = $this->connection->prepare($sql);
        try {
            $stmt->execute();
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return false;
        }
        return true;
    }

    /**
     * Encapsulates internal PDO::exec call. This allows us to extend it, e.g. with caching functionality.
     *
     * @param string $sql
     *
     * @return int Number of affected rows.
     */
    public function exec($sql)
    {
        // save query
        $this->queries[] = [
            'query' => $sql,
            'by_function' => 'exec'
        ];

        try {
            return $this->connection->executeStatement($sql);
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->errors[] = $e->getMessage();
            return 0;
        }
    }
}
