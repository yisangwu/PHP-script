<?php

/**
 * 从 AdMob 密钥服务器提取
 * 用于验证激励视频广告 SSV 回调的
 * 公钥列表
 * @return array
 */
function fetchVerifierKeys()
{
    $url = 'https://www.gstatic.com/admob/reward/verifier-keys.json';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,3);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);

    if(curl_errno($ch)){
        curl_close($ch);
        return [];
    }

    curl_close($ch);
    $result = json_decode($result, true);

    $keys_arr = [];
    foreach ($result['keys'] as $keys) {
        $keyId = trim($keys['keyId'] ?? '');
        if (empty($keyId)) {
            continue;
        }
        $keys_arr[$keyId]['pem'] = trim($keys['pem']);
        $keys_arr[$keyId]['base64'] = trim($keys['base64']);
    }

    return $keys_arr;
}


/**
 * 验证签名
 * 
 * @param  string $data            源签名数据
 * @param  string $signature       签名字符串
 * @param  string $publicKeyString 公钥
 * @return boolean  true-签名正确，false-签名错误
 */
function verifySignature(string $data='', string $signature='', string $publicKeyString='')
{
    $data = trim($data);
    $signature = trim($signature);
    $publicKeyString = trim($publicKeyString);

    $ret_verify = ['code'=> 0, 'message'=> 'success!'];

    if (empty($data) || !is_string($data) || empty($signature) || empty($publicKeyString)) {
        $ret_verify['code'] = -1;
        $ret_verify['message'] = 'params error!';

        return $ret_verify;
    }

    $publicKey = openssl_pkey_get_public($publicKeyString);
    if ( !is_resource($publicKey)) {
        $ret_verify['code'] = -2;
        $ret_verify['message'] = 'publicKey error!';

        return $ret_verify;
    }

    $result = openssl_verify($data, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256);

    if ($result === 1) {
        return $ret_verify;
    }

    $ret_verify['code'] = -3;
    $ret_verify['message'] = openssl_error_string();

    return $ret_verify;
}


/************************************* example **********************************************/

$keys_arr = fetchVerifierKeys();

if (empty($keys_arr) || !is_array($keys_arr)) {
    die('fetchVerifierKeys failed!');
}

// google 回调过来的get字符串参数
$query_string = '';

// 查询字符串解析到变量
parse_str($query_string, $query_arr);

// 公钥的key_id
$key_id = trim($query_arr['key_id'] ?? '');
if (empty($key_id) || !isset($keys_arr[$key_id])) {
    die('can not find publicKey for key_id!');
}

// 签名
$signature = trim($query_arr['signature']?? '');
// 最关键的一点，是这个字符串的替换。不替换怎么做都是验证失败
$signature = str_replace(['-', '_'], ['+', '/'], $signature);
// 签名源数据
$message = substr($query_string, 0, strpos($query_string, '&signature'));

// 公钥的base64 格式
$publicKeyString = $keys_arr[$key_id]['base64'];
$publicKeyString = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($publicKeyString, 64, "\n", true) . "\n-----END PUBLIC KEY-----";

// 公钥的pem 格式
$publicKeyString = $keys_arr[$key_id]['pem'];

var_dump(verifySignature($message, $signature, $publicKeyString));


/**
------------ output ---------
array(2) {
  'code' =>
  int(0)
  'message' =>
  string(8) "success!"
}
*/
