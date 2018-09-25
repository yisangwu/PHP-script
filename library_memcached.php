<?php
namespace library;
defined( 'AWESOME' ) OR exit ( 'Sorry! Invalid Access!' );

use library\Lib_Logger;

/**
 * Memcached是一个自由开源的，高性能，分布式内存对象缓存系统。
 * Memcached是一种基于内存的key-value存储，用来存储小块的任意数据（字符串、对象）。
 * Memcached是一个简洁的key-value存储系统。
 */

class Lib_Memcached{

	  private $_memcached = null; 		//memcached 连接对象
	  private $_memcachedConf = array();	//memcached 的配置必须是数组array( ip,port)
    private $_isconnected = FALSE; 		//memcached 是否已经连接过
    private $_try = 2; 					//命令操作重试次数

	  private static $_numMemcachedConf  = 2;	//memcached 配置数组的元素个数
	  private static $_defaultexpire = 604800; //memcached 设置默认的过期时间为7天
    private static $_prefix = 'BIG_';    //memcached 设置所有key的前缀

    const LIMITEXPIRE = 2592000; //过期时间，时间差不能大于 2592000秒（30天）

	/**
	 * 构造函数，memcached配置的初始化
	 * 
	 * @param array $memcachedConf 配置数组
	 */
	public function __construct( $memcachedConf=array() ){
        if( !extension_loaded('memcached') ){ //检查是否安装了memcache扩展
            die( 'Please Install The Memcached Extention!' );
        }		
		if( !is_array($memcachedConf)&&count($memcachedConf)!=self::$_numMemcachedConf ){
			die( 'The Memcached Conf Is Wrong!' );
		}

		$this->_memcachedConf = $memcachedConf;
	}

	/**
	 * memcached连接
	 * @return Boolean 连接是否成功
	 */
	private function _connect(){
		if( !$this->_isconnected ){
			$this->_isconnected = TRUE;
	        $this->_memcached = new \Memcached();
			$this->_memcached->setOption( \Memcached::OPT_COMPRESSION, TRUE ); //开启压缩
			$this->_memcached->setOption( \Memcached::OPT_NO_BLOCK, TRUE ); //启用异步IO
			$this->_memcached->setOption( \Memcached::OPT_PREFIX_KEY, self::$_prefix ); //设置所有key的前缀

			$this->_memcached->addServer( $this->_memcachedConf[0],$this->_memcachedConf[1] );
		}
		
		return is_object($this->_memcached) ? TRUE : FALSE;
	}

	/**
	 * 向key存储一个元素值为 var -ok
	 * @param string $key    要设置值的key
	 * @param mixed  $value  要存储的值，字符串和数值直接存储，其他类型序列化后存储。
	 * @param int  	 $expire 当前写入缓存的数据的失效时间，用时间差的话不能超过 2592000秒（30天）。
	 * @return Boolean 成功时返回 TRUE， 或者在失败时返回 FALSE
	 */
	public function set( $key, $value, $expire=null ){
		if( !$this->_connect()||empty($key) ){
			return FALSE;
		}
        if( is_null($expire) ) {
            $expire = self::$_defaultexpire;
        }else{
        	$expire = ($expire>=self::LIMITEXPIRE) ? self::LIMITEXPIRE : $expire; //时间差不能超过 30天
        }

        $do_succ = FALSE;
        for( $try=0; $try<$this->_try; $try++ ){
        	$this->_memcached->set( $key, $value, $expire );

	        $resultCode = $this->_memcached->getResultCode(); //返回最后一次操作的结果代码
	        if( $resultCode==\Memcached::RES_SUCCESS ){
	        	$do_succ = TRUE;
	        	break;
	        }else{
	        	$this->_errorlog( $key, $try, $resultCode, 'set');
	        }
        }
        return $do_succ;
	}

	/**
	 * 一次存储多个key 值 -ok
	 * 过期时间expire参数指定的时候一次应用到所有的元素上。
	 * @param array  $arrItems 键／值对数组
	 * @param int  	 $expire   当前写入缓存的数据的失效时间，用时间差的话不能超过 2592000秒（30天）。
	 * @return Boolean 成功时返回 TRUE， 或者在失败时返回 FALSE
	 */
	public function setMulti( $arrItems, $expire=null ){
		if( !$this->_connect()||empty($arrItems)||!is_array($arrItems) ){
			return FALSE;
		}
        if( is_null($expire) ) {
            $expire = self::$_defaultexpire;
        }else{
        	$expire = ($expire>=self::LIMITEXPIRE) ? self::LIMITEXPIRE : $expire; //时间差不能超过 30天
        }

        $do_succ = FALSE;
        for( $try=0; $try<$this->_try; $try++ ){
        	$this->_memcached->setMulti( $arrItems, $expire );

	        $resultCode = $this->_memcached->getResultCode(); //返回最后一次操作的结果代码
	        if( $resultCode==\Memcached::RES_SUCCESS ){
	        	$do_succ = TRUE;
	        	break;
	        }else{
	        	$this->_errorlog( json_encode($arrItems), $try, $resultCode, 'setMulti');
	        }
        }
        return $do_succ;
	}

	/**
	 * 获取以key作为key存储的元素存储的值 -ok
	 * @param string $key    要获取值的key
	 * @return string / false
	 */
	public function get( $key ){
		if( !$this->_connect()||empty($key) ){
			return FALSE;
		}

        $do_succ = FALSE;
        for( $try=0; $try<$this->_try; $try++ ){
        	$result = $this->_memcached->get( $key );

	        $resultCode = $this->_memcached->getResultCode(); //返回最后一次操作的结果代码
	        if( $resultCode==\Memcached::RES_SUCCESS ){
	        	$do_succ = TRUE;
	        	break;
	        }else{
	        	//$this->_errorlog( $key, $try, $resultCode, 'get');
	        	if( in_array($resultCode, array(\Memcached::RES_PAYLOAD_FAILURE, \Memcached::RES_BAD_KEY_PROVIDED, '4294966295')) ){ //不能正常解压或者key不正确,删除
					$this->_memcached->delete( $key );
				}	        	
	        }
        }
        return $do_succ? $result : FALSE;	        
	}

	/**
	 * 一次获取keys数组指定的多个key对应存储的值 -ok
	 * array_filter 防止出现 array('key1'=>null, 'key2'=>null ) 的情况
	 * @param  array  $arrKeys    key的数组
	 * @param  string $cas_tokens 用来存储检索到的元素的CAS标记，默认 null
	 * @param  int    $flags      仅可以指定为\Memcached::GET_PRESERVE_ORDER以保证返回的key的顺序和请求时一致。
	 * @return string / false
	 */
	public function getMulti( $arrKeys, $cas_tokens=null, $flags=\Memcached::GET_PRESERVE_ORDER ){
		if( !$this->_connect()||empty($arrKeys)||!is_array($arrKeys) ){
			return FALSE;
		}

        $do_succ = FALSE;
        for( $try=0; $try<$this->_try; $try++ ){
        	$result = $this->_memcached->getMulti( $arrKeys, $cas_tokens, $flags );

	        $resultCode = $this->_memcached->getResultCode(); //返回最后一次操作的结果代码
	        if( $resultCode==\Memcached::RES_SUCCESS ){
	        	$do_succ = TRUE;
	        	break;
	        }else{
	        	$this->_errorlog( json_encode($arrKeys), $try, $resultCode, 'getMulti');
	        }
        }
        return $do_succ? array_filter($result) : FALSE;	
	}

	/**
	 * 通过key删除一个元素。 -ok 
	 * 如果参数timeout指定，该元素会在timeout秒后失效
	 * key不存在 \Memcached::RES_NOTFOUND 
	 * @param  string $key     要删除值的key
	 * @param  int    $timeout 多少秒之后删除
	 * @return Boolean 成功时返回 TRUE， 或者在失败时返回 FALSE
	 */
	public function delete( $key, $timeout=0 ){
		if( !$this->_connect()||empty($key) ){
			return FALSE;
		}

        $do_succ = FALSE;
        for( $try=0; $try<$this->_try; $try++ ){
        	$flag = $this->_memcached->delete( $key, $timeout );

	        $resultCode = $this->_memcached->getResultCode(); //返回最后一次操作的结果代码
	        if( $resultCode==\Memcached::RES_SUCCESS || $resultCode==\Memcached::RES_NOTFOUND ){
	        	$do_succ = TRUE;
	        	break;
	        }else{
	        	$this->_errorlog( $key, $try, $resultCode, 'delete');
	        }
        }
        return $do_succ? $flag : FALSE;
	}

	/**
	 * 一次删除多个key -ok
	 * key不存在 \Memcached::RES_NOTFOUND 
	 * 删除多个Key时，有一个key不存在则返回false
	 * 每一个key都存在时，返回 array( key1=>true, key2=>true )
	 * 
	 * @param  array   $arrKeys 要删除的key数组
	 * @param  integer $timeout 延迟多少秒，再删除
	 * @return array/ Boolean 成功时返回 TRUE， 或者在失败时返回 FALSE
	 */
	public function deleteMulti( $arrKeys, $timeout=0 ){
		if( !$this->_connect()||empty($arrKeys)||!is_array($arrKeys) ){
			return FALSE;
		}

        $do_succ = FALSE;
        for( $try=0; $try<$this->_try; $try++ ){
        	$flag = $this->_memcached->deleteMulti( $arrKeys, $timeout );

	        $resultCode = $this->_memcached->getResultCode(); //返回最后一次操作的结果代码
	        if( $resultCode==\Memcached::RES_SUCCESS || $resultCode==\Memcached::RES_NOTFOUND ){
	        	$do_succ = TRUE;
	        	break;
	        }else{
	        	$this->_errorlog( json_encode($arrKeys), $try, $resultCode, 'deleteMulti');
	        }
        }
        return $do_succ? $flag : FALSE;
	}

	/**
	 * 向已存在的元素前面追加数据 -ok
	 * 如果\Memcached::OPT_COMPRESSION常量开启，这个操作会失败，并引发一个警告，
	 * 因为向压缩数据 后追加数据可能会导致解压不了。
	 * 要先关闭压缩，才可以追加
	 * 如果key不存在 \Memcached::RES_NOTSTORED
	 * @param   string  $key   向前追加数据的元素的key
	 * @param   string  $value 要追加的字符串
	 * @return  Boolean 成功时返回 TRUE， 或者在失败时返回 FALSE
	 */
	public function prepend( $key, $value ){
		if( !$this->_connect()||empty($key) ){
			return FALSE;
		}
		//要先关闭压缩，才可以追加
		$this->_memcached->setOption( \Memcached::OPT_COMPRESSION, FALSE );

        $do_succ = FALSE;
        for( $try=0; $try<$this->_try; $try++ ){
        	$flag = $this->_memcached->prepend( $key, $value );

	        $resultCode = $this->_memcached->getResultCode(); //返回最后一次操作的结果代码
	        if( $resultCode==\Memcached::RES_SUCCESS ){
	        	$do_succ = TRUE;
	        	break;
	        }else{
	        	$this->_errorlog( json_encode($arrKeys), $try, $resultCode, 'prepend');
	        }
        }
        return $do_succ? $flag : FALSE;
	}

	/**
	 * 向已存在元素后追加数据 -ok
	 * 如果\Memcached::OPT_COMPRESSION常量开启，这个操作会失败，并引发一个警告，
	 * 因为向压缩数据 后追加数据可能会导致解压不了。
	 * 要先关闭压缩，才可以追加
	 * 如果key不存在 \Memcached::RES_NOTSTORED
	 * @param   string  $key   向后追加数据的元素的key
	 * @param   string  $value 要追加的字符串
	 * @return  Boolean 成功时返回 TRUE， 或者在失败时返回 FALSE
	 */
	public function append( $key, $value ){
		if( !$this->_connect()||empty($key) ){
			return FALSE;
		}
		//要先关闭压缩，才可以追加
		$this->_memcached->setOption( \Memcached::OPT_COMPRESSION, FALSE );

        $do_succ = FALSE;
        for( $try=0; $try<$this->_try; $try++ ){
        	$flag = $this->_memcached->append( $key, $value );

	        $resultCode = $this->_memcached->getResultCode(); //返回最后一次操作的结果代码
	        if( $resultCode==\Memcached::RES_SUCCESS ){
	        	$do_succ = TRUE;
	        	break;
	        }else{
	        	$this->_errorlog( json_encode($arrKeys), $try, $resultCode, 'prepend');
	        }
        }
        return $do_succ? $flag : FALSE;
	}

	/**
	 * 获取服务器池的统计信息
	 * 服务器统计信息数组， 每个服务器一项。
	 * array( '127.0.0.1:11211'=>array('pid'=>3547,... ...)  )
	 * 服务器池中有不可用服务器时，返回false
	 * @return array / false
	 */
	public function getStats(){
		if( !$this->_connect() ){
			return FALSE;
		}
		return $this->_memcached->getStats();
	}

	/**
	 * 获取服务器池中的服务器列表
	 * array( 0=>array( 'host'=>'127.0.0.1','port'=>11211) )
	 * @return array 服务器池中所有服务器列表
	 */
	public function getServerList(){
		if( !$this->_connect() ){
			return FALSE;
		}		
		return $this->_memcached->getServerList();
	}

	/**
	 * 获取服务器池中所有服务器的版本信息
	 * array( "127.0.0.1:11211"=>"1.4.24" )
	 * 服务器池中有不可用服务器时，返回false
	 * @return array /false 返回一个包含所有可用memcached服务器版本信息的数组
	 */
	public function getVersion(){
		if( !$this->_connect() ){
			return FALSE;
		}		
		return $this->_memcached->getVersion();
	}

	/**
	 * 错误日志记录
	 * @param  string $key    		操作的key
	 * @param  int 	  $try    		重试次数 
	 * @param  int    $resultCode 	错误码
	 * @param  string $method 		操作方法，ex：set，get...
	 * @return void
	 */
    private function _errorlog( $key, $try, $resultCode, $method ){
        $error  = date('Y-m-d H:i:s').PHP_EOL;
        $error .= 'key:'.var_export( $key,TRUE ).PHP_EOL;
        $error .= 'try:'.$try.PHP_EOL;       
        $error .= 'method:'.$method.PHP_EOL;   
        $error .= 'resultCode:'.$resultCode.PHP_EOL;

        $server = $this->_memcached->getServerByKey( $key ); //获取key所映射的服务器信息
        $msg 	= $this->_memcached->getResultMessage(); //获取最后一次操作的结果描述消息

        $error .= 'server:'.var_export( $server,TRUE ).PHP_EOL;
        $error .= 'msg:'.$msg.PHP_EOL;

        //写错误日志，die
        Lib_Logger::instance()->writeLog( 'error_memcached', $error, FALSE );
    }

}//end-class
