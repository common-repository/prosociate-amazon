<?php
/**
 * Base class for models
 *
 * @author Pavel Kulbakin <p.kulbakin@gmail.com>
 */
class PMLC_Model_Record extends PMLC_Model {
    /**
     * Initialize model
     * @param array[optional] $data Array of record data to initialize object with
     */
    public function __construct($data = array()) {
        parent::__construct();
        if (! is_array($data)) {
            throw new Exception("Array expected as paramenter for " . get_class($this) . "::" . __METHOD__);
        }
        $data and $this->set($data);
    }

    /**
     * @see PMLC_Model::getBy()
     * @return PMLC_Model_Record
     */
    public function getBy($field = NULL, $value = NULL) {
        if (is_null($field)) {
            throw new Exception("Field parameter is expected at " . get_class($this) . "::" . __METHOD__);
        }
        $sql = "SELECT * FROM $this->table WHERE " . $this->buildWhere($field, $value);
        $result = $this->wpdb->get_row($sql, ARRAY_A);
        if (is_array($result)) {
            foreach ($result as $k => $v) {
                if (is_serialized($v)) {
                    $result[$k] = unserialize($v);
                }
            }
            $this->exchangeArray($result);
        } else {
            $this->clear();
        }
        return $this;
    }


    /**
     * Magic method to resolved object-like request to record values in format $obj->%FIELD_NAME%
     * @param string $field
     * @return mixed
     */
    public function __get($field) {
        if ( ! $this->offsetExists($field)) {
            throw new Exception("Undefined field $field.");
        }
        return $this[$field];
    }
    /**
     * Magic method to assign values to record fields in format $obj->%FIELD_NAME = value
     * @param string $field
     * @param mixed $value
     */
    public function __set($field, $value) {
        $this[$field] = $value;
    }
    /**
     * Magic method to check wether some record fields are set
     * @param string $field
     * @return bool
     */
    public function __isset($field) {
        return $this->offsetExists($field);
    }
    /**
     * Magic method to unset record fields
     * @param string $field
     */
    public function __unset($field) {
        $this->offsetUnset($field);
    }

}