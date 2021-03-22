<?php

require_once 'Game.php';

class Map
{
	private $m_Database;
	private $m_MapName;

	private $m_Controls;
	private $m_Info;
	private $m_Winners;
	
	public function __construct($db, $mapname)
	{
		$this->m_Database = $db;
		$this->m_MapName = $mapname;
		
		$this->m_Controls = array();
		$this->m_Info = array();
		$this->m_Winners = array();
	}
	
	public function GetControls()
	{
		$database = $this->m_Database;
		$mapname = $this->m_MapName;
		
		if (empty($this->m_Controls))
		{
			$this->m_Controls['prev'] = $mapname;
			$this->m_Controls['next'] = $mapname;
			
			$results = $database->ExecuteQuery(
				"select distinct(game_map) from games;");
			if ($results)
			{
				while ($row = $results->GetNextRow())
				{
					if (strcasecmp($row['game_map'], $mapname) < 0)
					{
						$this->m_Controls['prev'] = $row['game_map'];
					}
					else
					{
						if ($row = $results->GetNextRow())
						{
							$this->m_Controls['next'] = $row['game_map'];
							break;
						}
					}
				}
			}
		}
		
		return $this->m_Controls;
	}
	
	public function GetInfo()
	{
		$mapname = $this->m_MapName;
		
		if (empty($this->m_Info))
		{
			$this->m_Info['name'] = $mapname;
			$this->m_Info['thumb'] = '/img/maps/' . urlencode($mapname) . '.jpg';
			
			$results = $this->m_Database->ExecuteQuery(
				sprintf("select COUNT(*) as play_count, SUM(GAME_LENGTH) as total_time, min(game_length) as fast_time, avg(game_length) as avg_time from GAMES where GAME_MAP = '%s' and GAME_END <> 'mapchange';", 
				$mapname));
			if ($results)
			{
				while ($row = $results->GetNextRow())
				{
					$this->m_Info['playcount'] = $row['play_count'];
					$this->m_Info['totalplaytime'] = $row['total_time'];
					$this->m_Info['avgtime'] = $row['avg_time'];
					$this->m_Info['fasttime'] = $row['fast_time'];
				}
			}
			
			$results = $this->m_Database->ExecuteQuery(
				sprintf("select count(*) as frags from KILLS where SUICIDE = 0 and GAME_ID in (select GAME_ID from GAMES where GAME_MAP = '%s' and GAME_END <> 'mapchange');",
				$mapname));
			if ($results)
			{
				while ($row = $results->GetNextRow())
				{
					$this->m_Info['totalkills'] = $row['frags'];
				}
			}
		}
		
		return $this->m_Info;
	}
	
	public function GetWinners()
	{
		$mapname = $this->m_MapName;
		$colorlist = array();
		
		if (empty($this->m_Winners))
		{
			$games = $this->m_Database->ExecuteQuery(
				sprintf("select game_id from GAMES where GAME_MAP = '%s' and GAME_END <> 'mapchange';",
				$mapname));
			if ($games)
			{
				while ($row = $games->GetNextRow())
				{
					// create a game object to determine the winner
					$game = new Game($this->m_Database, $row['game_id']);
					$results = $game->GetPreview();
					
					if (!array_key_exists($results['winner'], $this->m_Winners))
					{
						// Generate a random color for this player
						$color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
						
						// Check if the color exists
						while (in_array($color, $colorlist))
							$color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
						
						$colorlist[] = $color; // add the color to the list

						$this->m_Winners[$results['winner']] = array(
							'name' => $results['winner'],
							'color' => $color,
							'wins' => 1
							);
					}
					else
					{
						$this->m_Winners[$results['winner']]['wins'] += 1;
					}
				}
			}
		}
		
		return $this->m_Winners;
	}
}

?>