<?php

require_once '../lib/Controller.php';

class MapsController extends Controller
{
	public function __construct($app, $db)
	{
		parent::__construct($app, $db);
		
		$obj = $this;
		$app->get('/maps', function () use ($obj)
		{
			$params = array(
				'maps' => array());
			
			$results = $obj->m_Database->ExecuteQuery(
				"select game_map, COUNT(GAME_ID) as play_count from GAMES group by GAME_MAP order by GAME_MAP asc;");
			if ($results)
			{
				while ($row = $results->GetNextRow())
				{
					$item = array(
						'thumb' => '/img/maps/' . urlencode($row['game_map']) . '.jpg',
						'name' => $row['game_map'],
						'playcount' => $row['play_count']
						);
						
					$params['maps'][] = $item;
				}
			}
			
			$obj->RenderPage('maps.html', $params);
		});
	}
}

?>