<?php

/*

# 生成 RSA 私钥（传统格式的）
$ openssl genrsa -out rsa_private_key.pem 1024

# 生成 RSA 公钥(php和java都用转换前私钥生成公钥)
$ openssl rsa -in rsa_private_key.pem -pubout -out rsa_public_key.pem

*/



/**
 * 私钥
 * @var string
 */
$privateKeyString=
<<<EOF
-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQDRHaNFj+BW4g6A+0NOrPq1XOACEsCZUuJNJsq6JbcvN9JgpPKG
C6zlvEwiibsAk5NqLvYmdbqiIC41VJ/T4aq7RFc/k5eo2FJlfzoRKMIG9yVsp6mr
NLOkrDrbbN+GS02463L3oDhlZVncXfXWZNbeC0vrBzjM3QsZIqHYVQQc6QIDAQAB
AoGAO6fA1C9fSGnkyVbktKUUQHjmTrEa0KKcfHX8j24C/C1ojrl/lk3uXPuCnLe9
6UQwYbJT9lTPkUCs7fnePou6MH4acPsOHnwJ3PpQrO5H01Z8sZQkD/KIyk1LPaGl
8zvKlTOgrSg4h8JLk7i9PI8q2Yp75rquVZglQ7m6H1GXNiECQQDszsnMjAptFvnb
bs29wiuRSV7iqkigZTokLtJjJ1qw5fpNipJbAN/jhwAFEe1aMAno/rpru0l3EuGG
Z85M+pfzAkEA4hBODEnRU5D1fwUcp4LY5spOm2dk5h/LZJbI/5et0y2Qah+YFe0k
hgzV70dNUmjteYg3axzksUuipZE4bSoqswJBALlFSCjaX8XdtfnyBNGzunZe2ven
lk63I/fvEfc1cQT5yQ0lnz/HvWK72k4dKn/nGbnKoXtr+hxJD10ilgsv+/UCQGnt
g/TkHg8PTMmxJoUjnek/AOh24WOnoFHJCfQiKdRbdGEV3tjfXw7lMtXFTmkAO86H
0pgBWPPu4g685njYmlsCQGHxbxNr2ybRqgIuqmiNO7jldtcXMqzaF4w1BHS5C0mk
A6+i/KEJ5yLVv6lmDPO1WDbbt81cYO8RGEQ7DblIRoc=
-----END RSA PRIVATE KEY-----
EOF;



/**
 * 公钥
 * @var string
 */
$publicKeyString = 
<<<EOF
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDRHaNFj+BW4g6A+0NOrPq1XOAC
EsCZUuJNJsq6JbcvN9JgpPKGC6zlvEwiibsAk5NqLvYmdbqiIC41VJ/T4aq7RFc/
k5eo2FJlfzoRKMIG9yVsp6mrNLOkrDrbbN+GS02463L3oDhlZVncXfXWZNbeC0vr
BzjM3QsZIqHYVQQc6QIDAQAB
-----END PUBLIC KEY-----
EOF;

/**
 * 公钥的另一种写法，私钥也可以这样的格式写
 * 注意不换行
 * @var string
 */
$publicKeyString = "-----BEGIN PUBLIC KEY-----\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDRHaNFj+BW4g6A+0NOrPq1XOAC\nEsCZUuJNJsq6JbcvN9JgpPKGC6zlvEwiibsAk5NqLvYmdbqiIC41VJ/T4aq7RFc/\nk5eo2FJlfzoRKMIG9yVsp6mrNLOkrDrbbN+GS02463L3oDhlZVncXfXWZNbeC0vr\nBzjM3QsZIqHYVQQc6QIDAQAB\n-----END PUBLIC KEY-----";



/**
 * 使用私钥，签名数据，生成签名字符串
 * 
 * @param  string $data             待签名数据
 * @param  string $privateKeyString 私钥字符串
 * @return array
 */
function genSignature(string $data='', string $privateKeyString='')
{
    $data = trim($data);
    $privateKeyString = trim($privateKeyString);

    $ret_sign = ['code'=>0, 'signature'=> '', 'message'=> ''];

    if (empty($data) || !is_string($data) || empty($privateKeyString)) {
        $ret_verify['code'] = -1;
        $ret_sign['message'] = 'params error!';
        return $ret_sign;
    }
    // 加密
    $privateKey = openssl_pkey_get_private($privateKeyString);

    if ( !is_resource($privateKey)) {
        $ret_verify['code'] = -2;
        $ret_verify['message'] = 'privateKey error!';

        return $ret_verify;
    }

    $signature = null;
    if (openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        $ret_sign['signature'] = base64_encode($signature);
        $ret_sign['message'] = 'success!';

        return $ret_sign;
    }

    $ret_verify['code'] = -2;
    $ret_sign['message'] = openssl_error_string();

    return '';
}


/**
 * 验证签名
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


// 签名字符串数据
$data = '0123456789';

// 签名
$sign = genSignature($data, $privateKeyString);

print_r($sign);

// 验证签名
$verifier = verifySignature($data, $sign['signature'], $publicKeyString);

print_r($verifier);


/**
 * --- output
 * 
Array
(
    [code] => 0
    [signature] => q6nCWEEXHRubgZEFT3wkjVd5gmmDucBCAiX20HBrpSBxPevlxCTwkMZ+35nVpVoj+Rmz3m+5qWBZ2m0q8POoDFr5YPsANSos0cM1Nr1zC9ju6SRCBpRmiGKLxzniuehkrRyxbWf+rLthmiSDnQa/peWw5Y7hsVT68yR8AoCovRY=
    [message] => success!
)
Array
(
    [code] => 0
    [message] => success!
)

*/
