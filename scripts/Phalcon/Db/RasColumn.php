<?php

namespace Phalcon\Db;

use \Phalcon\Db\Column;


class RasColumn extends Column{

    protected $_defaultValue;
    protected $_values = [];

    const TYPE_ENUM = 32;
    const TYPE_TIME = 33;

    protected $_columnName;
    protected $_schemaName;
    protected $_type;
    protected $_isNumeric;
    protected $_size;
    protected $_scale;
    protected $_unsigned;
    protected $_notNull;
    protected $_primary;
    protected $_autoIncrement;
    protected $_first;
    protected $_after;
    protected $_bindType;

    public function __construct($columnName, $definition)
    {
        if ($definition['type'] === static::TYPE_ENUM) {
            $this->_values = $definition['values'];
        }
        if (isset($definition['default'])) {
            $this->_defaultValue = $definition['default'];
        }
        if ($definition['type'] >= 32) { // additional fields
            $this->fillColumn($columnName, $definition);
        } else {
            parent::__construct($columnName, $definition);
        }
    }

    protected function fillColumn($columnName, $definition)
    {
        $this->_columnName    = $columnName;
        $this->_type          = isset($definition['type']) ? $definition['type'] : null;;
        $this->_size          = isset($definition['size']) ? $definition['size'] : 0;
        $this->_unsigned      = isset($definition['unsigned']) ? $definition['unsigned'] : false;
        $this->_notNull       = isset($definition['notNull']) ? $definition['notNull'] : false;
        $this->_autoIncrement = isset($definition['autoIncrement']) ? $definition['autoIncrement'] : false;
        $this->_first         = isset($definition['first']) ? $definition['first'] : false;
    }

    public function getDefault()
    {
        return $this->_defaultValue;
    }

    public function getColumnValues()
    {
        return $this->_values;
    }

} 