<?php

/*
  +------------------------------------------------------------------------+
  | Phalcon Developer Tools                                                |
  +------------------------------------------------------------------------+
  | Copyright (c) 2011-2014 Phalcon Team (http://www.phalconphp.com)       |
  +------------------------------------------------------------------------+
  | This source file is subject to the New BSD License that is bundled     |
  | with this package in the file docs/LICENSE.txt.                        |
  |                                                                        |
  | If you did not receive a copy of the license and are unable to         |
  | obtain it through the world-wide-web, please send an email             |
  | to license@phalconphp.com so we can send you a copy immediately.       |
  +------------------------------------------------------------------------+
  | Authors: Andres Gutierrez <andres@phalconphp.com>                      |
  |          Eduar Carvajal <eduar@phalconphp.com>                         |
  +------------------------------------------------------------------------+
*/

namespace Phalcon\Mvc\Model;

use Phalcon\Db\Column;
use Phalcon\Db\RasColumn;
use Phalcon\Mvc\Model\Migration\Profiler;
use Phalcon\Mvc\Model\Exception;
use Phalcon\Events\Manager as EventsManager;

/**
 * Phalcon\Mvc\Model\Migration
 *
 * Migrations of DML y DDL over databases
 *
 * @category 	Phalcon
 * @package 	Scripts
 * @copyright   Copyright (c) 2011-2014 Phalcon Team (team@phalconphp.com)
 * @license 	New BSD License
 */
class Migration
{

    /**
     * Migration database connection
     *
     * @var \Phalcon\Db
     */
    protected static $_connection;

    /**
     * Database configuration
     *
     * @var \Phalcon\Config
     */
    private static $_databaseConfig;

    /**
     * Path where to save the migration
     *
     * @var string
     */
    private static $_migrationPath = null;

    private static $_ignoreDrop = false;
    private static $_ignoreAlter = false;

    /**
     * Prepares component
     *
     * @param $database
     *
     * @throws \Phalcon\Exception
     */
    public static function setup($database, $options = [])
    {

        if ( ! isset($database->adapter))
            throw new \Phalcon\Exception('Unspecified database Adapter in your configuration!');

        $dump = isset($options['dump']) ? (bool)$options['dump'] : false;
        self::$_ignoreDrop  = isset($options['ignoreDrop']) ? (bool)$options['ignoreDrop'] : false;
        self::$_ignoreAlter = isset($options['ignoreAlter']) ? (bool)$options['ignoreAlter'] : false;

        if ($dump) {
            $adapter = '\\Phalcon\\Db\\Adapter\\Pdo\\Dump';
        } else {
            $adapter = '\\Phalcon\\Db\\Adapter\\Pdo\\RasMysql';
        }

        if ( ! class_exists($adapter))
            throw new \Phalcon\Exception('Invalid database Adapter!');

        $configArray = $database->toArray();
        unset($configArray['adapter']);
        self::$_connection = new $adapter($configArray);
        self::$_databaseConfig = $database;

        if ( \Phalcon\Migrations::isConsole() && !$dump) {
            $profiler = new Profiler();

            $eventsManager = new EventsManager();
            $eventsManager->attach('db', function ($event, $connection) use ($profiler) {
                if ($event->getType() == 'beforeQuery') {
                    $profiler->startProfile($connection->getSQLStatement());
                }
                if ($event->getType() == 'afterQuery') {
                    $profiler->stopProfile();
                }
            });

            self::$_connection->setEventsManager($eventsManager);
        }
    }

    /**
     * Set the migration directory path
     *
     * @param string $path
     */
    public static function setMigrationPath($path)
    {
        self::$_migrationPath = $path;
    }

    /**
     * Generates all the class migration definitions for certain database setup
     *
     * @param  string $version
     * @param  string $exportData
     * @return array
     */
    public static function generateAll($version, $exportData=null)
    {
        $classDefinition = array();
        foreach (self::$_connection->listTables() as $table) {
            $classDefinition[$table] = self::generate($version, $table, $exportData);
        }

        return $classDefinition;
    }

    /**
     * Generate specified table migration
     *
     * @param      $version
     * @param      $table
     * @param null $exportData
     *
     * @return string
     * @throws Exception
     */
    public static function generate($version, $table, $exportData=null)
    {

        $oldColumn = null;
        $allFields = array();
        $numericFields = array();
        $tableDefinition = array();
        $t1 = "\t";
        $t2 = str_repeat($t1, 2);
        $t3 = str_repeat($t1, 3);
        $t4 = str_repeat($t1, 4);
        $t5 = str_repeat($t1, 5);
        $t6 = str_repeat($t1, 6);
        $t7 = str_repeat($t1, 7);

        if (isset(self::$_databaseConfig->schema)) {
                $defaultSchema = self::$_databaseConfig->schema;
        } elseif (isset(self::$_databaseConfig->adapter) && self::$_databaseConfig->adapter == 'Postgresql') {
                $defaultSchema =  'public';
        } elseif (isset(self::$_databaseConfig->dbname)) {
                $defaultSchema = self::$_databaseConfig->dbname;
        } else {
                $defaultSchema = null;
        }

        $description = self::$_connection->describeColumns($table, $defaultSchema);
        /** @var $field RasColumn */
        foreach ($description as $field) {
            $fieldDefinition = array();
            switch ($field->getType()) {
                case Column::TYPE_INTEGER:
                    $fieldDefinition[] = "'type' => Column::TYPE_INTEGER";
                    $numericFields[$field->getName()] = true;
                    break;
                case Column::TYPE_VARCHAR:
                    $fieldDefinition[] = "'type' => Column::TYPE_VARCHAR";
                    break;
                case Column::TYPE_CHAR:
                    $fieldDefinition[] = "'type' => Column::TYPE_CHAR";
                    break;
                case Column::TYPE_DATE:
                    $fieldDefinition[] = "'type' => Column::TYPE_DATE";
                    break;
                case Column::TYPE_DATETIME:
                    $fieldDefinition[] = "'type' => Column::TYPE_DATETIME";
                    break;
                case Column::TYPE_DECIMAL:
                    $fieldDefinition[] = "'type' => Column::TYPE_DECIMAL";
                    $numericFields[$field->getName()] = true;
                    break;
                case Column::TYPE_TEXT:
                    $fieldDefinition[] = "'type' => Column::TYPE_TEXT";
                    break;
                case Column::TYPE_BOOLEAN:
                    $fieldDefinition[] = "'type' => Column::TYPE_BOOLEAN";
                    break;
                case Column::TYPE_FLOAT:
                    $fieldDefinition[] = "'type' => Column::TYPE_FLOAT";
                    break;
                case RasColumn::TYPE_ENUM:
                    $fieldDefinition[] = "'type' => Column::TYPE_ENUM";
                    break;
                case RasColumn::TYPE_TIME:
                    $fieldDefinition[] = "'type' => Column::TYPE_TIME";
                    break;
                default:
                    throw new Exception('Unrecognized data type ' . $field->getType() . ' at column ' . $field->getName());
            }

            //if ($field->isPrimary()) {
            //	$fieldDefinition[] = "'primary' => true";
            //}

            if ($field->isNotNull()) {
                $fieldDefinition[] = "'notNull' => true";
            }

            if ($field->isAutoIncrement()) {
                $fieldDefinition[] = "'autoIncrement' => true";
            }

            if ($field->getDefault()) {
                $fieldDefinition[] = "'default' => '" . addslashes($field->getDefault()) . "'";
            }

            if ($field->getSize()) {
                $fieldDefinition[] = "'size' => " . $field->getSize();
            }/* else {
                $fieldDefinition[] = "'size' => 1";
            }
            */

            if ($field->getScale()) {
                $fieldDefinition[] = "'scale' => " . $field->getScale();
            }

            if ($field->getColumnValues()) {
                // var_export($field->getColumnValues(), true) for php < 5.4
                $fieldDefinition[] = "'values' => " . json_encode($field->getColumnValues());
            }

            if ($oldColumn != null) {
                $fieldDefinition[] = "'after' => '" . $oldColumn . "'";
            } else {
                $fieldDefinition[] = "'first' => true";
            }

            $oldColumn = $field->getName();
            $tableDefinition[] = "{$t5}new Column(\n{$t6}'" . $field->getName() . "',\n{$t6}[\n{$t7}" . join(",\n{$t7}", $fieldDefinition) . ",\n{$t6}]\n{$t5})";
            $allFields[] = "'".$field->getName()."'";
        }

        $indexesDefinition = array();
        $indexes = self::$_connection->describeIndexes($table, $defaultSchema);
        foreach ($indexes as $indexName => $dbIndex) {
            $indexDefinition = array();
            foreach ($dbIndex->getColumns() as $indexColumn) {
                $indexDefinition[] = "'" . $indexColumn . "'";
            }
            $class = explode('\\',get_class($dbIndex));
            $class = array_pop($class);
            $indexesDefinition[] = "{$t5}new {$class}('".$indexName."', [" . join(", ", $indexDefinition) . "])";
        }

        $referencesDefinition = array();
        $references = self::$_connection->describeReferences($table, $defaultSchema);
        foreach ($references as $constraintName => $dbReference) {

            $columns = array();
            foreach ($dbReference->getColumns() as $column) {
                $columns[] = "'" . $column . "'";
            }

            $referencedColumns = array();
            foreach ($dbReference->getReferencedColumns() as $referencedColumn) {
                $referencedColumns[] = "'" . $referencedColumn . "'";
            }

            $referenceDefinition = array();
            $referenceDefinition[] = "'referencedSchema' => '" . $dbReference->getReferencedSchema() . "'";
            $referenceDefinition[] = "'referencedTable' => '" . $dbReference->getReferencedTable() . "'";
            $referenceDefinition[] = "'columns' => array(" . join(",", $columns) . ")";
            $referenceDefinition[] = "'referencedColumns' => [".join(",", $referencedColumns) . "]";

            $referencesDefinition[] = "{$t5}new Reference('" . $constraintName."', [\n{$t6}" . join(",\n{$t6}", $referenceDefinition) . "\n{$t5}])";
        }

        $optionsDefinition = array();
        $tableOptions = self::$_connection->tableOptions($table, $defaultSchema);
        foreach ($tableOptions as $optionName => $optionValue) {
            if (strtoupper($optionName) !== 'AUTO_INCREMENT') {
                $optionsDefinition[] = "{$t5}'" . strtoupper($optionName) . "' => '" . $optionValue . "'";
            }
        }

        $classVersion = preg_replace('/[^0-9A-Za-z]/', '', $version);
        $className = \Phalcon\Text::camelize(trim($table, '_')) . 'Migration'.$classVersion;

        $classData = "namespace Migration;

use Phalcon\\Db\\RasColumn as Column;
use Phalcon\\Db\\Reference;
use Phalcon\\Mvc\\Model\\Migration;
use Phalcon\\Db\\RasIndex;
use Phalcon\\Db\\FullTextIndex;
use Phalcon\\Db\\UniqueIndex;

/**
 * Class ".$className."
 * @package Migration
 */
class ".$className." extends Migration\n".
"{\n\n".
        "{$t1}public function up()\n".
        "{$t1}{\n{$t2}\$this->morphTable(\n{$t3}'" . $table . "',\n{$t3}[" .
        "\n{$t4}'columns' => [\n" . join(",\n", $tableDefinition) . ",\n{$t4}],";
        if (count($indexesDefinition)) {
            $classData .= "\n{$t4}'indexes' => [\n" . join(",\n", $indexesDefinition) . ",\n{$t4}],";
        }

        if (count($referencesDefinition)) {
            $classData .= "\n{$t4}'references' => [\n".join(",\n", $referencesDefinition) . ",\n{$t5}],";
        }

        if (count($optionsDefinition)) {
            $classData .= "\n{$t4}'options' => [\n".join(",\n", $optionsDefinition) . ",\n{$t4}],\n";
        }

        $classData .= "{$t3}]\n{$t2});\n{$t1}}";
        if ($exportData == 'always' || $exportData == 'oncreate') {

            if ($exportData == 'oncreate') {
                $classData .= "\n\n\tpublic function afterCreateTable() {\n";
            } else {
                $classData .= "\n\n\tpublic function afterUp() {\n";
            }
            $classData .= "\t\t\$this->batchInsert('$table', [\n\t\t\t" . join(",\n\t\t\t", $allFields) . ",\n\t\t]);";

            $fileHandler = fopen(self::$_migrationPath . '/' . $table . '.dat', 'w');
            $cursor = self::$_connection->query('SELECT * FROM ' . $table);
            $cursor->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
            while ($row = $cursor->fetchArray()) {
                $data = array();
                foreach ($row as $key => $value) {
                    if (isset($numericFields[$key])) {
                        if ($value==='' || is_null($value)) {
                            $data[] = 'NULL';
                        } else {
                            $data[] = addslashes($value);
                        }
                    } else {
                        $data[] = "'".addslashes($value)."'";
                    }
                    unset($value);
                }
                fputs($fileHandler, join('|', $data).PHP_EOL);
                unset($row);
                unset($data);
            }
            fclose($fileHandler);

            $classData.="\n\t}";
        }
        $classData.="\n\n}\n";
        $classData = str_replace("{$t1}", "    ", $classData);

        return $classData;
    }

    /**
     * Migrate single file
     *
     * @param $version
     * @param $filePath
     *
     * @throws Exception
     */
    public static function migrateFile($version, $filePath)
    {
        if (file_exists($filePath)) {
            $fileName = basename($filePath);
            $classVersion = preg_replace('/[^0-9A-Za-z]/', '', $version);
            $className = \Phalcon\Text::camelize(str_replace('.php', '', trim($fileName, '_'))).'Migration'.$classVersion;
            $className = "Migration\\{$className}";
            require $filePath;
            if (class_exists($className)) {
                $migration = new $className();
                if (method_exists($migration, 'up')) {
                    $migration->up();
                    if (method_exists($migration, 'afterUp')) {
                        $migration->afterUp();
                    }
                }
            } else {
                throw new Exception('Migration class cannot be found ' . $className . ' at ' . $filePath);
            }
        }
    }

    /**
     * Look for table definition modifications and apply to real table
     *
     * @param $tableName
     * @param $definition
     *
     * @throws Exception
     */
    public function morphTable($tableName, $definition)
    {

        if (isset(self::$_databaseConfig->dbname)) {
            $defaultSchema = self::$_databaseConfig->dbname;
        } else {
            $defaultSchema = null;
        }

        $tableExists = self::$_connection->tableExists($tableName, $defaultSchema);
        if (isset($definition['columns'])) {

            if (count($definition['columns']) == 0) {
                throw new Exception('Table must have at least one column');
            }

            $fields = array();
            foreach ($definition['columns'] as $tableColumn) {
                if (!is_object($tableColumn)) {
                    throw new Exception('Table must have at least one column');
                }
                $fields[$tableColumn->getName()] = $tableColumn;
            }

            if ($tableExists == true) {

                $localFields = array();
                $description = self::$_connection->describeColumns($tableName, $defaultSchema);
                foreach ($description as $field) {
                    $localFields[$field->getName()] = $field;
                }

                foreach ($fields as $fieldName => $tableColumn) {
                    if (!isset($localFields[$fieldName])) {
                        self::$_connection->addColumn($tableName, $tableColumn->getSchemaName(), $tableColumn);
                    } else {

                        $changed = false;

                        if ($localFields[$fieldName]->getType() != $tableColumn->getType()) {
                            $changed = true;
                        }

                        if ($tableColumn->isNotNull() != $localFields[$fieldName]->isNotNull()) {
                            $changed = true;
                        }

                        if ($tableColumn->getSize() != $localFields[$fieldName]->getSize()) {
                            $changed = true;
                        }

                        if ($tableColumn->getDefault() != $localFields[$fieldName]->getDefault()) {
                            $changed = true;
                        }

                        if ($tableColumn->getColumnValues() != $localFields[$fieldName]->getColumnValues()) {
                            $changed = true;
                        }

                        if ($changed == true && !self::$_ignoreAlter) {
                            self::$_connection->modifyColumn($tableName, $tableColumn->getSchemaName(), $tableColumn);
                        }
                    }
                }

                foreach ($localFields as $fieldName => $localField) {
                    if (!isset($fields[$fieldName]) && !self::$_ignoreDrop) {
                        self::$_connection->dropColumn($tableName, null, $fieldName);
                    }
                }
            } else {
                self::$_connection->createTable($tableName, $defaultSchema, $definition);
                if (method_exists($this, 'afterCreateTable')) {
                    $this->afterCreateTable();
                }
            }
        }

        if (isset($definition['references'])) {
            if ($tableExists == true) {

                $references = array();
                foreach ($definition['references'] as $tableReference) {
                    $references[$tableReference->getName()] = $tableReference;
                }

                $localReferences = array();
                $activeReferences = self::$_connection->describeReferences($tableName, $defaultSchema);
                foreach ($activeReferences as $activeReference) {
                    $localReferences[$activeReference->getName()] = array(
                        'referencedTable' => $activeReference->getReferencedTable(),
                        'columns' => $activeReference->getColumns(),
                        'referencedColumns' => $activeReference->getReferencedColumns(),
                    );
                }

                foreach ($definition['references'] as $tableReference) {
                    if (!isset($localReferences[$tableReference->getName()])) {
                        self::$_connection->addForeignKey($tableName, $tableReference->getSchemaName(), $tableReference);
                    } else {

                        $changed = false;
                        if ($tableReference->getReferencedTable()!=$localReferences[$tableReference->getName()]['referencedTable']) {
                            $changed = true;
                        }

                        if ($changed == false) {
                            if (count($tableReference->getColumns()) != count($localReferences[$tableReference->getName()]['columns'])) {
                                $changed = true;
                            }
                        }

                        if ($changed==false) {
                            if (count($tableReference->getReferencedColumns()) != count($localReferences[$tableReference->getName()]['referencedColumns'])) {
                                $changed = true;
                            }
                        }
                        if ($changed == false) {
                            foreach ($tableReference->getColumns() as $columnName) {
                                if (!in_array($columnName, $localReferences[$tableReference->getName()]['columns'])) {
                                    $changed = true;
                                    break;
                                }
                            }
                        }
                        if ($changed == false) {
                            foreach ($tableReference->getReferencedColumns() as $columnName) {
                                if (!in_array($columnName, $localReferences[$tableReference->getName()]['referencedColumns'])) {
                                    $changed = true;
                                    break;
                                }
                            }
                        }

                        if ($changed == true && !self::$_ignoreAlter) {
                            self::$_connection->dropForeignKey($tableName, $tableReference->getSchemaName(), $tableReference->getName());
                            self::$_connection->addForeignKey($tableName, $tableReference->getSchemaName(), $tableReference);
                        }
                    }
                }

                foreach ($localReferences as $referenceName => $reference) {
                    if (!isset($references[$referenceName]) && !self::$_ignoreDrop) {
                        self::$_connection->dropForeignKey($tableName, null, $referenceName);
                    }
                }

            }
        }

        if (isset($definition['indexes'])) {
            if ($tableExists == true) {

                $indexes = array();
                foreach ($definition['indexes'] as $tableIndex) {
                    $indexes[$tableIndex->getName()] = $tableIndex;
                }

                $localIndexes = array();
                $actualIndexes = self::$_connection->describeIndexes($tableName, $defaultSchema);
                foreach ($actualIndexes as $actualIndex) {
                    $localIndexes[$actualIndex->getName()] = $actualIndex->getColumns();
                }

                foreach ($definition['indexes'] as $tableIndex) {
                    if (!isset($localIndexes[$tableIndex->getName()])) {
                        if ($tableIndex->getName() == 'PRIMARY') {
                            self::$_connection->addPrimaryKey($tableName, $tableColumn->getSchemaName(), $tableIndex);
                        } else {
                            self::$_connection->addIndex($tableName, $tableColumn->getSchemaName(), $tableIndex);
                        }
                    } else {
                        $changed = false;
                        if (count($tableIndex->getColumns()) != count($localIndexes[$tableIndex->getName()])) {
                            $changed = true;
                        } elseif (get_class($tableIndex) !== get_class($actualIndexes[$tableIndex->getName()])) {
                            $changed = true;
                        } else {
                            foreach ($tableIndex->getColumns() as $columnName) {
                                if (!in_array($columnName, $localIndexes[$tableIndex->getName()])) {
                                    $changed = true;
                                    break;
                                }
                            }
                        }
                        if ($changed == true && !self::$_ignoreAlter) {
                            if ($tableIndex->getName() == 'PRIMARY') {
                                self::$_connection->dropPrimaryKey($tableName, $tableColumn->getSchemaName());
                                self::$_connection->addPrimaryKey($tableName, $tableColumn->getSchemaName(), $tableIndex);
                            } else {
                                self::$_connection->dropIndex($tableName, $tableColumn->getSchemaName(), $tableIndex->getName());
                                self::$_connection->addIndex($tableName, $tableColumn->getSchemaName(), $tableIndex);
                            }
                        }
                    }
                }
                foreach ($localIndexes as $indexName => $indexColumns) {
                    if (!isset($indexes[$indexName]) && !self::$_ignoreDrop) {
                        self::$_connection->dropIndex($tableName, null, $indexName);
                    }
                }
            }
        }

    }

    /**
     * Inserts data from a data migration file in a table
     *
     * @param string $tableName
     * @param string $fields
     */
    public function batchInsert($tableName, $fields)
    {
        $migrationData = self::$_migrationPath.'/'.$tableName.'.dat';
        if (file_exists($migrationData)) {
            self::$_connection->begin();
            self::$_connection->delete($tableName);
            $batchHandler = fopen($migrationData, 'r');
            while (($line = fgets($batchHandler)) !== false) {
                self::$_connection->insert($tableName, explode('|', rtrim($line)), $fields, false);
                unset($line);
            }
            fclose($batchHandler);
            self::$_connection->commit();
        }
    }

}
