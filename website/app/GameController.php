<?php

require_once '../lib/Controller.php';
require_once 'Player.php';
require_once 'Game.php';

class GameController extends Controller
{
	public function __construct($app, $db)
	{
		parent::__construct($app, $db);
		
		$obj = $this;
		$app->get('/game/:id', function ($id) use ($obj)
		{
			$game = new Game($obj->m_Database, $id);
			
			$params = array(
				'controls' => $game->GetControls(),
				'preview' => $game->GetPreview(),
				'results' => $game->GetResults(),
				'powerups' => $game->GetPowerups(),
				'timeline' => $game->GetTimeline(),
				'matchup' => $game->GetMatchup()
			);
			
			$obj->RenderPage('game.html', $params);
		});
	}
}

?>
