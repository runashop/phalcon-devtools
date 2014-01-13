<?php

namespace Phalcon\Db\Adapter\Pdo;

use \Phalcon\Db\Column;
use \Phalcon\Db\Index;

class Dump extends Mysql
{

    protected $_types = [
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
    ];

    /**
     * @param $columnInfo Column
     * @return string
     */
    private function getCreateTableColumn($columnInfo)
    {
        //`varchar` VARCHAR(45) CHARACTER SET 'utf8' COLLATE 'utf8_general_ci' NULL,
        $result = [
            '`' . $columnInfo->getName() . '`',
            ('' !== $columnInfo->getType()) ? $this->_types[$columnInfo->getType()] : '',
            ($columnInfo->getSize()) ? '(' . $columnInfo->getSize() . ')' : '',
            ($columnInfo->isUnsigned()) ? "UNSIGNED" : '',
            ($columnInfo->isNotNull()) ? "NOT NULL" : '',
            ($columnInfo->isAutoIncrement()) ? "AUTO_INCREMENT" : '',

        ];
        return join(' ', $result);
    }

    /**
     *
     * PRIMARY KEY (`int`),
       UNIQUE INDEX `datetime_UNIQUE` (`datetime` ASC),
       INDEX `qweqwe` (`char` ASC));
     *
     * @param $indexInfo \Phalcon\Db\Index
     * @return string
     */
    private function getCreateTableIndex($indexInfo)
    {
        //`varchar` VARCHAR(45) CHARACTER SET 'utf8' COLLATE 'utf8_general_ci' NULL,
        switch ($indexInfo->getName()) {
            case 'PRIMARY':
                $type = 'PRIMARY KEY ';
                break;
            case 'UNIQUE':
                $type = 'UNIQUE INDEX `' . $indexInfo->getName() . '`';
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

    /**
     *
     * CREATE TABLE `runashop_pp2`.`test` (
    `int` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `char` CHAR NULL DEFAULT '',
    `varchar` VARCHAR(45) CHARACTER SET 'utf8' COLLATE 'utf8_general_ci' NULL,
    `datetime` DATETIME NULL,
    PRIMARY KEY (`int`),
    UNIQUE INDEX `datetime_UNIQUE` (`datetime` ASC),
    INDEX `qweqwe` (`char` ASC));

     *
     * @param string $tableName
     * @param string $schemaName
     * @param array $definition
     * @return bool|void
     */
    public function createTable($tableName, $schemaName, $definition)
    {
        $sql = [];
        $columns = [];
        $keys = [];
        $sql[] = "CREATE TABLE " . $tableName . " (";
        foreach ($definition['columns'] as $column) {
            $columns[] = $this->getCreateTableColumn($column);
        }
        $columns[] = '';
        $sql[] = join(',', $columns);
        foreach ($definition['indexes'] as $index) {
            $keys[] = $this->getCreateTableIndex($index);
        }
        $sql[] = join(',', $keys);
        $sql[] = ") ENGINE = " . $definition['options']['ENGINE'];
        $sql[] = "AUTO_INCREMENT = " . $definition['options']['AUTO_INCREMENT'];
        $sql[] = "COLLATE = " . $definition['options']['TABLE_COLLATION'];
        echo implode(' ', $sql) . PHP_EOL;
    }

    public function addColumn($tableName, $schemaName, $column)
    {
        echo "ALTER TABLE {$tableName} ADD COLUMN " . $this->getCreateTableColumn($column) . ';' . PHP_EOL;
    }

    public function modifyColumn($tableName, $schemaName, $column)
    {
        echo "ALTER TABLE {$tableName} CHANGE COLUMN `{$column->getName()}` "
                    . $this->getCreateTableColumn($column) . ";" . PHP_EOL;
    }

    public function dropColumn($tableName, $schemaName, $columnName)
    {
        echo "ALTER TABLE {$tableName} DROP COLUMN `{$columnName}`" . ';' . PHP_EOL;
    }

    public function addIndex($tableName, $schemaName, $index)
    {
        echo "ALTER TABLE {$tableName} ADD " . $this->getCreateTableIndex($index) . ';' . PHP_EOL;
    }

    public function dropIndex($tableName, $schemaName, $indexName)
    {
        echo "ALTER TABLE {$tableName} DROP INDEX {$indexName};" . PHP_EOL;
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