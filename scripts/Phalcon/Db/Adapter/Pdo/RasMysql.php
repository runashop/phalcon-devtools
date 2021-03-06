<?php

namespace Phalcon\Db\Adapter\Pdo;

use Phalcon\Db\RasIndex as Index;
use Phalcon\Db\FullTextIndex;
use Phalcon\Db\UniqueIndex;
use Phalcon\Db\RasColumn as Column;

class RasMysql extends Mysql {

    const NOT_NULL = 'NO';

    /**
     * describes a table
     *
     * @param string $table
     * @param null $schema
     * @return array|\Phalcon\Db\Column[]
     */
    public function describeColumns($table, $schema = null)
    {
        if (null !== $schema) {
            $table = $schema . '.' . $table;
        }
        $columns = [];
        $result = $this->fetchAll("DESCRIBE " . $table, \Pdo::FETCH_ASSOC);
        foreach ($result as $row) {
            $column = $this->getColumn($row);
            $columns[] = $column;
        }
        return $columns;
    }

    /**
     *
     * returns generated Column by array of definitions
     *
     * @param array $columnInfo
     * @param bool $isFirst
     * @return Column
     */
    protected function getColumn($columnInfo, $isFirst = false)
    {
        $column = [
            'type' => Column::TYPE_CHAR,
            'size' => 0,
            'unsigned' => false,
            'notNull' => false,
            'first' => $isFirst,
            'default' => $columnInfo['Default'],
        ];
        switch(true) {
            case preg_match("/int/", $columnInfo['Type']):
                $column['type'] = Column::TYPE_INTEGER;
                break;
            case preg_match("/enum/", $columnInfo['Type']):
                $column['type'] = Column::TYPE_ENUM;
                if (preg_match("/\((.*?)\)/", $columnInfo['Type'], $matches)) {
                    $column['values'] = explode("','", trim($matches[1], "'"));
                }
                break;
            case preg_match("/boolean|bool/", $columnInfo['Type']):
                $column['type'] = Column::TYPE_BOOLEAN;
                break;
            case preg_match("/date$/", $columnInfo['Type']):
                $column['type'] = Column::TYPE_DATE;
                break;
            case preg_match("/^time/", $columnInfo['Type']):
                $column['type'] = Column::TYPE_TIME;
                break;
            case preg_match("/[^r]char/", $columnInfo['Type']):
                $column['type'] = Column::TYPE_CHAR;
                break;
            case preg_match("/datetime/", $columnInfo['Type']):
                $column['type'] = Column::TYPE_DATETIME;
                break;
            case preg_match("/decimal/", $columnInfo['Type']):
                $column['type'] = Column::TYPE_DECIMAL;
                break;
            case preg_match("/float/", $columnInfo['Type']):
                $column['type'] = Column::TYPE_FLOAT;
                break;
            case preg_match("/text/", $columnInfo['Type']):
                $column['type'] = Column::TYPE_TEXT;
                break;
            case preg_match("/varchar/", $columnInfo['Type']):
                $column['type'] = Column::TYPE_VARCHAR;
                break;
            default:
                $column['type'] = Column::TYPE_CHAR;
        }

        if (preg_match("/\((\d+)\)/", $columnInfo['Type'], $matches)) {
            $column['size'] = $matches[1];
        }

        if ($columnInfo['Type'] && strpos('unsigned', $columnInfo['Type']) !== false) {
            $column['unsigned'] = true;
        }

        if ($columnInfo['Null'] === self::NOT_NULL) {
            $column['notNull'] = true;
        }

        if ($columnInfo['Extra'] && strpos('auto_increment', $columnInfo['Extra']) !== false) {
            $column['autoIncrement'] = true;
        }

        return new Column($columnInfo['Field'], $column);
    }

    public function describeIndexes($table, $schema=null)
    {
        if (null !== $schema) {
            $table = $schema . '.' . $table;
        }
        $indexes = [];
        $groupedIndexes = [];
        $result = $this->fetchAll("SHOW INDEX FROM " . $table, \Pdo::FETCH_ASSOC);
        foreach ($result as $row) {
            $groupedIndexes[$row['Key_name']][] = $row;
        }
        foreach ($groupedIndexes as $indexName => $index) {
            $indexes[$indexName] = $this->getIndex($indexName, $index);
        }
        return $indexes;
    }

    protected function getIndex($name, $fields)
    {
        $columns = [];
        foreach ($fields as $field) {
            $columns[] = $field['Column_name'];
        }
        //$columns = array_intersect_key($fields, ['Column_name' => 1]);
        //$columns = array_intersect_key($fields, ['Column_name' => 1]);
        switch ($fields[0]['Index_type']) {
            case Index::TYPE_FULLTEXT:
                return new FullTextIndex($name, $columns);
                break;
            case Index::TYPE_BTREE:
                if ($name !== Index::TYPE_PRIMARY && $fields[0]['Non_unique'] == 0) {
                    return new UniqueIndex($name, $columns);
                }
            default:
                return new Index($name, $columns);
        }
    }

    /**
     * creates table
     *
     * @param string $tableName
     * @param string $schemaName
     * @param array $definition
     * @return bool
     */
    public function createTable($tableName, $schemaName, $definition)
    {
        $sql = Dump::createTableSQL($tableName, $schemaName, $definition);
        $this->execute($sql);
    }

    /**
     * creates new column
     *
     * @param string $tableName
     * @param string $schemaName
     * @param \Phalcon\Db\Column|\Phalcon\Db\ColumnInterface $column
     * @return void
     */
    public function addColumn($tableName, $schemaName, $column)
    {
        $sql = Dump::addColumnSQL($tableName, $schemaName, $column);
        $this->execute($sql);
    }

    /**
     * alters a column
     *
     * @param string $tableName
     * @param string $schemaName
     * @param \Phalcon\Db\Column|\Phalcon\Db\ColumnInterface $column
     * @return void
     */
    public function modifyColumn($tableName, $schemaName, $column)
    {
        $sql = Dump::modifyColumnSQL($tableName, $schemaName, $column);
        $this->execute($sql);
    }

    /**
     * Adds an index to a table
     *
     * @param string $tableName
     * @param string $schemaName
     * @param \Phalcon\Db\IndexInterface $index
     * @return 	boolean
     */
    public function addIndex($tableName, $schemaName, $index)
    {
        $sql = Dump::addIndexSQL($tableName, $schemaName, $index);
        $this->execute($sql);
    }
} 