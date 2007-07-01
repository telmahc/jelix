<?php
/**
 * @package     jelix
 * @subpackage  dao
 * @author      Laurent Jouanneau
 * @contributor Loic Mathaud
 * @copyright   2005-2006 Laurent Jouanneau
 * @copyright   2007 Loic Mathaud
 * @link        http://www.jelix.org
 * @licence     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
 */

/**
 * Base class for all record classes generated by the dao compiler
 * @package  jelix
 * @subpackage dao
 */
abstract class jDaoRecordBase {

    const ERROR_REQUIRED=1;
    const ERROR_BAD_TYPE=2;
    const ERROR_BAD_FORMAT=3;
    const ERROR_MAXLENGTH = 4;
    const ERROR_MINLENGTH = 5;

    /**
     * informations on all properties
     * 
     * keys are property name, and values are an array like that :
     * <pre> array (
     *  'name' => 'name of property',
     *  'fieldName' => 'name of fieldname',
     *  'regExp' => NULL, // or the regular expression to test the value
     *  'required' => true/false, 
     *  'isPK' => true/false, //says if it is a primary key
     *  'isFK' => true/false, //says if it is a foreign key
     *  'datatype' => '', // type of data : string
     *  'table' => 'grp', // alias of the table the property is attached to
     *  'updatePattern' => '%s',
     *  'insertPattern' => '%s',
     *  'selectPattern' => '%s',
     *  'sequenceName' => '', // name of the sequence when type is autoincrement
     *  'maxlength' => NULL, // or a number
     *  'minlength' => NULL, // or a number
     *  'ofPrimaryTable' => true/false,
     *  'needsQuotes' => tree/false, // says if the value need to enclosed between quotes
     * ) </pre>
     * @var array 
     */
    protected $_properties = array();

    /**
     * list of id of primary key properties
     * @var array
     */
    protected $_pkFields = array();

    /**
     * @return array informations on all properties
     * @see jDaoRecordBase::$_properties
     */
    public function getProperties(){ return $this->_properties; }

    /**
     * check values in the properties of the record, according on the dao definition
     * @return array list of errors
     */
    public function check(){
        $errors=array();
        foreach($this->_properties as $prop=>$infos){
            $value = $this->$prop;

            // test required
            if($infos['required'] && $value === null && $infos['datatype'] != 'autoincrement' && $infos['datatype'] != 'bigautoincrement'){
                $errors[$prop][] = self::ERROR_REQUIRED;
                continue;
            }

            if($infos['datatype']=='varchar' || $infos['datatype']=='string'){
                if(!is_string($value) && $value !== null){
                    $errors[$prop][] = self::ERROR_BAD_TYPE;
                    continue;
                }
                // test regexp
                if ($infos['regExp'] !== null && preg_match ($infos['regExp'], $value) === 0){
                    $errors[$prop][] = self::ERROR_BAD_FORMAT;
                    continue;
                }

                //  test maxlength et minlength
                $len = iconv_strlen($value, $GLOBALS['gJConfig']->defaultCharset);
                if($infos['maxlength'] !== null && $len > intval($infos['maxlength'])){
                    $errors[$prop][] = self::ERROR_MAXLENGTH;
                }

                if($infos['minlength'] !== null && $len < intval($infos['minlength'])){
                    $errors[$prop][] = self::ERROR_MINLENGTH;
                }


            }elseif( in_array($infos['datatype'], array('int','integer','numeric', 'double', 'float'))) {
                // test datatype
                if($value !== null && !is_numeric($value)){
                    $errors[$prop][] = self::ERROR_BAD_TYPE;
                    continue;
                }
            }elseif( in_array($infos['datatype'], array('datetime', 'time','varchardate', 'date'))) {
                if (jLocale::timestampToDate ($value) === false){
                    $errors[$prop][] = self::ERROR_BAD_FORMAT;
                    continue;
                }
            }
        }
        return $errors;
    }

    /**
     * set values on the properties which correspond to the primary
     * key of the record
     * This method accept a single or many values as parameter
     */
    public function setPk(){
        $args=func_get_args();
        if(count($args)==1 && is_array($args[0])){
            $args=$args[0];
        }
        if(count($args) == 0 || count($args) != count($this->_pkFields) ) 
            throw new jException('jelix~dao.error.keys.missing');

        foreach($this->_pkFields as $k=>$prop){
           $this->$prop = $args[$k];
        }
        return true;
    }
    
    /**
     * return the value of fields corresponding to the primary key
     * @return mixed  the value or an array of values if there is several  pk
     * @since 1.0beta3
     */
    public function getPk(){
        if(count($this->_pkFields) == 1){
            return $this->{$this->_pkFields[0]};
        }else{
            $list = array();
            foreach($this->_pkFields as $k=>$prop){
                $list[] = $this->$prop;
            }
            return $list;
        }
    }
}




/**
 * base class for all factory classes generated by the dao compiler
 * @package  jelix
 * @subpackage dao
 */
abstract class jDaoFactoryBase  {
    /**
     * informations on tables
     *
     * Keys of elements are the alias of the table. values are arrays like that :
     * <pre> array (
     *   'name' => ' the table alias',
     *   'realname' => 'the real name of the table',
     *   'pk' => array ( list of primary keys name ),
     *   'fields' => array ( list of property name attached to this table )
     * )
     * </pre>
     * @var array
     */
    protected $_tables;
    /**
     * the id of the primary table
     * @var string
     */
    protected $_primaryTable;
    /**
     * the database connector
     * @var jDbConnection
     */
    protected $_conn;
    /**
     * the select clause you can reuse for a specific SELECT query
     * @var string
     */
    protected $_selectClause;
    /**
     * the from clause you can reuse for a specific SELECT query
     * @var string
     */
    protected $_fromClause;
    /**
     * the where clause you can reuse for a specific SELECT query
     * @var string
     */
    protected $_whereClause;
    /**
     * the class name of a dao record for this dao factory
     * @var string
     */
    protected $_DaoRecordClassName;
    /**
     * list of id of primary properties
     * @var array
     */
    protected $_pkFields;

    /**
     * @param jDbConnection $conn the database connection
     */
    function  __construct($conn){
        $this->_conn = $conn;
    }

    /**
     * return all records
     * @return jDbResultSet
     */
    public function findAll(){
        $rs =  $this->_conn->query ($this->_selectClause.$this->_fromClause.$this->_whereClause);
        $rs->setFetchMode(8,$this->_DaoRecordClassName);
        return $rs;
    }
    /**
     * return the number of all records
     * @return int the count
     */
    public function countAll(){
        $query = 'SELECT COUNT(*) as c '.$this->_fromClause.$this->_whereClause;
        $rs  =  $this->_conn->query ($query);
        $res =  $rs->fetch ();
        return intval($res->c);
    }

    /**
     * return the record corresponding to the given key
     * @param string  one or more primary key
     * @return jDaoRecordBase
     */
    public function get(){
        $args=func_get_args();
        if(count($args)==1 && is_array($args[0])){
            $args=$args[0];
        }
        $keys = array_combine($this->_pkFields,$args );

        if($keys === false){
            throw new jException('jelix~dao.error.keys.missing');
        }

        $q = $this->_selectClause.$this->_fromClause.$this->_whereClause;
        $q .= $this->_getPkWhereClauseForSelect($keys);

        $rs  =  $this->_conn->query ($q);
        $rs->setFetchMode(8,$this->_DaoRecordClassName);
        $record =  $rs->fetch ();
        return $record;
    }

    /**
     * delete a record corresponding to the given key
     * @param string  one or more primary key
     * @return int the number of deleted record
     */
    public function delete(){
        $args=func_get_args();
        if(count($args)==1 && is_array($args[0])){
            $args=$args[0];
        }
        $keys = array_combine($this->_pkFields, $args);
        if($keys === false){
            throw new jException('jelix~dao.error.keys.missing');
        }
        $q = 'DELETE FROM '.$this->_tables[$this->_primaryTable]['realname'].' ';
        $q.= $this->_getPkWhereClauseForNonSelect($keys);
        return $this->_conn->exec ($q);
    }

    /**
     * save a new record into the database
     * if the dao record has an autoincrement key, its corresponding property is updated
     * @param jDaoRecordBase $record the record to save
     */
    abstract public function insert ($record);

    /**
     * save a modified record into the database
     * @param jDaoRecordBase $record the record to save
     */
    abstract public function update ($record);


    /**
     * return all record corresponding to the conditions stored into the
     * jDaoConditions object.
     * you can limit the number of results by given an offset and a count
     * @param jDaoConditions $searchcond
     * @param int $limitOffset 
     * @param int $limitCount 
     * @return jDbResultSet
     */
    public function findBy ($searchcond, $limitOffset=0, $limitCount=0){
        $query = $this->_selectClause.$this->_fromClause.$this->_whereClause;
        if (!$searchcond->isEmpty ()){
            $query .= ($this->_whereClause !='' ? ' AND ' : ' WHERE ');
            $query .= $this->_createConditionsClause($searchcond);
        }

        if($limitCount != 0){
            $rs  =  $this->_conn->limitQuery ($query, $limitOffset, $limitCount);
        }else{
            $rs  =  $this->_conn->query ($query);
        }
        $rs->setFetchMode(8,$this->_DaoRecordClassName);
        return $rs;
    }

    /**
     * return the number of records corresponding to the conditions stored into the
     * jDaoConditions object.
     * @author Loic Mathaud
     * @copyright 2007 Loic Mathaud
     * @since 1.0b2
     * @param jDaoConditions $searchcond
     * @return int the count
     */
    public function countBy($searchcond) {
        $query = 'SELECT COUNT(*) as c '.$this->_fromClause.$this->_whereClause;
        if (!$searchcond->isEmpty ()){
            $query .= ($this->_whereClause !='' ? ' AND ' : ' WHERE ');
            $query .= $this->_createConditionsClause($searchcond);
        }
        $rs  =  $this->_conn->query ($query);
        $res =  $rs->fetch();
        return intval($res->c);
    }
    
    /**
     * create a WHERE clause with conditions on primary keys with given value. This method
     * should be used for SELECT queries. You haven't to escape values.
     *
     * @param array $pk  associated array : keys = primary key name, values : value of a primary key
     * @return string a 'where' clause (WHERE mypk = 'myvalue' ...)
     */
    abstract protected function _getPkWhereClauseForSelect($pk);

    /**
     * create a WHERE clause with conditions on primary keys with given value. This method
     * should be used for DELETE and UPDATE queries.
     * @param array $pk  associated array : keys = primary key name, values : value of a primary key
     * @return string a 'where' clause (WHERE mypk = 'myvalue' ...)
     */
    abstract protected function _getPkWhereClauseForNonSelect($pk);

    /**
    * @internal
    */
    protected function _createConditionsClause($daocond){

        $c = $this->_DaoRecordClassName;
        $rec= new $c();
        $fields = $rec->getProperties();

        $sql = $this->_generateCondition ($daocond->condition, $fields, true);

        $order = array ();
        foreach ($daocond->order as $name => $way){
            if (isset($fields[$name])){
                $order[] = $name.' '.$way;
            }
        }
        if(count ($order) > 0){
            if(trim($sql) =='') {
                $sql.= ' 1=1 ';
            }
            $sql.=' ORDER BY '.implode (', ', $order);
        }
        return $sql;
    }

    /**
     * @internal it don't support isExpr property of a condition because of security issue (SQL injection)
     * because the value could be provided by a form, it is escaped in any case
     */
    protected function _generateCondition($condition, &$fields, $principal=true){
        $r = ' ';
        $notfirst = false;
        foreach ($condition->conditions as $cond){
            if ($notfirst){
                $r .= ' '.$condition->glueOp.' ';
            }else
                $notfirst = true;

            $prop=$fields[$cond['field_id']];

            $prefixNoCondition = $this->_tables[$prop['table']]['name'].'.'.$prop['fieldName'];
            $prefix=$prefixNoCondition.' '.$cond['operator'].' '; // ' ' pour les like..

            if (!is_array ($cond['value'])){
                $value = $this->_prepareValue($cond['value'],$prop['datatype']);
                if ($value === 'NULL'){
                    if($cond['operator'] == '='){
                        $r .= $prefixNoCondition.' IS NULL';
                    }else{
                        $r .= $prefixNoCondition.' IS NOT NULL';
                    }
                } else {
                    $r .= $prefix.$value;
                }
            }else{
                $r .= ' ( ';
                $firstCV = true;
                foreach ($cond['value'] as $conditionValue){
                    if (!$firstCV){
                        $r .= ' or ';
                    }
                    $value = $this->_prepareValue($conditionValue,$prop['datatype']);
                    if ($value === 'NULL'){
                        if($cond['operator'] == '='){
                            $r .= $prefixNoCondition.' IS NULL';
                        }else{
                            $r .= $prefixNoCondition.' IS NOT NULL';
                        }
                    }else{
                        $r .= $prefix.$value;
                    }
                    $firstCV = false;
                }
                $r .= ' ) ';
            }
        }
        //sub conditions
        foreach ($condition->group as $conditionDetail){
            if ($notfirst){
                $r .= ' '.$condition->glueOp.' ';
            }else{
                $notfirst=true;
            }
            $r .= $this->_generateCondition($conditionDetail, $fields, false);
        }

        //adds parenthesis around the sql if needed (non empty)
        if (strlen (trim ($r)) > 0 && !$principal){
            $r = '('.$r.')';
        }
        return $r;
    }
    /**
     * prepare the value ready to be used in a dynamic evaluation
     */
    protected function _prepareValue($value, $fieldType){
        switch(strtolower($fieldType)){
            case 'int':
            case 'integer':
            case 'autoincrement':
                $value = $value === null ? 'NULL' : intval($value);
                break;
            case 'double':
            case 'float':
                $value = $value === null ? 'NULL' : doubleval($value);
                break;
            case 'numeric'://usefull for bigint and stuff
            case 'bigautoincrement':
                if (is_numeric ($value)){
                    //was numeric, we can sends it as is
                    // no cast with intval else overflow
                    return $value === null ? 'NULL' : $value;
                }else{
                    //not a numeric, nevermind, casting it
                    return $value === null ? 'NULL' : intval ($value);
                }
                break;
            default:
                $value = $this->_conn->quote ($value);
        }
        return $value;
    }

}
?>
