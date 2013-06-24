<?php

$config['locale']='zh_CN';

// $config['session_handler'] = 'built_in';
$config['session_cookie'] = array(
	'lifetime' => 0,	//浏览器关闭
	'path' => '/',
	'domain' => NULL,
	);

// $config['session_path'] = '/tmp/session/';

$config['tmp_dir'] = sys_get_temp_dir().'/gini/';
// $config['session_name'] = 'gini-session';

// $config['24hour'] = FALSE;

$config['timezone'] = 'Asia/Shanghai';

$config['postmaster'] = [
	'address' => 'support@geneegroup.com',
	'name' => 'Genee'
];

$config['log'] = [
	'ident' => 'gini',
	'option' => LOG_ODELAY|LOG_PID,
	'facility' => LOG_USER,
]