<?php
namespace library;
defined('AWESOME') OR exit('Sorry! Invalid Access!');

/**
 * Redis是完全开源免费的，遵守BSD协议，是一个高性能的key-value数据库。
 * Redis支持数据的持久化。
 * Redis支持简单的key-value类型，还提供list，set，zset，hash等数据结构的存储。
 * Redis支持数据的备份，即master-slave模式的数据备份。
 *
 * Redis读的速度是110000次/s,写的速度是81000次/s
 * Redis的所有操作都是原子性的，同时Redis还支持对几个操作全并后的原子性执行。
 *
 * @package     Library
 * @subpackage  Lib_Redis
 * @author      yisangwu
 *
 */

class Lib_Redis {

	private $_redis = null; //redis 连接对象
	private $_redisConf = array(); //redis 的配置必须是数组array( ip,port)
	private $_connect = FALSE; //redis 连接
	private $_isconnected = FALSE; //redis 是否已经连接过
	private $_gzcompress = FALSE; //是否可以使用压缩
	private $_serialize = FALSE; //是否可以使用序列化

	private static $_numRedisConf = 2; //redis 配置数组的元素个数
	private static $_defaultexpire = 172800; //redis 设置默认的过期时间为48小时
	private static $_prefix = 'RBIG_'; //redis 设置所有key的前缀

	/**
	 * 构造函数，redis配置的初始化
	 *
	 * @param array $redisConf 配置数组
	 */
	public function __construct($redisConf = array()) {
		if (!extension_loaded('redis')) {
			//检查是否安装了redis扩展
			die('Please Install The Redis Extention!');
		}
		if (!is_array($redisConf) && count($redisConf) != self::$_numRedisConf) {
			die('The Redis Conf Is Wrong!');
		}
		if (function_exists('serialize')) {
			//PHP支不支持数据的序列化
			$this->_serialize = TRUE;
		}
		if (function_exists('gzcompress')) {
			//PHP支不支持数据的压缩
			$this->_gzcompress = TRUE;
		}
		$this->_redisConf = $redisConf;
	}

	/**
	 * redis连接
	 * @return Boolean 连接是否成功
	 */
	private function _connect() {
		if (!$this->_isconnected) {
			try {
				$this->_redis = new \Redis();
				$this->_connect = $this->_redis->connect($this->_redisConf[0], $this->_redisConf[1]); //TRUE on success, FALSE on error.
				if ($this->_connect) {
					$this->_isconnected = TRUE;
					//SERIALIZER_NONE 不进行序列化
					//SERIALIZER_PHP PHP序列化工具（即serialize方法）
					//SERIALIZER_IGBINARY 二进制序列化
					$this->_redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
					$this->_redis->setOption(\Redis::OPT_PREFIX, self::$_prefix); //设置所有key的前缀
				}
			} catch (Exception $e) {
				//连接失败时，抛出异常
				die('Redis Connect Failed' . $e->getCode() . $e->getMessage());
			}
		}
		return $this->_connect;
	}

	/**
	 * 处理数据，压缩 和 序列化
	 * @param  Mixed  $value 写入 或 读取的缓存数据
	 * @param  int    $act   0，写入缓存；1，读取缓存
	 * @return string 处理压缩后的数据 或者 不做处理的数据
	 */
	private function _dealValue($value, $act = 0) {
		return $value;
	}

	/**
	 * 写入缓存
	 *
	 * @param string $name 缓存变量名
	 * @param mixed $value 存储数据
	 * @param int $expire  过期时间（秒）
	 * @return boolean
	 */
	public function set($key, $value, $expire = null) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		if (is_null($expire)) {
			$expire = self::$_defaultexpire;
		}
		//对数组/对象数据进行缓存处理，保证数据完整性
		$value = self::_dealValue($value);
		if (is_int($expire) && $expire) {
			$flag = $this->_redis->setex($key, $expire, $value);
		} else {
			$flag = $this->_redis->set($key, $value);
		}
		return $flag;
	}

	/**
	 * 读取缓存
	 *
	 * @param  string $key 缓存变量名
	 * @return mixed
	 */
	public function get($key) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		$value = $this->_redis->get($key);
		return self::_dealValue($value, 1);
	}

	/**
	 * 删除key对应的缓存
	 *
	 * @param  string $key 缓存变量名
	 * @return int 成功删除的key的个数，key不存在则为 0
	 */
	public function delete($key) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		return $this->_redis->delete($key); //del
	}

	/**
	 * 将 key 中储存的数字值增一。
	 * 如果 key 不存在，那么 key 的值会先被初始化为 0 ，然后再执行 INCR 操作。
	 * 如果值包含错误的类型，或字符串类型的值不能表示为数字，那么返回一个错误。
	 * 本操作的值限制在 64 位(bit)有符号数字表示之内。
	 * @param  string $key 缓存变量名
	 * @return false/int 返回最新的值
	 */
	public function incr($key) {
		if (!$this->_connect()) {
			return FALSE;
		}
		return $this->_redis->incr($key);
	}

	/**
	 * 将 key 中储存的数字加上指定的增量值。
	 * 如果 key 不存在，那么 key 的值会先被初始化为 0 ，然后再执行 INCRBY 命令
	 * 如果值包含错误的类型，或字符串类型的值不能表示为数字，那么返回一个错误。
	 * 本操作的值限制在 64 位(bit)有符号数字表示之内。
	 * @param  string $key   缓存变量名
	 * @param  int    $value 增量值,必须是int，否则返回false
	 * @return false/int 返回最新的值
	 */
	public function incrBy($key, $value) {
		if (!$this->_connect() || !is_int($value)) {
			return FALSE;
		}
		return $this->_redis->incrBy($key, $value);
	}

	/**
	 * 将 key 中储存的数字值减一。
	 * 如果 key 不存在，那么 key 的值会先被初始化为 0 ，然后再执行 DECR 操作。
	 * 如果值包含错误的类型，或字符串类型的值不能表示为数字，那么返回一个错误。
	 * 本操作的值限制在 64 位(bit)有符号数字表示之内。
	 * @param  string $key 缓存变量名
	 * @return false/int 返回最新的值
	 */
	public function decr($key) {
		if (!$this->_connect()) {
			return FALSE;
		}
		return $this->_redis->decr($key);
	}

	/**
	 * 将 key 所储存的值减去指定的减量值。
	 * 如果 key 不存在，那么 key 的值会先被初始化为 0 ，然后再执行 DECRBY 命令
	 * @param  string $key   缓存变量名
	 * @param  int    $value 减量值
	 * @return false/int 返回最新的值
	 */
	public function decrBy($key, $value) {
		if (!$this->_connect() || !is_int($value)) {
			return FALSE;
		}
		return $this->_redis->decrBy($key, $value);
	}

	/******************************  队列 List ******************************************************/
	/**
	 * 左压入队列，把元素加入到队列左边(头部)，
	 * 如果不存在则创建一个队列
	 * 当 key 存在但不是列表类型时，返回一个错误。
	 * @param  string $key    缓存变量名
	 * @param  string $value  加入队列头部的值
	 * @param  int    $expire 过期时间
	 * @return Boolean /int   LPUSH 命令执行之后，列表的长度。
	 */
	public function lPush($key, $value, $expire = null) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		if (is_null($expire)) {
			$expire = self::$_defaultexpire;
		}
		$value = self::_dealValue($value);
		$flag = $this->_redis->lPush($key, $value);
		if ($flag && $expire) {
			$this->_redis->setTimeout($key, $expire);
		}
		return $flag;
	}

	/**
	 * 右压入队列，把元素加入到队列左边(尾部)，
	 * 如果不存在则创建一个队列
	 * 当 key 存在但不是列表类型时，返回一个错误。
	 * @param  string $key    缓存变量名
	 * @param  string $value  加入队列尾部的值
	 * @param  int    $expire 过期时间（秒）
	 * @return Boolean /int   执行 RPUSH 操作后，列表的长度。
	 */
	public function rPush($key, $value, $expire = null) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		if (is_null($expire)) {
			$expire = self::$_defaultexpire;
		}
		$value = self::_dealValue($value);
		$flag = $this->_redis->rPush($key, $value);
		if ($flag && $expire) {
			$this->_redis->setTimeout($key, $expire);
		}
		return $flag;
	}

	/**
	 * 弹出队列头部元素,移除并返回列表的第一个元素。
	 * @param  string $key   缓存变量名
	 * @return string/false  列表的第一个元素。当列表 key 不存在时，返回 false 。
	 */
	public function lPop($key) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		$value = $this->_redis->lPop($key);
		return self::_dealValue($value, 1);
	}

	/**
	 * 弹出队列尾部元素,移除并返回列表的最后一个元素。
	 * @param  string $key    缓存变量名
	 * @return string/false   列表的最后一个元素。当列表 key 不存在时，返回 false
	 */
	public function rPop($key) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		$value = $this->_redis->rPop($key);
		return self::_dealValue($value, 1);
	}

	/**
	 * 返回队列里的元素个数.
	 * 不存在则为0.不是队列则为false
	 * @param  string $key    缓存变量名
	 * @return int/false
	 */
	public function lSize($key) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		return $this->_redis->lSize($key); //lLen
	}

	/**
	 *  对一个队列进行修剪(trim)，就是说， -ok
	 *  让列表只保留指定区间内的元素，不在指定区间之内的元素都将被删除。
	 *  下标 0 表示列表的第一个元素，以 1 表示列表的第二个元素
	 *  -1 表示列表的最后一个元素， -2 表示列表的倒数第二个元素
	 * @param   string  $key    缓存变量名
	 * @param   int     $start  保留区间，开始位置
	 * @param   int     $end    保留区间，结束位置
	 * @return  Boolean
	 */
	public function lTrim($key, $start, $end) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		return $this->_redis->lTrim($key, $start, $end); //listTrim
	}

	/**
	 * 取出队列的某一段，返回列表中指定区间内的元素， -ok
	 * 区间以偏移量 START 和 END 指定
	 * 下标 0 表示列表的第一个元素，以 1 表示列表的第二个元素
	 * -1 表示列表的最后一个元素， -2 表示列表的倒数第二个元素
	 * @param   string  $key    缓存变量名
	 * @param   string  $start  开始位置，第一个为0
	 * @param   string  $end    结束位置，最后一个为-1
	 * @return  array
	 */
	public function lGetRange($key, $start, $end) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		$result = $this->_redis->lGetRange($key, $start, $end); //lRange
		if (!is_array($result)) {
			return array();
		}
		foreach ($result as $k => $v) {
			$aList[$k] = self::_dealValue($v, 1);
		}
		return (array) $aList;
	}

	/**
	 * 根据参数 COUNT 的值，移除列表中与参数 VALUE 相等的元素。
	 * COUNT 的值可以是以下几种：
	 * count > 0 : 从表头开始向表尾搜索，移除与 VALUE 相等的元素，数量为 COUNT 。
	 * count < 0 : 从表尾开始向表头搜索，移除与 VALUE 相等的元素，数量为 COUNT 的绝对值。
	 * count = 0 : 移除表中所有与 VALUE 相等的值。
	 * @param   string  $key    缓存变量名
	 * @param   string  $value  要删除的值
	 * @param   int     $count  删除的个数，$count大于列表中存在的$value 个数时，删除返回真实移除数量
	 * @return  int/false 删除的个数 / false 被移除元素的数量。 没有值 或 列表不存在时返回 0
	 */
	public function lRem($key, $value, $count) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		$value = self::_dealValue($value);
		return $this->_redis->lRem($key, $value, $count); //lRemove
	}

/******************************  哈希 Hash ******************************************************/

	/**
	 * 从哈希名为hashkey中添加key1->value1 将哈希表key中的域field的值设为value。-ok -ok
	 * 如果key不存在，一个新的哈希表被创建并进行hset操作。
	 * 如果域field已经存在于哈希表中，旧值将被覆盖。
	 * 错误则 返回 FALSE
	 * 如果字段是哈希表中的一个新建字段，并且值设置成功，返回 1 。
	 * 如果哈希表中域字段已经存在且旧值已被新值覆盖，返回 0 。
	 * @param   string  $hashname   哈希名
	 * @param   string  $key        key名
	 * @param   string  $value      key 对应的值
	 * @param   int     $expire     过期时间（秒）*
	 * @return  int/false  1 成功，0 替换 ，false 失败
	 */
	public function hSet($hashname, $key, $value, $expire = null) {
		if (!$this->_connect() || empty($hashname) || empty($key)) {
			return FALSE;
		}
		if (is_null($expire)) {
			$expire = self::$_defaultexpire;
		}
		$flag = $this->_redis->hSet($hashname, $key, $value);
		if ($flag !== FALSE && $expire) {
			$this->_redis->setTimeout($hashname, $expire);
		}
		return $flag;
	}

	/**
	 * 同时将多个 field-value (字段-值)对设置到哈希表中。-ok -ok
	 * 此命令会覆盖哈希表中已存在的字段。
	 * 如果哈希表不存在，会创建一个空哈希表，并执行 HMSET 操作。
	 * 可用版本 >= 2.0.0
	 * @param   string  $hashname   哈希名
	 * @param   array   $arr        array(key1=>value1,key2=>value2.........)
	 * @return  boolean 如果命令执行成功，返回 OK 。
	 */
	public function hMset($hashname, $arr, $expire = null) {
		if (!$this->_connect() || empty($hashname) || !is_array($arr)) {
			return FALSE;
		}
		if (is_null($expire)) {
			$expire = self::$_defaultexpire;
		}
		$flag = $this->_redis->hMset($hashname, $arr);
		if ($flag && $expire) {
			$this->_redis->setTimeout($hashname, $expire);
		}
		return $flag;
	}

	/**
	 * 从哈希名为haskey中获取key名为$key的值 -ok
	 * 如果hashname 或 key 不存在时
	 * 可用版本 >= 2.0.0
	 * @param   string  $hashname   哈希名
	 * @param   string  $key        key名
	 * @param   string/false  值 / 不存在则false
	 */
	public function hGet($hashname, $key) {
		if (!$this->_connect() || empty($hashname) || empty($key)) {
			return FALSE;
		}
		return $this->_redis->hGet($hashname, $key);
	}

	/**
	 * 删除哈希表hashname中的一个或多个指定字段key -ok
	 * 不存在的字段将被忽略
	 * 可用版本 >= 2.0.0
	 * @param   string         $hashname    哈希名
	 * @param   string/array   $mixedKey    string 删除单个key，array 删除多个 key
	 * @return  int    成功返回被成功删除字段的数量，hashname 或 key 不存在则返回 0
	 */
	public function hDel($hashname, $mixedKey) {
		if (!$this->_connect() || empty($hashname) || empty($mixedKey)) {
			return FALSE;
		}
		if (is_array($mixedKey)) {
			return call_user_func_array(array($this->_redis, 'hDel'), array_merge(array($hashname), $mixedKey));
		} else {
			return $this->_redis->hDel($hashname, $mixedKey);
		}
	}

	/**
	 * 获取哈希名为hashname的hash中所有键对应的value -ok
	 * 以array形式返回哈希表的字段及字段值。 若 key 不存在，返回空array
	 * @param   string  $hashname   哈希名
	 * @return  array   键值对应的数组,如果hashname不存在，则返回空数组
	 */
	public function hGetAll($hashname) {
		if (!$this->_connect() || empty($hashname)) {
			return FALSE;
		}
		return $this->_redis->hGetAll($hashname);
	}

	/**
	 * 为哈希表中的字段值加上指定增量值。-ok
	 * 增量也可以为负数，相当于对指定字段进行减法操作。
	 * 如果哈希表hashname不存在，一个新的哈希表被创建并执行 HINCRBY 命令。
	 * 如果指定的字段key不存在，那么在执行命令前，字段的值被初始化为 0 。
	 * 对一个储存字符串值的字段执行 HINCRBY 命令将造成一个错误。
	 * @param   string  $hashname   哈希名
	 * @param   string  $key        key名
	 * @param   int     $value      增量值，整数，可正可负
	 * @return  false/int           增量操作后的值,hashname 或 key 不存在时，新建再加，只有当key的值是字符串才false
	 */
	public function hIncrBy($hashname, $key, $value) {
		if (!$this->_connect() || empty($hashname) || empty($key) || empty($value)) {
			return FALSE;
		}
		return $this->_redis->hIncrBy($hashname, $key, $value);
	}

/******************************  无序集合 Set ******************************************************/

	/**
	 * 给该key添加一个唯一值.相当于制作一个没有重复值的数组 -ok
	 * 将一个或多个成员元素加入到集合中，已经存在于集合的成员元素将被忽略。
	 * 假如集合 key 不存在，则创建一个只包含添加的元素作成员的集合。
	 * 当集合 key 不是集合类型时，返回一个错误。
	 * 注意：在Redis2.4版本以前， SADD 只接受单个成员值。
	 * 只有添加成功，才可以设置过期时间，0 和 false都不会改变过期时间
	 * 集合的最后一次操作的过期时间，会覆盖之前的过期时间，如果最后一次没有过期时间，则永不过期
	 * @param  string   $key    缓存变量名
	 * @param  array /string $arrValue 数组，集合元素
	 * @return int /false  1成功添加，0 已存在，当key不是集合时false
	 */
	public function sAdd($key, $mixedValue, $expire = null) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		if (is_null($expire)) {
			$expire = self::$_defaultexpire;
		}

		if (is_array($mixedValue)) {
			foreach ($mixedValue as $value) {
				$arrV[] = self::_dealValue($value);
			}
			$flag = call_user_func_array(array($this->_redis, 'sAdd'), array_merge(array($key), $arrV));
		} else {
			$flag = $this->_redis->sAdd($key, $mixedValue);
		}
		//设置过期时间
		if ($flag !== FALSE && $expire) {
			$this->_redis->setTimeout($key, $expire);
		}
		return $flag;
	}

	/**
	 * 获取集合key中元素的数量 -ok
	 * 集合的数量。 当集合 key 不存在时，返回 0
	 * @param  string   $key    缓存变量名
	 * @return int 集合的数量。 当集合 key 不存在时，返回 0
	 */
	public function sSize($key) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		return $this->_redis->sSize($key); //sCard
	}

	/**
	 * 获取某数组所有值 -ok
	 * 返回集合中的所有的成员。 不存在的集合 key 被视为空集合。
	 * @param  string $key   集合名
	 * @return array  数组，如果集合不存在，则返回空array
	 */
	public function sMembers($key) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		$result = $this->_redis->sMembers($key); //sGetMembers
		$aList = array();
		if (is_array($result)) {
			foreach ($result as $k => $v) {
				$aList[$k] = self::_dealValue($v, 1);
			}
			return (array) $aList;
		}
		return array();
	}

	/**
	 * 删除该数组中对应的值 -ok
	 * 移除集合中的一个或多个成员元素，不存在的成员元素会被忽略。
	 * 当 key 不是集合类型，返回一个错误。
	 * 在 Redis 2.4 版本以前， SREM 只接受单个成员值。
	 * @param  string $key   集合名
	 * @param  array /string $arrValue 数组，要删除的成员值
	 * @return Boolean
	 */
	public function sRem($key, $mixedValue) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		if (is_array($mixedValue)) {
			foreach ($mixedValue as $value) {
				$arrV[] = self::_dealValue($value);
			}
			return call_user_func_array(array($this->_redis, 'sRem'), array_merge(array($key), $arrV));
		} else {
			return $this->_redis->sRem($key, $mixedValue); //sRemove
		}
	}

	/**
	 * 移除并返回集合中的一个随机元素。-ok
	 * 将随机元素从集合中移除并返回。
	 * 集合不存在或者为空时，返回false
	 * @param  string $key    集合名
	 * @return Boolean/string 返回元素，如果集合为空或者不存在，则返回false
	 */
	public function sPop($key) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		return $this->_redis->sPop($key);
	}

	/**
	 * 随机获取任意个数元素
	 */
	public function sRandMember($key, $count = 1) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		return $this->_redis->sRandMember($key, $count);
	}

/******************************  有序集合 Zset( sorted set ) ******************************************************/

	/**
	 * 将一个或多个成员元素及其分数值加入到有序集当中。
	 * 如果某个成员已经是有序集的成员，那么更新这个成员的分数值，并通过重新插入这个成员元素，来保证该成员在正确的位置上。
	 * 如果有序集合 key 不存在，则创建一个空的有序集并执行 ZADD 操作。
	 * 当 key 存在但不是有序集类型时，返回一个错误。
	 * @param  string   $key    集合名
	 * @param  integer  $score  分数值，可以是整数值或双精度浮点数。
	 * @param  string   $value  字符串 或 整数
	 * @param  int      $expire 过期时间，秒
	 * @return boolean
	 */
	public function zAdd($key = '', $score = 0, $value = '', $expire = null) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		if (is_null($expire)) {
			$expire = self::$_defaultexpire;
		}
		$flag = $this->_redis->zAdd($key, $score, $value);
		//设置过期时间
		if ($flag !== FALSE && $expire) {
			$this->_redis->setTimeout($key, $expire);
		}
		return $flag;
	}

	/**
	 * 逆序
	 * 返回有序集中指定分数区间内的所有的成员。
	 * 有序集成员按分数值递减(从大到小)的次序排列。
	 * 具有相同分数值的成员按字典序的逆序(reverse lexicographical order )排列。
	 * @param  string   $key        集合名
	 * @param  int      $s_start    最大分数值
	 * @param  int      $s_end      最小分数值
	 * @param  boolean  $withscores 是否带分数值，默认不带 false,
	 * 带分数： array( value=>score )。不带分数 array( value )
	 * @param  int      $limit      获取成员个数，默认为 1
	 * @return false / array
	 */
	public function zRevRangeByScore($key = '', $s_start = 0, $s_end = 0, $withscores = FALSE, $limit = 1) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		if (empty($s_start) && empty($s_end)) {

			return FALSE;
		}
		$z_arr = $this->_redis->zRevRangeByScore($key, $s_start, $s_end, array('withscores' => $withscores, 'limit' => array(0, $limit)));
		if (empty($z_arr) || !is_array($z_arr)) {
			return FALSE;
		}
		return $z_arr;
	}

	/**
	 * 正序
	 * 返回有序集中指定分数区间内的所有的成员。
	 * 有序集成员按分数值递减(从大到小)的次序排列。
	 * 具有相同分数值的成员按字典序的逆序(reverse lexicographical order )排列。
	 * @param  string   $key        集合名
	 * @param  int      $s_start    最大分数值
	 * @param  int      $s_end      最小分数值
	 * @param  boolean  $withscores 是否带分数值，默认不带 false,
	 * 带分数： array( value=>score )。不带分数 array( value )
	 * @param  int      $limit      获取成员个数，默认为 1
	 * @return false / array
	 */
	public function zRangeByScore($key = '', $s_start = 0, $s_end = 0, $withscores = FALSE, $limit = 1) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		if (empty($s_start) && empty($s_end)) {

			return FALSE;
		}
		$z_arr = $this->_redis->zRangeByScore($key, $s_start, $s_end, array('withscores' => $withscores, 'limit' => array(0, $limit)));
		if (empty($z_arr) || !is_array($z_arr)) {
			return FALSE;
		}
		return $z_arr;
	}

	/**
	 * 移除单个元素
	 * 不存在的成员将被忽略。
	 * 当 key 存在但不是有序集类型时，返回一个错误。
	 *
	 * @param  string   $key   集合名
	 * @param  string   $value 移除的元素
	 * @return boolean
	 */
	public function zDelete($key = '', $value = NULL) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		if (is_null($value)) {
			return FALSE;
		}
		return $this->_redis->zDelete($key, $value);
	}

	/**
	 * 移除有序集中指定排名(rank)区间内的所有成员。start从0开始，stop可以为负数，表示倒过来第几位
	 * @param string $key    集合名
	 * @param int $start     开始位置
	 * @param int $stop      结束位置
	 * @return bool
	 */
	public function zRemRangeByRank($key = '', $start = 0, $stop = 0) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		$start = (int) trim($start);
		$stop = (int) trim($stop);
		$z_num = $this->_redis->zRemRangeByRank($key, $start, $stop);

		return $z_num;
	}

	/**
	 * 获取有序集合的成员数
	 * @param string $key    集合名
	 * @return bool
	 */
	public function zCard($key = '') {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		return $this->_redis->zCard($key);
	}

	/**
	 * 获取有序集合的成员数
	 * @param string $key    集合名
	 * @return bool
	 */
	public function zRange($key = '', $start = 0, $end = 1) {
		if (!$this->_connect() || empty($key)) {
			return FALSE;
		}
		return $this->_redis->zRange($key, $start, $end);
	}

/******************************  服务器信息 等 ******************************************************/

	/**
	 * 析构函数
	 * close 关闭redis连接, 成功时返回 TRUE， 或者在失败时返回 FALSE
	 */
	public function __destruct() {
		if (!$this->_connect()) {
			return FALSE;
		}
		$this->_redis->close() && ($this->_isconnected = FALSE);
	}

} //end-class
