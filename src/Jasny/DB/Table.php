<?php
/**
 * Jasny DB - A DB layer for the masses.
 * 
 * PHP version 5.3+
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db/master/LICENSE MIT
 * @link    https://jasny.github.io/db
 */
/** */
namespace Jasny\DB;

/**
 * Default table gateways.
 */
abstract class Table
{
    /** Option to skip check if class exists on Table::getClass() */
    const SKIP_CLASS_EXISTS = 1;


    /**
     * Default database connection
     * @var Connection
     */
    public static $defaultConnection;
    
    /**
     * Created table gateways
     * @var array
     */
    private static $tables = array();
    
    
    /**
     * @var DB
     */
    protected $db;

    /**
     * @var string
     */
    protected $name;

    
    /**
     * Get the default database (with respect of the namespace).
     * 
     * @return Connection
     */
    public static function getDefaultConnection()
    {
        $class = get_called_class();
        
        while ($class != __CLASS__) {
            $ns = preg_replace('/[^\\\\]+$/', '', $class);
            if (class_exists($ns . 'Connection') && is_a($ns . 'Connection', 'Jasny\DB\Connection', true)) {
                return call_user_func(array($ns . 'Connection', 'conn'));
            }
            
            $class = get_parent_class($class);
        };
        
        if (!isset(self::$defaultConnection)) throw new \Exception("Default connection not set, please connect to a DB.");
        return self::$defaultConnection;
    }
    
    /**
     * Get the default Table class (with respect of the namespace).
     * 
     * @param string     $base  The classname we're looking for
     * @param Connection $db
     * @return string
     */
    public static function getDefaultClass($base, $db=null)
    {
        $class = get_class($db ?: static::getDefaultConnection());
        
        do {
            $ns = preg_replace('/[^\\\\]+$/', '', $class);
            if (class_exists($ns . $base) && is_a($ns . $base, __NAMESPACE__ . '\\' . $base, true)) return $ns . $base;
            
            $class = get_parent_class($class);
        } while ($class);
        
        return null;
    }
    
    
    /**
     * Get a table gateway.
     * 
     * @param string $name  Table name or record class name
     * @param DB     $db    Database connection
     * @return Table
     */
    public static function factory($name, Connection $db=null)
    {
        $name = static::uncamelcase(preg_replace('/^.+\\\\/', '', $name)); // Remove namespace and un-camelcase to get DB table name from record class
        
        if (!isset($db)) $db = self::getDefaultConnection();
        
        $class = ltrim($db->getModelNamespace() . '\\', '\\') . static::camelcase($name) . 'Table';
        if (!class_exists($class)) $class = static::getDefaultClass('Table', $db); // Use this standard table gateway if no specific gateway exists.
        if (!isset($class)) trigger_error("Table gateways aren't supported for " . get_class($db), E_USER_ERROR);

        if (isset(self::$tables[spl_object_hash($db)][$name])) { // Return cached gateway, only if the modelNamespace hasn't changed.
            $table = self::$tables[spl_object_hash($db)][$name];
            if (get_class($table) == $class) return $table;
        }
        
        $table = new $class($db); // Create a new table
        $table->name = $name;
        
        self::$tables[spl_object_hash($db)][$name] = $table;
        
        return $table;
    }
    
    
    /**
     * Class constructor.
     * Protected because the factory method should be used.
     * 
     * @param Connection $db    Database connection
     */
    protected function __construct(Connection $db=null)
    {
        $this->db = $db ?: self::getDefaultConnection();
    }
    
    /**
     * Return DB connection
     * 
     * @return Connection
     */
    public function getDB()
    {
        return $this->db;
    }

    /**
     * Get database name
     * 
     * @return string
     */
    public function getName()
    {
        if (!isset($this->name)) $this->name = static::uncamelcase(preg_replace('/^.+\\\\|Table$/i', '', get_class($this)));
        return $this->name;
    }
    
    /**
     * Return record class name
     * 
     * @param int $options
     * @return string
     */
    public function getClass($options=0)
    {
        $class = ltrim($this->getDB()->getModelNamespace() . '\\', '\\') . static::camelcase($this->getName());
        
        if (($options & self::SKIP_CLASS_EXISTS) || (class_exists($class) && is_a($class, __NAMESPACE__ . '\Record', true))) return $class; // Record class for this table exists
        return self::getDefaultClass('Record', $this->getDB()) ?: __NAMESPACE__ . '\Record'; // Use default Record class
    }
    
    
    /**
     * Get all the default values for this table.
     * 
     * @return array
     */
    abstract public function getDefaults();
    
    /**
     * Get the php type for each field of this table.
     * 
     * @return array
     */
    abstract public function getFieldTypes();
    
    /**
     * Get the property (or properties) to uniquely identifies a record.
     * 
     * @return string|array
     */
    abstract public function getIdentifier();
    
    
    /**
     * Fetch all records of the table.
     * 
     * @return array
     */
    abstract public function fetchAll();
    
    /**
     * Load a record from the DB
     * 
     * @param int|array $id  ID or filter
     * @return Record
     */
    abstract public function fetch($id);
    
    /**
     * Save the record to the DB.
     * 
     * @param Record|array $record  Record or array with values
     * @return Record
     */
    abstract public function save($record);

    
    /**
     * Turn a string using underscores in a camelcase string.
     * 
     * @param string $string
     * @return string
     */
    protected static function camelcase($string)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
    
    /**
     * Turn a camelcase string in a string using underscores.
     * 
     * @param string $string
     * @return string
     */
    protected static function uncamelcase($string)
    {
        return strtolower(preg_replace('/(?<=[a-z])([A-Z])(?![A-Z])/', '_$1', $string));
    }
    
    
    /**
     * Cast table to table name.
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
    
    
    /**
     * Check if a table exists for the default connection.
     * 
     * @param string $name
     * @return boolean
     */
    public static function exists($name)
    {
        return (bool)static::getDefaultConnection()->tableExists($name);
    }

    /**
     * Automatically create classes for table gateways and records
     */
    public static function autoGenerateModel($class)
    {
        if (preg_replace('/\\[^\\\\]+$/', '', $class) != self::getDefaultConnection()->getModelNamespace()) return;
        
        $name = static::uncamelcase(preg_replace('/^.+\\\\|Table$/i', '', $class));
        if (!static::exists($name)) return;
        
        $base = self::getDefaultClass(substr($class, -5) == 'Table' ? 'Table' : 'Record');
        eval("class $class extends $base {}");
    }

    
    /**
     * Cast the value to a type
     * 
     * @param string $value
     * @param string $type
     * @return mixed
     */
    public static function castValue($value, $type)
    {
        if (!is_string($value) || $type == 'string') return;
        
        switch ($type) {
            case 'bool': case 'boolean':
            case 'int':  case 'integer':
            case 'float':
                if (isset($type)) settype($value, $type);
                break;
                
            case 'array':
                $value = explode(',', $value);
                break;
            
            default:
                if (!class_exists($type)) throw new \Exception("Invalid type '$type'");
                $value = new $type($value);
                break;
        }
        
        return $value;
    }
    
    /**
     * Get the PHP types for values in the result.
     * Other types should be cast.
     * 
     * @return array
     */
    public static function resultValueTypes()
    {
        return array();
    }
}