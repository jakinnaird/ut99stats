<?php

require_once '../lib/Controller.php';
require_once 'Player.php';

class PlayerController extends Controller
{
	public function __construct($app, $db)
	{
		parent::__construct($app, $db);
		
		$obj = $this;
		$app->get('/player/:id', function ($id) use ($obj)
		{
			$player = new Player($obj->m_Database, $id);
			
			$params = array(
				'player' => $player->GetStats(),
				'weaponstable' => $player->GetWeaponStatsTable(),
				'powerups' => $player->GetPowerups(),
				'weeklyresults' => $player->GetWeeklyResults()
			);
			
			$obj->RenderPage('player.html', $params);
		});
	}
}

?>