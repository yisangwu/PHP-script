<?php
	
/**
 * PHP 数据对象 （PDO） 扩展为PHP访问数据库定义了一个轻量级的一致接口。
 * PDO 提供了一个数据访问抽象层，这意味着，不管使用哪种数据库，都可以用相同的函数（方法）来查询和获取数据。
 * PDO随PHP5.1发行，在PHP5.0的PECL扩展中也可以使用，无法运行于之前的PHP版本。
 */
class Library_Pdomysql
{

	private $_driver = 'mysql';     //数据库类型
	private $_user;
	private $_password;
	private $_dsn;
	private $_obj_pdo;
	private $_result = FALSE;

	/**
	 * 构造函数
	 * 
	 * @param array $dbconf 数据库配置
	 */
	public function __construct($dbconf=array())
	{
		if( !extension_loaded('pdo_mysql')){
			die('Please Install The pdo_mysql Extention!');
		}
		if(empty($dbconf)||!is_array($dbconf)){
			die( 'Wrong db Config !' );
		}
		$host            = empty( $dbconf['host'] ) ? 'localhost' : trim($dbconf['host']);
		$port            = empty( $dbconf['port'] ) ? '3306' : trim($dbconf['port']);		
		$this->_user     = empty( $dbconf['user'] ) ? 'root' : trim($dbconf['user']);
		$this->_password = empty( $dbconf['pwd'] ) ? '123456' : trim($dbconf['pwd']);
		$db              = empty( $dbconf['db'] ) ? 'mysql' : trim($dbconf['db']);
		$this->_dsn="{$this->_driver}:host={$host};port={$port};dbname={$db}";
		// 创建pdo连接
		// 默认这个不是长连接，
		// 如果需要数据库长连接，需要最后加一个参数：array(PDO::ATTR_PERSISTENT => true)
		try {
		    $this->_obj_pdo = new PDO($this->_dsn, $this->_user, $this->_password); //初始化一个PDO对象
		    $this->_obj_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //抛出一个 PDOException 异常类
		} catch (PDOException $e) {
		    die ("Connect Mysql Error!: " . $e->getMessage() . "<br/>");
		}
		//设置默认客户端字符集
		$this->query("set names utf8mb4");
	}

	/**
	 * 执行query
	 * PDO::query — 执行 SQL 语句，返回PDOStatement对象,可以理解为结果集(PHP 5 >= 5.1.0, PECL pdo >= 0.2.0)
	 * 如果成功，PDO::query()返回PDOStatement对象，如果失败返回 FALSE 。
	 * 
	 * @param  string $sql 执行语句
	 * @return mixed
	 */
	public function query($sql='')
	{
		$sql = trim($sql);
		if( empty($sql) ){
			return FALSE;
		}
		try{
			$this->_result = $this->_obj_pdo->query($sql);
		}catch (PDOException $e) {
			die ("query Error!: " . $e->getMessage() . "<br/>");
		}
		if(!$this->_result){
			return FALSE;
		}
		return $this->_result;
	}

	/**
	 * 从结果集中获取下一行(PHP 5 >= 5.1.0, PECL pdo >= 0.1.0)
	 * 
	 * PDO::FETCH_ASSOC：返回一个索引为结果集列名的数组
	 * PDO::FETCH_BOTH（默认）：返回一个索引为结果集列名和以0开始的列号的数组
	 * 
	 * @param  string $sql  执行语句
	 * @param  const  $mode PDO::FETCH_* 系列常量
	 * @return 成功时返回的值依赖于提取类型。在所有情况下，失败都返回 FALSE 。
	 */
	public function getOne($sql='', $mode=PDO::FETCH_ASSOC)
	{
		$sql = trim($sql);
		if( empty($sql) ){
			return FALSE;
		}
		$this->_result = $this->query( $sql );
		if( !$this->_result || !is_object($this->_result) ){
			return array();
		}
		return $this->_result->fetch($mode);
	}

	/**
	 * 返回一个包含结果集中所有行的数组(PHP 5 >= 5.1.0, PECL pdo >= 0.1.0)
	 * 
	 * @param  string $sql 执行语句
	 * @param  const  $mode PDO::FETCH_* 系列常量
	 * @return 此数组的每一行要么是一个列值的数组，要么是属性对应每个列名的一个对象。
	 */
	public function getAll($sql='', $mode=PDO::FETCH_ASSOC)
	{
		$sql = trim($sql);
		if( empty($sql) ){
			return FALSE;
		}
		$this->_result = $this->query( $sql );
		if( !$this->_result || !is_object($this->_result) ){
			return array();
		}
		return $this->_result->fetchAll($mode);
	}

	/**
	 * 如果没有为参数 name 指定序列名称，PDO::lastInsertId() 则返回一个表示最后插入数据库那一行的行ID的字符串。
	 * 如果为参数 name 指定了序列名称，PDO::lastInsertId() 则返回一个表示从指定序列对象取回最后的值的字符串。
	 * 如果当前 PDO 驱动不支持此功能，则 PDO::lastInsertId() 触发一个 IM001 SQLSTATE 。
	 * 
	 * PDO::lastInsertId — 返回最后插入行的ID或序列值(PHP 5 >= 5.1.0, PECL pdo >= 0.1.0)
	 * @return boolean
	 */
	public function insertId()
	{
		return (int)$this->_obj_pdo->lastInsertId();
	}

	/**
	 * 执行更新操作
	 * 返回影响的行数
	 *
	 * PDO::exec() 返回受修改或删除 SQL 语句影响的行数。
	 * @param  string $sql 执行语句
	 * @return int
	 */
	public function update($sql='')
	{
		$sql = trim($sql);
		if( empty($sql) ){
			return FALSE;
		}
		return (int)$this->_obj_pdo->exec( $sql );
	}

	/**
	 * 执行删除操作
	 * 返回影响的行数
	 *
	 * PDO::exec() 返回受修改或删除 SQL 语句影响的行数。
	 * @param  string $sql 执行语句
	 * @return int
	 */
	public function delete($sql='')
	{
		$sql = trim($sql);
		if( empty($sql) ){
			return FALSE;
		}
		return (int)$this->_obj_pdo->exec( $sql );
	}


	/**
	 * 析构函数
	 * 主要用来释放结果集和关闭数据库连接
	 */
	public function __destruct()
	{
		unset($this->_obj_pdo);
		unset($this->_result);
	}

}//end-class