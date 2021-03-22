<?php
return array(
	'slim' => array(
		'mode' => 'development',
		'debug' => true,
		'templates.path' => __DIR__ . '/../templates',
		'log.enabled' => true,
		'log.level' => \Slim\Log::DEBUG,
		'log.path' => __DIR__ . '/../log/utstats.log',
		'locales' => array('value' => '(en|fr)')
	),
	
	'twig' => array(
    'debug' => true,
    'cache' => __DIR__ . '/../cache'
    ),
	
	'pdo' => array(
		'host' => 'sql2k8',
		'port' => '1494',
		'database' => 'utstats',
		'user' => 'utstats',
		'pass' => 'utstats'
	),
);
?>
