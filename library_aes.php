<?php
namespace library;
defined( 'AWESOME' ) OR exit ( 'Sorry! Invalid Access!' );

/**
 * 利用mcrypt做AES加密解密
 * rijndael-128，rijndael-192，rijndael-256就是AES加密，3种分别是使用不同的数据块和密钥长度进行加密
 */

class Library_Aes{

  	//算法,另外还有192和256两种长度 
	  //MCRYPT_RIJNDAEL_128 ( 仅 libmcrypt > 2.4.x 可用 )
	  //MCRYPT_RIJNDAEL_192 ( 仅 libmcrypt > 2.4.x 可用 )
	  //MCRYPT_RIJNDAEL_256 ( 仅 libmcrypt > 2.4.x 可用 )
    const CIPHER = MCRYPT_RIJNDAEL_128;  

    //模式
    //MCRYPT_MODE_ECB (electronic codebook) 适用于随机数据， 比如可以用这种模式来加密其他密钥。 
                      //由于要加密的数据很短，并且是随机的，所以这种模式的缺点反而起到了积极的作用。
    //MCRYPT_MODE_CBC (cipher block chaining) 特别适用于对文件进行加密。 相比 ECB， 它的安全性有明显提升。
	  //MCRYPT_MODE_CFB (cipher feedback) 对于每个单独的字节都进行加密， 所以非常适用于针对字节流的加密。
	  //MCRYPT_MODE_OFB (output feedback, in 8bit) 和 CFB 类似。 它可以用在无法容忍加密错误传播的应用中。 
	                  //因为它是按照 8 个比特位进行加密的， 所以安全系数较低，不建议使用。
	  //MCRYPT_MODE_NOFB (output feedback, in nbit) 和 OFB 类似，但是更加安全， 因为它可以按照算法指定的分组大小来对数据进行加密。
	  //MCRYPT_MODE_STREAM 是一种扩展模式， 它包含了诸如 "WAKE" 或 "RC4" 的流加密算法。
    const MODE = MCRYPT_MODE_ECB;  

    //默认的key，可以重新设置
    private static $_secret_key = 'default_secret_key';
    
    /**
     * 设置加密的key
     * 
     * @param string $key 新的加密key
     */
    public static function setKey( $key ) {
    	$key = trim( $key );
    	if( empty($key) ){
    		return FALSE;
    	}
        self::$_secret_key = $key;
    }

    /**
     * 获取加密key
     * 
     * @return string
     */
    public static function getKey(){
    	return self::$_secret_key;
    }

    /** 
     * 加密
     * 
     * @param  string $str 需加密的字符串 
     * @return string
     */  
    public static function aesEncode( $str ){  
        $iv  = mcrypt_create_iv(mcrypt_get_iv_size(self::CIPHER,self::MODE),MCRYPT_RAND);
        $key = self::getKey();
        $encrypt = mcrypt_encrypt(self::CIPHER, $key, $str, self::MODE, $iv);

        return trim( $encrypt );
    }  
      
      
    /** 
     * 解密
     * 
     * @param string $str 需解密的字符串 
     * @return string
     */  
    public static function aesDecode( $str ){  
        $iv  = mcrypt_create_iv(mcrypt_get_iv_size(self::CIPHER,self::MODE),MCRYPT_RAND);
		$key = self::getKey();
		$decrypt = mcrypt_decrypt(self::CIPHER, $key, $str, self::MODE, $iv);

        return trim( $decrypt );
    }  

}//end-class
