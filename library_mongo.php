<?php
namespace library;
defined( 'AWESOME' ) OR exit ( 'Sorry! Invalid Access!' );

use Lib_Logger;

/**
 *
 * mongo
 *
 **/
class Lib_Mongo{

	/**
	 * mongo extension name
	 */
	const EXTENSION_NAME = 'mongodb';

	/**
	 * mongo is connect
	 * @var boolean
	 */
	private $_isconnect = false;

	/**
	 * mongo 实例对象
	 * @var object
	 */
	private $_mongo_obj = null;

	/**
	 * 当前连接的db
	 * @var null
	 */
	private $_database = null;

	/**
	 * mongo uri with password
	 * @var string
	 */
	private $_uri_tpl_withpwd = 'mongodb://username:password@host:port/database';

	/**
	 * mongo uri with no pwd
	 * @var string
	 */
	private $_uri_tpl_nopwd = 'mongodb://host:port/database';

	/**
	 * allow action
	 * @var array
	 */
	private $_allow_action = ['insert', 'update', 'find', 'delete'];

	/**
	 * just query find
	 * default query options
	 * @var array
	 */
	private $_default_query_options = [
										'projection' => ['_id' => 0], 
										'limit' => 1 
									];

	/**
	 * mongo uri
	 * @var null
	 */
	private $_mongo_uri = null;

	/**
	 * MongoDB\Driver\BulkWrite
	 * BulkWrite objects may only be executed once
	 * For insert, update, delete
	 * @var null
	 */
	private $_mongo_bulk = null;

	/**
	 * MongoDB\Driver\Query
	 * The query may then be executed with MongoDB\Driver\Manager::executeQuery()
	 * For query(select / find)
	 * @var null
	 */
	private $_mongo_query = null;

	/**
	 * 当前操作的集合collection 
	 * @var null
	 */
	private $_collection = null;

	/**
	 * database.collection
	 * @var string
	 */
	private $_db_collection = null;

	/**
	 * mongo result
	 * @var null
	 */
	private $_mongo_result = null;


	/**
	 * 构造函数
	 * @param array $conf_mongo 配置文件
	 */
	public function __construct($conf_mongo=array())
	{
		if(!extension_loaded(self::EXTENSION_NAME)){
			die('Please Install The Mongodb Extention!');
		}
		if(empty($conf_mongo) || !is_array($conf_mongo)){
			die('Wrong Format Befound For Mongodb Config!');
		}

		extract($conf_mongo);

		if(empty($host)||empty($port)||empty($database)){
			die('Invalid Mongodb Config！');
		}

		$find = array_keys($conf_mongo);

		//password
		if(empty($username)||empty($password)){
			$this->_mongo_uri = str_replace($find, compact($find), $this->_uri_tpl_nopwd);
		}else{
			$this->_mongo_uri = str_replace($find, compact($find), $this->_uri_tpl_withpwd);		
		}

		if(! $this->_mongo_obj){
			$this->_connect();
			$this->_database = $database;
		}
	}


	/**
	 * connect mongodb
	 * @return object 
	 */
	private function _connect()
	{
		if($this->_mongo_obj){
			return $this->_mongo_obj;
		}
		try{
			$this->_mongo_obj = new \MongoDB\Driver\Manager($this->_mongo_uri);
		}catch(Exception $e){
			$this->_errorlog( 'MongoDB Connect Failed', $e->getCode(), $e->getMessage() );
		}
	}


	/**
	 * db.collection
	 * @return string/false
	 */
	private function _make_db_collection()
	{
		if(empty($this->_database) || empty($this->_collection)){
			return false;
		}
		return sprintf('%s.%s', $this->_database, $this->_collection);
	}


	/**
	 * 执行操作
	 * DML  - modify
	 * 主要是抽出try-catch
	 * @param  string $action 操作类型
	 * @return mixed
	 */
	private function _do_dml_action($action='')
	{
		$action = trim($action);
		//检查操作
		if(empty($action) || !in_array($action, $this->_allow_action)){
			return false;
		}
		//检查mongo对象
		if(empty($this->_mongo_obj) || !is_object($this->_mongo_obj)){
			return false;
		}
		//检查BulkWrite对象
		if(empty($this->_mongo_bulk) || !is_object($this->_mongo_bulk)){
			return false;
		}
		//检查DB集合
		$this->_db_collection = $this->_make_db_collection();
		if(empty($this->_db_collection)){
			return false;
		}

		// do operation
		try {
			$writeConcern = new \MongoDB\Driver\WriteConcern(
														\MongoDB\Driver\WriteConcern::MAJORITY, 1000
													);
		    $this->_mongo_result = $this->_mongo_obj->executeBulkWrite(
											    						$this->_db_collection,
											    						$this->_mongo_bulk, 
											    						$writeConcern
											    					);
		} catch (\MongoDB\Driver\Exception\BulkWriteException $e) {

		    $error_result = $e->getWriteResult();

		    // Check if the write concern could not be fulfilled
		    if ($writeConcernError = $error_result->getWriteConcernError()) {
		    	$error_string = sprintf("%s (%d): %s",
		            $writeConcernError->getMessage(),
		            $writeConcernError->getCode(),
		            var_export($writeConcernError->getInfo(), true)
		        );
		        $this->_errorlog($action, 100, $eror_string);
		    }

		    // Check if any write operations did not complete at all
		    if($writeErrors = $error_result->getWriteErrors()){
			    $error_string = array();
			    foreach ($writeErrors as $wError) {
			    	$error_string[] = sprintf("Operation#%d: %s (%d)",
			            $wError->getIndex(),
			            $wError->getMessage(),
			            $wError->getCode()
			        );
			    }
			    $this->_errorlog($action, 111, json_encode($eror_string));
		    }

		} catch (\MongoDB\Driver\Exception\Exception $e) {
		    $error_string = sprintf("Other error: %s", $e->getMessage());
		    $this->_errorlog($action, 112, $eror_string);
		}
		//do result
		if(empty($this->_mongo_result) || !is_object($this->_mongo_result)){
			return false;
		}

		switch($action){
			case 'insert':
				return $this->_mongo_result->getInsertedCount();
				break;
			case 'update':
				return $this->_mongo_result->getModifiedCount();
			case 'delete':
				return $this->_mongo_result->getDeletedCount();				
				break;
			default:
				return false;
		}
	}


	/**
	 * insert
	 * 
	 * @param  string $collection 集合
	 * @param  array  $data       数据
	 * @return int/false 成功返回写入的记录条数
	 */
	public function insert($collection='', array $data=array())
	{
		if(! $this->_mongo_obj){
			return false;
		}
		$collection = trim($collection);
		if(empty($collection) || empty($data) || !is_array($data)){
			return false;
		}
		$this->_collection = $collection;
		$this->_mongo_bulk = new \MongoDB\Driver\BulkWrite();
		$this->_mongo_bulk->insert($data);

		return $this->_do_dml_action('insert');
	}


	/**
	 * mongodb中的update的形式是这样的：
	 * db.collectionName.update(query, obj, upsert, multi);
	 * 对于upsert(默认为false)：
	 * 		如果upsert=true，如果query找到了符合条件的行，则修改这些行，如果没有找到，则
	 * 追加一行符合query和obj的行。
	 *		如果upsert为false，找不到时，不追加。
	 * 对于multi(默认为false): 
	 * 		如果multi=true，则修改所有符合条件的行，否则只修改第一条符合条件的行。
	 * @param  string $collection 集合
	 * @param  array  $condition  更新的条件
	 * @param  array  $updata     更新值
	 * @return int/false 成功返回更新的记录条数
	 */
	public function update($collection='', array $condition=array(), array $updata=array())
	{
		if(! $this->_mongo_obj){
			return false;
		}
		$collection = trim($collection);
		if(empty($collection) || empty($condition) || !is_array($condition)){
			return false;
		}
		if(empty($updata) || !is_array($updata)){
			return false;
		}
		$this->_collection = $collection;
		$this->_mongo_bulk = new \MongoDB\Driver\BulkWrite();

		$this->_mongo_bulk->update(
		    $condition,
		    ['$set' => $updata],
		    ['multi' => true, 'upsert' => false] //upsert = true, getUpsertedCount
		);
		return $this->_do_dml_action('update');
	}


	/**
	 * deleteOne
	 * 删除符合条件的一条记录
	 * 
	 * @param  string $collection 集合
	 * @param  array  $condition  删除的条件
	 * @return int/false 成功返回删除的记录条数
	 */
	public function deleteOne($collection='', array $condition=array())
	{
		if(! $this->_mongo_obj){
			return false;
		}
		$collection = trim($collection);
		if(empty($collection) || empty($condition) || !is_array($condition)){
			return false;
		}
		$this->_collection = $collection;
		$this->_mongo_bulk = new \MongoDB\Driver\BulkWrite();

		// limit 为 1 时，删除第一条匹配数据
		$this->_mongo_bulk->delete($condition, ['limit' => 1]);
		return $this->_do_dml_action('delete');
	}


	/**
	 * deleteAll
	 * 删除符合条件的所有记录
	 * 
	 * @param  string $collection 集合
	 * @param  array  $condition  删除的条件
	 * @return int/false 成功返回删除的记录条数
	 */
	public function deleteAll($collection='', array $condition=array())
	{
		if(! $this->_mongo_obj){
			return false;
		}
		$collection = trim($collection);
		if(empty($collection) || empty($condition) || !is_array($condition)){
			return false;
		}
		$this->_collection = $collection;
		$this->_mongo_bulk = new \MongoDB\Driver\BulkWrite();

		// limit 为 0 时，删除所有匹配数据
		$this->_mongo_bulk->delete($condition, ['limit' => 0]);
		return $this->_do_dml_action('delete');
	}


	/**
	 * 执行查询操作
	 * DQL -query
	 * 
	 * @return mixed array/false
	 */
	private function _do_dql_action()
	{
		//检查mongo对象
		if(empty($this->_mongo_obj) || !is_object($this->_mongo_obj)){
			return false;
		}
		//检查DB集合
		$this->_db_collection = $this->_make_db_collection();
		if(empty($this->_db_collection)){
			return false;
		}
		$cursor = null;
		try {
			$cursor = $this->_mongo_obj->executeQuery($this->_db_collection, $this->_mongo_query);
		} catch (Exception $e) {
			
		}
		
		if(empty($cursor) || !is_object($cursor)){
			return false;
		}

		$ret_arr = null;
		foreach ($cursor as $document) {
    		$ret_arr[] = (array)$document;
		}
		if(empty($ret_arr)|!is_array($ret_arr)){
			return false;
		}

		//for limit 1  or all
		if(count($ret_arr)>1){
			$this->_mongo_result = (array)$ret_arr;
		}else{
			$this->_mongo_result = (array)$ret_arr[0];
		}
		return $this->_mongo_result;
	}


	/**
	 * Find One
	 * 
	 * @param  string $collection 集合 
	 * @param  array  $filter     筛选条件
	 * @param  array  $options    自定义查询参数
	 * @return array/false
	 */
	public function getOne($collection='', array $filter=array(), array $options=array())
	{
		if(! $this->_mongo_obj){
			return false;
		}
		$collection = trim($collection);
		if(empty($collection) || empty($filter) || !is_array($filter)){
			return false;
		}
		$this->_collection = $collection;

		if(empty($options)){
			$options = $this->_default_query_options;
		}

		$this->_mongo_query = new \MongoDB\Driver\Query($filter, $options);
		return $this->_do_dql_action();
	}


	/**
	 * find get all 
	 * @param  string $collection 集合 
	 * @param  array  $filter     筛选条件
	 * @param  array  $options    自定义查询参数
	 * @return array/false
	 */
	public function getAll($collection='', array $filter=array(), array $options=array())
	{
		if(! $this->_mongo_obj){
			return false;
		}
		$collection = trim($collection);
		if(empty($collection) || empty($filter) || !is_array($filter)){
			return false;
		}
		$this->_collection = $collection;

		if(empty($options)){
			$options = $this->_default_query_options;
		}
		$options['limit'] = 0; //A limit of 0 is equivalent to setting no limit

		$this->_mongo_query = new \MongoDB\Driver\Query($filter, $options);
		return $this->_do_dql_action();		
	}


	/**
	 * 获取当前连接的mongo uri信息
	 * The driver connects to the database server lazily, so Manager::getServers()
 	 * may initially return an empty array.
 	 * 
	 * @return array()
	 */
	public function get_servers()
	{
		if(!$this->_mongo_obj){
			return false;
		}
		$command = new \MongoDB\Driver\Command(['ping' => 1]);
		$this->_mongo_obj->executeCommand($this->_database, $command);

		$result = $this->_mongo_obj->getServers();
		if(!isset($result[0])){
			return false;
		}
		if(!is_object($result[0])){
			return false;
		}
		$ret_svr = array();
		$ret_svr['host'] = trim($result[0]->getHost());
		$ret_svr['port'] = trim($result[0]->getPort());
		$ret_svr['type'] = trim($result[0]->getType());
		$ret_svr['is_primary'] = trim($result[0]->isPrimary());
		$ret_svr['is_secondary'] = trim($result[0]->isSecondary());
		$ret_svr['is_arbiter'] = trim($result[0]->isArbiter());
		$ret_svr['is_hidden'] = trim($result[0]->isHidden());
		$ret_svr['is_passive'] = trim($result[0]->isPassive());
		$ret_svr['getTags'] = (array)$result[0]->getTags();
		$ret_svr['getLatency'] = trim($result[0]->getLatency());

		return $ret_svr;
	}


	/**
	 * destruct 
	 * unset var
	 */
	public function __destruct()
	{
		unset(
				$this->_mongo_obj,
				$this->_mongo_result,
				$this->_mongo_bulk,
				$this->_mongo_query
		);
	}


    /**
     * 错误日志记录
     * 
     * @param  string $errorString 错误类型
     * @param  string $code        错误码
     * @param  string $msg         错误信息
	 * @return True
	 */
	private function _errorlog( $errorString='', $code=0, $msg='' )
	{
		$error = array();
        $error['time']= date('Y-m-d H:i:s');
        $error['database'] = $this->_database;
        $error['db_collection'] = $this->_db_collection;
        $error['errorString'] = (string)$errorString;
        $error['code'] = (int)$code;    
        $error['msg'] = (string)$msg;

        //写错误日志，die
        return Lib_Logger::instance()->writeLog( 'error_mongo', json_encode($error), TRUE );
	}

}//end-class
