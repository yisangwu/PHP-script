<?php

/* 
图片合并需要
1   下载   php_imagick_st-Q8.dll   
并将其放入 php/ext/
 2 在php.ini 中加入
    extension=php_imagick_st-Q8.dll          
 重启apache (web 服务器)
3 访问phpinfo 
*/
set_time_limit(0);

$start = microtime( TRUE );
$dir = "img/";
$imgname_arr = array();
// Open a directory, and read its contents
if (is_dir($dir)){
	if ($dh = opendir($dir)){
		while (($file = readdir($dh)) !== false){
			if( $file=='.'||$file=='..'){
				continue;
			}
			$imgname_arr[] = $dir.$file;
		}
		closedir($dh);
	}
}
//sort pic
krsort( $imgname_arr );

//调用函数生成gif图片
get_img( $imgname_arr, 'gif', (count($imgname_arr)%11) * 5, '', '',0 );

echo microtime(TRUE)-$start;

/**
 * 图片合并,生成gif动态
 * 
 * @param  array   	$filelist 文图片文件列表
 * @param  string  	$type     生成的文件类型，默认 gif
 * @param  int 		$num      gif帧数
 * @param  string  	$qian     gif前缀名
 * @param  string  	$path     保存的路径
 * @param  int 		$is       是否预览
 * @return boolean
 */
function get_img( $filelist=array(), $type='gif', $num=0, $qian='', $path='', $is=0 ){
	//初始化类
	$animation = new Imagick(); 

	//设置生成的格式
	$animation->setFormat( $type );   

	foreach ( $filelist as $file ){  
		$image = new Imagick();
		$image->readImage( $file );  	 //合并图片
		$animation->addImage( $image );  //加入到刚才建立的对象
		$animation->setImageDelay( $num ); //设定图片的帧数 20 would be 20/100 of a second aka 1/5th of a second.
		unset( $image );  			//消除内存里的图像资源
	}  

	//显示在浏览器
	//header("Content-Type: image/gif"); 
	//echo $animation->getImagesBlob(); 


	//生成gif图片
	//header( "Content-Type: image/gif" );
	 $animation->writeImages( "360.gif",TRUE ); //文件存储。不能使用writeImage，因为是多帧的
	
	//新图片文件名组合
/*	$images = $qian . time(). '.' . $type;
	
	//生成图片
	$animation->writeImages( $images,true ); 
	
	//保存都指定目录
	copy($images, $path . $images);

	//是否预览
	if($is)
	{
		echo '已生成gif图片: ' . $images . '<br />';
		echo "<img src='" . $path . $images . "' />";
	}
	else
	{
		echo '已生成gif图片: ' . $images . '<br />';
	}
	
	//删除最先保存的图片
	unlink($images);*/
}

// header("Content-Type: image/gif"); 
// echo $out_image->getImagesBlob(); 