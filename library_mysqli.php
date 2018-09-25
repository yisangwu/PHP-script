<?php
namespace library;
defined( 'AWESOME' ) OR exit ( 'Sorry! Invalid Access!' );

use library\Lib_Logger;

/**
 * PHP MySQLi = PHP MySQL Improved!
 * MySQLi 函数允许您访问 MySQL 数据库服务器。
 * 注释：MySQLi 扩展被设计用于 MySQL 4.1.13 版本或更新的版本。
 *
 * @package		Library
 * @subpackage	Lib_Mysqli
 * @author 		yisangwu
 * 
 */

class Lib_Mysqli{

	private $_mysqli;	//mysqli实例对象
	private $_result;	//mysqli结果集

	/**
	 * 构造函数：主要用来返回一个mysqli对象  
	 * @param array $dbconfig 数据库配置
	 */
	public function __construct( $dbarr ){
        if( !extension_loaded('mysqli') ){ //检查是否安装了mysqli扩展
            die( 'Please Install The Mysqli Extention!' );
        }
		if( empty($dbarr)||!is_array($dbarr) ){
			die( 'Wrong Mysql Config !' );
		}

		$dbarr['host'] = empty( $dbarr['host'] )?'localhost':$dbarr['host'];
		$dbarr['user'] = empty( $dbarr['user'] )?'root':$dbarr['user'];
		$dbarr['pwd']  = empty( $dbarr['pwd'] )?'123456':$dbarr['pwd'];
		$dbarr['db']   = empty( $dbarr['db'] )?'mysql':$dbarr['db'];
		$dbarr['port'] = empty( $dbarr['port'] )?'3306':$dbarr['port'];

		$this->connect( $dbarr );
	}

	/**
	 * 实例化mysqli
	 * 
	 * @param  array $mysqlconf mysql的配置
	 * @return mixed/object
	 */
	protected function connect( $mysqlconf ){
		$this->_mysqli = new \Mysqli( $mysqlconf['host'], $mysqlconf['user'], $mysqlconf['pwd'], $mysqlconf['db'], $mysqlconf['port'] );
		if( $this->_mysqli->connect_errno ) {
            die( 'Connect Error: ' .$this->_mysqli->connect_errno.' -- '. $this->_mysqli->connect_error );
        } else {
        	//设置默认客户端字符集
            $this->_mysqli->set_charset( "utf8mb4" ) OR $this->_errorlog( 'set_charset' );
        }
        return TRUE;
	}

	/**
	 * 转义在 SQL 语句中使用的字符串中的特殊字符。
	 * @param  string $escapestring 要转义的字符串
	 * @return string 转义后的字符串
	 */
	public function escapeString( $escapestring ){
		return $this->_mysqli->real_escape_string( strip_tags($escapestring) );
	}

 	/**
     * 检查数据库连接,是否有效，无效则重新建立
     */
    protected function checkConnection(){
        if ( !$this->_mysqli->ping() ){
            $this->_mysqli->close();
            return $this->connect();
        }
        return TRUE;
    }

	/**
	 * 执行某个针对数据库的查询
	 * @param  string $sql 查询字符串
	 * @return 针对成功的 SELECT、SHOW、DESCRIBE 或 EXPLAIN 查询，将返回一个 mysqli_result 对象。
	 * 针对其他成功的查询，将返回 TRUE。如果失败，则返回 FALSE。
	 *
	 * mysql_query执行后检测返回值
	 * 如果mysql_query返回失败，检测错误码发现为2006/2013（这2个错误表示连接失败），再执行一次mysql_connect
	 * 执行mysql_connect后，重新执行mysql_query，这时必然会成功，因为已经重新建立了连接
	 * 如果mysql_query返回成功，那么连接是有效的，这是一次正常的调用
	 */
	public function query( $sql ){
		if( empty($sql) ){
			return FALSE;
		}
		$this->_result = FALSE;
		for( $i=0; $i<2; $i++ ){
			$this->_result = $this->_mysqli->query( $sql );
			if( $this->_result===FALSE ){
				//检测错误码发现为2006/2013（这2个错误表示连接失败）
				if( $this->_mysqli->errno==2006 || $this->_mysqli->errno==2013 ){
					if( $this->checkConnection() ){
						continue;
					}
				}
				$this->_errorlog( 'query', $sql );
			}
			break;
		}

		if( !$this->_result ){
			$this->_errorlog( 'query', $sql );
		}
		//$this->_result = $this->_mysqli->query( $sql ) OR $this->_errorlog( 'query', $sql );
		return $this->_result;
	}

	/**
	 * 返回最后一个查询中自动生成的 ID（通过 AUTO_INCREMENT 生成）
	 * 返回一个在最后一个查询中自动生成的带有 AUTO_INCREMENT 字段值的整数。
	 * 如果数字 > 最大整数值，它将返回一个字符串。
	 * 如果没有更新或没有 AUTO_INCREMENT 字段，将返回 0。
	 * @return int / string
	 */
	public function insertId(){
		return $this->_mysqli->insert_id;
	}

	/**
	 * 返回前一次 MySQL 操作（SELECT、INSERT、UPDATE、REPLACE、DELETE）所影响的记录行数
	 * 一个 > 0 的整数表示所影响的记录行数。
	 * 0 表示没有受影响的记录。-1 表示查询返回错误。
	 * @return int
	 */
	public function affectedRows(){
		return $this->_mysqli->affected_rows;
	}

	/**
	 * 取一行记录
	 * 从结果集中取得一行作为关联数组，或数字数组，或二者兼有。
	 * @param  string $sql        查询语句
	 * @param  const  $resulttype 默认为MYSQLI_ASSOC，可以是 MYSQLI_NUM 或 MYSQLI_BOTH
	 * @return array
	 */
	public function getOne( $sql, $resulttype=MYSQLI_ASSOC ){
		$this->_result = $this->query( $sql );
		if( !$this->_result || !is_object($this->_result) ){
			return array();
		}
		return $this->_result->fetch_array( $resulttype );
	}

	/**
	 * 取所有行记录
	 * 从结果集中取得所有行作为关联数组，或数字数组，或二者兼有。
	 * @param  string $sql        查询语句
	 * @param  const  $resulttype 默认为MYSQLI_ASSOC，可以是 MYSQLI_NUM 或 MYSQLI_BOTH
	 * @return array
	 */
	public function getAll( $sql, $resulttype=MYSQLI_ASSOC ){
		$this->_result = $this->query( $sql );
		if( !$this->_result || !is_object($this->_result) ){
			return array();
		}
		return $this->_result->fetch_all( $resulttype );
	}

	/**
	 * 获取结果集中行的数量
	 * 没有结果集 或者 结果集为空 则返回 0 
	 * @return int
	 */
	public function numRows(){
		if( !$this->_result || !is_object($this->_result) ){
			return 0;
		}
		return $this->_result->num_rows;
	}

	/**
	 * 获取结果集中字段（列）的数量
	 * 没有结果集 或者 结果集为空 则返回 0 
	 * @return int
	 */
	public function numFields(){
		if( !$this->_result || !is_object($this->_result) ){
			return 0;
		}
		return $this->_result->field_count;		
	}

	/**
	 * 返回字符集对象。
	 * @return object 返回带有下列属性的字符集对象：
	 * charset - 字符集名称
	 * collation - 排序规则名称
	 * dir - 被获取的目录字符集或者 ""
	 * min_length - 以字节计的最小字符长度
	 * max_length - 以字节计的最大字符长度
	 * number - 内部字符集数
	 * state - 字符集状态
	 */
	public function getCharset(){
		return $this->_mysqli->get_charset();
	}

	/**
	 * 整型的Mysql客户端版本信息
	 * @return int
	 */
	public function getClientVersion(){
		return $this->_mysqli->client_version;
	}

	/**
	 * 字符串类型的Mysql客户端版本信息
	 * @return string 
	 */
	public function getClientInfo(){
		return $this->_mysqli->client_info;
	}

	/**
	 * 获取整型的Mysql服务端版本信息
	 * main_version * 10000 + minor_version * 100 + sub_version (例如： 版本 4.1.0 是 40100).
	 * @return int  返回MySQL服务器的版本
	 */
	public function getServerVersion(){
		return $this->_mysqli->server_version;
	}

	/**
	 * 获取字符串的Mysql服务端版本信息
	 * @return string ，返回MySQL服务器的版本号
	 */
	public function getServerInfo(){
		return $this->_mysqli->server_info;
	}

	/**
	 * 服务器主机名和连接类型
	 * @return string 
	 */
	public function getHostInfo(){
		return $this->_mysqli->host_info;
	}

	/**
	 * 析构函数
	 * 主要用来释放结果集和关闭数据库连接
	 */
	public function __destruct(){
		if( is_object($this->_result ) ){
			$this->_result->free();
		}
		$this->_mysqli->close();
	}

	/**
	 * 错误日志记录
	 * @param  string $action 是什么操作
	 * @param  string $string 操作涉及的语句或者其他
	 * @return void 终端脚本的执行
	 */
    private function _errorlog( $action, $string ){
        $error  = date('Y-m-d H:i:s').PHP_EOL;
        $error .= 'action:'.$action.PHP_EOL;
        $error .= 'string:'.$string.PHP_EOL;    
        $error .= 'errno:'.$this->_mysqli->errno.PHP_EOL;
        $error .= 'error:'.$this->_mysqli->error.PHP_EOL;

        //写错误日志，die
        Lib_Logger::instance()->writeLog( 'error_mysql', $error, TRUE );
    }

}//end-class

