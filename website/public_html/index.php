<?php

	// Load the Slim framework
	require '../lib/Slim/Slim.php';
	\Slim\Slim::registerAutoloader();

	// Load the Twig template library and Slim\View controller
	require '../lib/Twig/Autoloader.php';
	require '../lib/Slim/Views/Twig.php';

	// Load the configuration
	$g_Config = require_once __DIR__ . '/../config/config.php';

	// Register the TWIG middleware
	$g_Config['slim']['view'] = new Slim\Views\Twig();
	
	// Create the log writer
	if (isset($g_Config['slim']['log.enabled']) &&
		$g_Config['slim']['log.enabled'] == true)
	{
		if (isset($g_Config['slim']['log.path']))
			$logpath = $g_Config['slim']['log.path'];
		else
			$logpath = '../log/utstats.log';
			
		$g_Config['slim']['log.writer'] = new \Slim\LogWriter(fopen($logpath, 'a+'));
	}

	// Create the Slim app
	$g_App = new Slim\Slim($g_Config['slim']);
		
	// Keep flash messages across requests
	$g_App->add(new \Slim\Middleware\SessionCookie(
		array('expires' => '24 hours')));
	$g_App->flashKeep();

	// Set the required Twig parameters
	$view = $g_App->view();
	$view->parserDirectory = __DIR__ . '/../lib/Twig';
	$view->parserOptions = $g_Config['twig'];
	$view->parserExtensions = array(new Slim\Views\TwigExtension());

	// Connect to the database
	require '../lib/Database.php';
	$g_Database = new MSSQLDatabase($g_Config['pdo']);
	
	require '../app/IndexController.php';
	new IndexController($g_App, $g_Database);
	
	require '../app/PlayerController.php';
	new PlayerController($g_App, $g_Database);
	
	require '../app/GameController.php';
	new GameController($g_App, $g_Database);
	
	require '../app/MapsController.php';
	new MapsController($g_App, $g_Database);
	
	require '../app/MapController.php';
	new MapController($g_App, $g_Database);
	
	// Run the application
	$g_App->run();

?>
