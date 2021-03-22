<?php

require_once '../lib/Controller.php';
require_once 'Map.php';

class MapController extends Controller
{
	public function __construct($app, $db)
	{
		parent::__construct($app, $db);
		
		$obj = $this;
		$app->get('/map/:name', function ($name) use ($obj)
		{
			$map = new Map($obj->m_Database, $name);
			
			$params = array(
				'controls' => $map->GetControls(),
				'info' => $map->GetInfo(),
				'winners' => $map->GetWinners());

			$obj->RenderPage('map.html', $params);
		});
	}
}

?>