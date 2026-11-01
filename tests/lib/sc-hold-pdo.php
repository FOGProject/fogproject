<?php
/**
 * A narrow FogFakeDb adapter for scratch-DB integration tests.
 * query()->fetch() returns one associative row; fetch(..., 'fetch_all') returns rows.
 * link() remains the real PDO for transactions and direct statements.
 */
class ScHoldPdoDb extends FogFakeDb
{
    private $statement = null;
    private $result = [];
    private $manualResult = false;
    private $affectedCount = 0;
    private $directoryInsertID = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct();
        $this->pdo = $pdo;
        $events = array_merge(self::KNOWN_EVENTS, ['DIRECTORY_CONNECTION', 'HOST_EDIT_AD_FIELDS']);
        $known = new \ReflectionProperty(FOG\Base\HookManager::class, 'knownEvents');
        $known->setAccessible(true);
        $known->setValue(null, array_fill_keys($events, true));
    }

    /** Match PDODB's captured-link check and cursor handoff for fence reads. */
    public function closeCursorForFence(PDO $pdo)
    {
        if ($this->pdo !== $pdo) {
            throw new FOG\Agent\DirectoryFenceRefusal('directory_fence_connection_changed');
        }
        if ($this->statement instanceof \PDOStatement) {
            $this->statement->closeCursor();
        }
    }

    public function query($sql, $data = [], $params = [])
    {
        $directorySqlScope = FOG\Agent\DirectoryNameFence::sqlScope();
        $directorySqlSuccess = false;
        $this->directoryInsertID = null;
        try {
            $this->log[] = $sql;
            if ($this->statement instanceof \PDOStatement) {
                $this->statement->closeCursor();
            }
            $this->statement = null;
            $this->result = [];
            $this->manualResult = false;
            $this->affectedCount = 0;
            $this->error = false;
            $this->errorCode = false;

            try {
                if (is_array($data) && count($data)) {
                    $sql = vsprintf($sql, $data);
                }
                if (!$sql) {
                    throw new \PDOException('No query passed');
                }
                FOG\Agent\DirectoryNameFence::beforeSQL($this->pdo, $sql, $params);
                if (false !== strpos($sql, 'hookEvents')) {
                    foreach (self::KNOWN_EVENTS as $event) {
                        $this->result[] = ['heName' => $event];
                    }
                    $this->manualResult = true;
                    $directorySqlSuccess = false === $this->error;
                    return $this;
                }
                $this->statement = $this->pdo->prepare($sql);
                $this->statement->execute($params);
                $this->affectedCount = $this->statement->rowCount();
            } catch (\PDOException $e) {
                $this->error = $e->getMessage();
                $this->errorCode = $e->getCode();
                $this->result = false;
            }
            $directorySqlSuccess = false === $this->error;
            return $this;
        } finally {
            try {
                $this->directoryInsertID = $directorySqlScope->finishSQL($directorySqlSuccess);
            } finally {
                $directorySqlScope->close();
            }
        }
    }

    public function fetch($mode = \PDO::FETCH_ASSOC, $type = 'fetch_assoc')
    {
        if ($this->manualResult || false === $this->result || !$this->statement) {
            if ($this->manualResult && 'fetch_all' !== strtolower((string)$type)) {
                $this->result = $this->result[0] ?? false;
                $this->manualResult = false;
            }
            return $this;
        }
        $mode = $mode ?: \PDO::FETCH_ASSOC;
        if ('fetch_all' === strtolower((string)$type)) {
            $this->result = $this->statement->fetchAll($mode);
        } else {
            $this->result = $this->statement->fetch($mode);
        }
        $this->statement->closeCursor();
        $this->statement = null;
        return $this;
    }

    public function get($field = '')
    {
        if (false === $this->result || true === $this->result || '' === $field
            || !is_array($this->result)) {
            return $this->result;
        }
        if (array_key_exists($field, $this->result)) {
            return $this->result[$field];
        }
        $values = [];
        foreach ($this->result as $row) {
            if (is_array($row) && array_key_exists($field, $row)) {
                $values[] = $row[$field];
            }
        }
        return $values ?: $this->result;
    }

    public function insertId()
    {
        if (null !== $this->directoryInsertID) {
            return $this->directoryInsertID;
        }
        if (!$this->pdo instanceof \PDO) {
            throw new \LogicException('ScHoldPdoDb requires a real PDO connection for insertId');
        }
        return (int)$this->pdo->lastInsertId();
    }

    public function affectedRows()
    {
        return (int)$this->affectedCount;
    }

    public function sqlerror()
    {
        return (string)$this->error;
    }
}
