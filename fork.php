<?php
/**
 * PHP 多进程
 */

 /**
  * 1. 在 ps -ef 中可以看到进程
  * @var void
  */
 $ppid = posix_getpid();
 $pid  = pcntl_fork();
 if( $pid==-1 ){
    die('fork failed!');
 }
 if( $pid>0 ){
     cli_set_process_title( "我是父进程,我的进程id是{$ppid}." );
     sleep(30);
 }else{
     $cpid = posix_getpid();
     cli_set_process_title( "{$ppid}的子进程,我的进程id是{$cpid}." );
     sleep(30);
 }


/**
 * 2. 输出进程pid信息
 * @var void
 */
$parent_pid = posix_getpid();
$pid = pcntl_fork();
if( $pid == -1 ){
    exit("fork error");
}
//执行pcntl_fork的时候，子进程pid总是0，并且不会再fork出新的进程
if( $pid == 0 ){
		$child_pid = posix_getpid();
		echo "child process {$child_pid}\n";
}else{
    //父进程fork之后，返回的就是子进程的pid号，pid不为0
    echo "parent process {$parent_pid}\n";
}


/**
 * 3. 主进程创建多个子进程
 * @var void
 */
$parent_pid = posix_getpid(); //父进程pid（执行当前脚本的进程）
//脚本执行3次，每次fork一个子进程
for( $i=0;$i<3;$i++ ){
	$pid = pcntl_fork();
	if( $pid==-1){
		die('fork failed!');
	}
	if($pid){ 
		//parent 进程
		//如果这里exit，则只会fork一个子进程（脚本执行一次）
		cli_set_process_title('Here parent'.$parent_pid); //设置进程名称
	}else{ 
		//child 进程
		$child_pid = posix_getpid();
		// cli_set_process_title('Here child--'.$child_pid);
		file_put_contents($child_pid, '123456'.PHP_EOL,FILE_APPEND ); //每个子进程独立写一个文件
		exit(0);// 一定要注意退出子进程,否则pcntl_fork() 会被子进程再fork（脚本会再次被子进程执行，又生成子进程）
	}
}


/**
 * 4. fork两次:由子进程fork子进程,避免僵尸进程
 *  1.创建子进程，父进程退出
        pcntl_fork()返回一个整型值，在父进程里面返回的是子进程的id，子进程返回的是0，失败则返回-1。这样我们就可以根据这个来分别控制父进程和子进程执行任务。
    2.子进程创建会话
        这个是重要的一步，在这一步中该子进程会做这些事情：1.让进程摆脱原会话的控制；2.让进程摆脱员进程组的控制；3.让进程摆脱终端的控制。
    这里使用posix_setsid()来在这个子进程中创建会话,使得这个进程成为会话组组长。
 * @var void
 */
$pid = pcntl_fork();
if( $pid==-1 ){
	die( 'fork failed!' );
}
if($pid){
	exit('parent exit now,bye bye ~~');
}else{
	child_dosomething();
}

//子进程
function child_dosomething(){
    $sid = posix_setsid();  //调用posix_setsid()使 进程成为会话组长: 
    //echo $sid;
    for( $i=0;$i<2;$i++ ){  //循环两次，创建两个子进程，在worker里面创建
        worker($i); //worker里面fork子进程
    }
}

//worker 来fork
function worker($no){
    $pid = pcntl_fork();
    if( $pid == -1 ){
        exit('worker fork error');
    }
    if( $pid == 0 ){
        for( $i=0;$i<5;$i++ ){
            file_put_contents('log'.$no,posix_getpid()."--hello {$i}\n",FILE_APPEND);
        }
        exit(0);
    }else{
    	cli_set_process_title('Here parent'.posix_getpid() ); //设置进程名称
    }
}

sleep(30); //加sleep 可以在 ps -ef 中看到设置的进程名
