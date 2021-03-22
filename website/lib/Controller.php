<?php

class Controller
{
	public $m_App;
	public $m_Database;
	
	public function __construct($app, $db)
	{
		$this->m_App = $app;
		$this->m_Database = $db;
	}

	public function RenderPage($path, $params = array())
	{
		$this->m_App->render($path, $params);
	}
}

?>
