<?php

namespace Phalcon\Db\Adapter\Pdo;

use \Phalcon\Db\Column as PhalconColumn;
use \Phalcon\Db\RasColumn as Column;
use \Phalcon\Db\Index;

class Dump extends Mysql
{

    static protected $_types = [
        '0' => 'INT',
        '1' => 'DATE',
        '2' => 'VARCHAR',
        '3' => 'DECIMAL',
        '4' => 'DATETIME',
        '5' => 'CHAR',
        '6' => 'TEXT',
        '7' => 'FLOAT',
        '8' => 'BOOLEAN',
        '9' => 'DOUBLE',
        '32' => 'ENUM',
        '33' => 'TIME',
    ];

    static private function getCreateTableColumn(Column $columnInfo)
    {
        $v = $columnInfo->getColumnValues();
        $values = ($v
                    ? '(' . implode(',', array_map(function($q){ return "'" . addslashes($q) . "'";}, $v)) . ')'
                    : null);
        $result = [
            '`' . $columnInfo->getName() . '`',
            ('' !== $columnInfo->getType()) ? self::$_types[$columnInfo->getType()] : null,
            $columnInfo->getSize() ? '(' . $columnInfo->getSize() . ')' : $values,
            ($columnInfo->isUnsigned()) ? "UNSIGNED" : null,
            ($columnInfo->isNotNull()) ? "NOT NULL" : null,
            ($columnInfo->isAutoIncrement()) ? "AUTO_INCREMENT" : null,
            ($columnInfo->getDefault()) ? "DEFAULT " . $columnInfo->getDefault() : null,
        ];
        return join(' ', array_values($result));
    }

    static private function getCreateTableIndex($indexInfo)
    {
        $class = get_class($indexInfo);
        switch (true) {
            case $indexInfo->getName() === 'PRIMARY':
                $type = 'PRIMARY KEY ';
                break;
            case $class == "Phalcon\\Db\\UniqueIndex":
                $type = 'UNIQUE KEY `' . $indexInfo->getName() . '`';
                break;
            case $class == "Phalcon\\Db\\FullTextIndex":
                $type = 'FULLTEXT KEY  `' . $indexInfo->getName() . '`';
                break;
            default:
                $type = 'INDEX `' . $indexInfo->getName() . '`';
        }

        $result = [

            $type,
            '(' . join(',', array_map(function($item) use ($indexInfo) {
                                            return '`' . $item . '`';
                                        }, $indexInfo->getColumns())) . ')',

        ];
        return join(' ', $result);
    }

    static public function createTableSQL($tableName, $schemaName, $definition)
    {
        $sql = [];
        $columns = [];
        $keys = [];
        $sql[] = "CREATE TABLE " . $tableName . " (";
        foreach ($definition['columns'] as $column) {
            $columns[] = self::getCreateTableColumn($column);
        }
        $columns[] = '';
        $sql[] = join(',', $columns);
        foreach ($definition['indexes'] as $index) {
            $keys[] = self::getCreateTableIndex($index);
        }
        $sql[] = join(',', $keys);
        $sql[] = ") ENGINE = " . $definition['options']['ENGINE'];
        $sql[] = !empty($definition['options']['AUTO_INCREMENT']) ? "AUTO_INCREMENT = " . $definition['options']['AUTO_INCREMENT'] : '';
        $sql[] = "COLLATE = " . $definition['options']['TABLE_COLLATION'];
        return implode(' ', $sql);
    }

    public function createTable($tableName, $schemaName, $definition)
    {
        echo self::createTableSQL($tableName, $schemaName, $definition) . ';' . PHP_EOL;
    }

    static public function addColumnSQL($tableName, $schemaName, $column)
    {
        return "ALTER TABLE {$tableName} ADD COLUMN " . self::getCreateTableColumn($column);
    }

    public function addColumn($tableName, $schemaName, $column)
    {
        echo self::addColumnSQL($tableName, $schemaName, $column) . ';' . PHP_EOL;
    }

    static public function modifyColumnSQL($tableName, $schemaName, $column)
    {
        return "ALTER TABLE {$tableName} CHANGE COLUMN `{$column->getName()}` "
        . self::getCreateTableColumn($column);
    }

    public function modifyColumn($tableName, $schemaName, $column)
    {
        echo self::modifyColumnSQL($tableName, $schemaName, $column) . ";" . PHP_EOL;
    }

    public function dropColumn($tableName, $schemaName, $columnName)
    {
        echo "ALTER TABLE {$tableName} DROP COLUMN `{$columnName}`" . ';' . PHP_EOL;
    }

    static public function addIndexSQL($tableName, $schemaName, $index)
    {
        return "ALTER TABLE {$tableName} ADD " . static::getCreateTableIndex($index);
    }

    public function addIndex($tableName, $schemaName, $index)
    {
        echo self::addIndexSQL($tableName, $schemaName, $index) . ';' . PHP_EOL;
    }

    static public function dropIndexSQL($tableName, $schemaName, $indexName)
    {
        return "ALTER TABLE {$tableName} DROP INDEX {$indexName}";
    }

    public function dropIndex($tableName, $schemaName, $indexName)
    {
        echo static::dropIndexSQL($tableName, $schemaName, $indexName) . ";" . PHP_EOL;
    }

    public function addPrimaryKey($tableName, $schemaName, $index)
    {
        echo "ALTER TABLE ADD " . $this->getCreateTableIndex($index) . ";" . PHP_EOL;
    }

    public function dropPrimaryKey($tableName, $schemaName)
    {
        echo "DROP PRIMARY KEY;" . PHP_EOL;
    }

    public function addForeignKey($tableName, $schemaName, $reference)
    {
        $result = [ "ALTER TABLE {$tableName}",
                    "ADD CONSTRAINT `{$reference->getName()}`",
                    'FOREIGN KEY(' . join(',', array_map(function($item) {
                                                            return '`' . $item . '`';
                                                        }, $reference->getColumns())) . ')',
                    "REFERENCES {$reference->getReferencedTable()} ("
                    . join(',', array_map(function($item) {
                                              return '`' . $item . '`';
                                          }, $reference->getReferencedColumns())) . ')',
                    "ON DELETE NO ACTION",
                    "ON UPDATE NO ACTION;"];
        echo join(" ", $result) . PHP_EOL;
    }

    public function dropForeignKey($tableName, $schemaName, $referenceName)
    {
        echo "ALTER TABLE {$tableName} DROP FOREIGN KEY `{$referenceName}`" . PHP_EOL;
    }

    public function query($sqlStatement, $bindParams=null, $bindTypes=null)
    {
        if (preg_match("/(drop|alter)/i", $sqlStatement)) {
            echo $sqlStatement . PHP_EOL;
        } else {
            return parent::query($sqlStatement, $bindParams, $bindTypes);
        }
    }
} 