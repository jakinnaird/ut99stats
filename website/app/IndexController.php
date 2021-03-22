<?php

require_once '../lib/Controller.php';
require_once 'Player.php';
require_once 'Game.php';

class IndexController extends Controller
{
	public function __construct($app, $db)
	{
		parent::__construct($app, $db);
		
		$obj = $this;
		$app->get('/', function () use ($obj)
		{
			// Generate list of today's games
			$games = array();
			$last_date = $obj->m_Database->ExecuteScalarQuery(
				'select max(game_date) from games');
			if ($last_date)
			{
				$gamelist = $obj->m_Database->ExecuteQuery(
					sprintf("select game_id from games where datediff(day, game_date, '%s') < 4 order by game_id desc;",
					date_format($last_date, 'Y-m-d')));
				if ($gamelist)
				{
					while ($row = $gamelist->GetNextRow())
					{
						$game = new Game($obj->m_Database, $row['game_id']);
						$games[] = $game->GetPreview();
					}
				}
			}
			
			// Generate player rankings
			$player_list = array();
			$playerlist_resultset = $obj->m_Database->ExecuteQuery('select distinct(player_name) from played');
			if ($playerlist_resultset)
			{
				while ($row = $playerlist_resultset->GetNextRow())
				{
					$player_list[] = $row['player_name'];
				}
			}

			$rankings = array();
			foreach ($player_list as $player)
			{
				$p = new Player($obj->m_Database, $player);
				$rankings[] = $p->GetRankingInfo();
			}
			
			// Generate the player progress data
			$progress = array(
				'days' => array(),
				'columns' => array());
			
			$player_wins = array();
			
			// Vicious hack because some games have no winners
			$player_list[] = 'None';
			
			// Populate the player wins with the players
			foreach ($player_list as $player)
			{
				$player_wins[$player] = 0;
				$progress['columns'][$player] = array($player);
			}
			
			$game_results = $obj->m_Database->ExecuteQuery("select game_id from games;");
			if ($game_results)
			{
				while ($row = $game_results->GetNextRow())
				{
					$game = new Game($obj->m_Database, $row['game_id']);
					
					// Update the player wins
					$preview = $game->GetPreview();

					$player_wins[$preview['winner']]++;
					
					$progress['days'][] = $preview['date'];

					// Update today's wins for all players
					foreach ($player_wins as $player => $wins)
					{
						$progress['columns'][$player][] = $wins;
					}
				}
			}
			
			$params = array('games' => $games,
				'rankings' => $rankings,
				'progress' => $progress);
	
			$obj->RenderPage('index.html', $params);
		});
	}
}

?>