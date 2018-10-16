<?php
namespace library;
defined( 'AWESOME' ) OR exit ( 'Sorry! Invalid Access!' );

/**
 * 利用mcrypt做3DES加密解密
 */
 class Library_Des{

    const CIPHER = MCRYPT_3DES;     //加密算法
    const MODE   = MCRYPT_MODE_ECB; //加密模式

    /**
     *  加密
	 *
     * @param  string $des_key  加密Key
     * @param  string $str      待加密字符串
     * @return string
     */
    public static function encrypt( $des_key, $str ){
        if( empty($des_key)||empty($str) ){
            return FALSE;
        }
        return mcrypt_encrypt( self::CIPHER, $des_key, $str, self::MODE );
    } 

    /**
     *  解密
	 *
     * @param  string $des_key  解密Key
     * @param  string $str      待解密字符串
     * @return string
     */
    public static function decrypt( $des_key, $str ){
        if( empty($des_key)||empty($str) ){
            return FALSE;
        }
        return mcrypt_decrypt( self::CIPHER, $des_key, $str, self::MODE );
    }
 
 }//end-class
