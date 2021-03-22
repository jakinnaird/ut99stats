<?php

class Game
{
	private $m_Database;
	private $m_GameId;
	
	private $m_Controls;
	private $m_Preview;
	private $m_Results;
	private $m_Powerups;
	private $m_Timeline;
	private $m_Matchup;
	
	public function __construct($database, $gameid)
	{
		$this->m_Database = $database;
		$this->m_GameId = $gameid;
		
		$this->m_Controls = array();
		$this->m_Preview = array();
		$this->m_Results = array();		
		$this->m_Powerups = array();
		$this->m_Timeline = array();
		$this->m_Matchup = array();
	}
	
	public function GetControls()
	{
		$database = $this->m_Database;
		$gameid = $this->m_GameId;
		
		if (empty($this->m_Controls))
		{
			$this->m_Controls['prev'] = 0;
			$this->m_Controls['next'] = $gameid;
			
			$results = $database->ExecuteQuery(
				"select game_id from games;");
			if ($results)
			{
				while ($row = $results->GetNextRow())
				{
					if ($row['game_id'] < $gameid)
					{
						$this->m_Controls['prev'] = $row['game_id'];
					}
					else
					{
						if ($row = $results->GetNextRow())
						{
							$this->m_Controls['next'] = $row['game_id'];
							break;
						}
					}
				}
			}
		}
		
		return $this->m_Controls;
	}
	
	public function GetPreview()
	{
		$database = $this->m_Database;
		$gameid = $this->m_GameId;
		
		if (empty($this->m_Preview))
		{
			$this->m_Preview['gameid'] = $gameid;
			
			// Get the map and play time
			$gameinfo = $database->ExecuteQuery(
				sprintf("select game_map, game_length, game_date from games where game_id = %d",
				$gameid));
			if ($gameinfo)
			{
				while ($row = $gameinfo->GetNextRow())
				{
					$this->m_Preview['name'] = $row['game_map'];
					$this->m_Preview['time'] = $row['game_length'];
					$this->m_Preview['date'] = date_format($row['game_date'], 'Y-m-d');		
				}
				
				// Build the thumbnail
				$this->m_Preview['thumb'] = '/img/maps/' . urlencode($this->m_Preview['name']) . '.jpg';
			}
			
			// Check who won this game
			$this->m_Preview['winner'] = 'None';
			
			// Is it a fraglimit game?
			$fraglimit = $database->ExecuteScalarQuery(
				sprintf("select frag_limit from GAMES where GAME_ID = %d and GAME_END = 'fraglimit';",
				$gameid));
			if ($fraglimit > 0)
			{
				// who won?
				$playerlist = $database->ExecuteQuery(
					sprintf("select killer as player, count(case when suicide = 0 then 1 end) - count(case when suicide = 1 then 1 end) as total from KILLS where GAME_ID = %d group by KILLER order by total desc;", 
					$gameid));
				if ($playerlist)
				{
					while ($row = $playerlist->GetNextRow())
					{
						if ($row['total'] == $fraglimit)
						{
							$this->m_Preview['winner'] = $row['player'];
							break;
						}
					}
				}
			}

			// @TODO
			// Handle time limit games
		}
		
		return $this->m_Preview;
	}
	
	public function GetResults()
	{
		$database = $this->m_Database;

		if (empty($this->m_Results))
		{
			$results = $database->ExecuteQuery(
				sprintf("select killer as player, count(case when suicide = 0 then 1 end) - count(case when suicide = 1 then 1 end) as kills, (select count(*) from KILLS where GAME_ID = %d and VICTIM = k.killer) as deaths, (select sum(play_time) from PLAYED where GAME_ID = %d and PLAYER_NAME = k.KILLER group by PLAYER_NAME) as play_time from KILLS k where GAME_ID = %d group by killer order by kills desc;",
				$this->m_GameId, $this->m_GameId, $this->m_GameId));
			if ($results)
			{
				while ($row = $results->GetNextRow())
				{
					$killratio = 0;
					if ($row['deaths'] != 0)
						$killratio = round($row['kills'] / $row['deaths'], 2);
					else
						$killratio = $row['kills'];
						
					$this->m_Results[] = array(
						'name' => $row['player'],
						'kills' => $row['kills'],
						'deaths' => $row['deaths'],
						'time' => $row['play_time'],
						'killratio' => $killratio,
						'killrate' => round($row['kills'] / ($row['play_time'] / 3600), 0)
					);
				}
			}
		}

		return $this->m_Results;
	}
	
	public function GetPowerups()
	{
		$database = $this->m_Database;

		if (empty($this->m_Powerups))
		{
			$results = $database->ExecuteQuery(
				sprintf("select player, COUNT(case when ITEM_TYPE = 'Damage Amplifier' then 1 end) as dmg_amp, COUNT(case when ITEM_TYPE = 'ShieldBelt' then 1 end) as shield_belt, COUNT(case when ITEM_TYPE = 'Super Health Pack' then 1 end) as keg, COUNT(case when ITEM_TYPE = 'AntiGrav Boots' then 1 end) as boots, COUNT(case when ITEM_TYPE = 'Invisibility' then 1 end) as invis from ITEMS where GAME_ID = %d group by PLAYER order by PLAYER asc;",
				$this->m_GameId));
			if ($results)
			{
				while ($row = $results->GetNextRow())
				{
					$this->m_Powerups[] = array(
						'name' => $row['player'],
						'dmgamp' => $row['dmg_amp'],
						'belt' => $row['shield_belt'],
						'keg' => $row['keg'],
						'boots' => $row['boots'],
						'invis' => $row['invis']
					);
				}
			}
		}

		return $this->m_Powerups;
	}
	
	public function GetTimeline()
	{
		$database = $this->m_Database;

		if (empty($this->m_Timeline))
		{
			$events = array();
			
			$results = $database->ExecuteQuery(
				sprintf("select game_time, killer, (case when suicide = 1 then -1 else 1 end) as frag from KILLS where GAME_ID = %d order by killer asc, game_time asc;",
				$this->m_GameId));
			if ($results)
			{			
				while ($row = $results->GetNextRow())
				{
					$events[] = array(
						$row['game_time'],
						$row['killer'],
						$row['frag']);
				}
			}
			
			// here we need to collate the data into something we can graph
			$totalfrags = array();
			$colorlist = array();
			
			foreach ($events as $event)
			{
				list($gametime, $killer, $frag) = $event;
				
				if (!array_key_exists($killer, $this->m_Timeline))
				{
					// Generate a random color for this player
					$color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
					
					// Check if the color exists
					while (in_array($color, $colorlist))
						$color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
					
					$colorlist[] = $color; // add the color to the list
					
					// Initialize the array key
					$this->m_Timeline[$killer] = array(
						'name' => $killer,
						'color' => $color,
						'data' => array());
				}

				if (!array_key_exists($killer, $totalfrags))
				{
					// initialize this players kill tally
					$totalfrags[$killer] = 0;
				}
				
				$totalfrags[$killer] += $frag;
				
				$this->m_Timeline[$killer]['data'][$gametime] = $totalfrags[$killer];
			}
		}

		return $this->m_Timeline;
	}
	
	public function GetMatchup()
	{
		$database = $this->m_Database;

		if (empty($this->m_Matchup))
		{
			$this->m_Matchup['players'] = array();
			
			$victims = array();
			
			$results = $database->ExecuteQuery(
				sprintf("select victim, killer from KILLS where game_id = %d order by victim, killer;",
				$this->m_GameId));
			if ($results)
			{
				while ($row = $results->GetNextRow())
				{
					// Check if the player is in the list
					if (!in_array($row['killer'], $this->m_Matchup['players']))
					{
						$this->m_Matchup['players'][] = $row['killer'];
					}

					// Set up the victim table
					if (!array_key_exists($row['victim'], $victims))
					{
						$victims[$row['victim']] = array();
					}
					
					if (!array_key_exists($row['killer'], $victims[$row['victim']]))
					{
						$victims[$row['victim']][$row['killer']] = 1;
					}
					else
					{
						$victims[$row['victim']][$row['killer']] += 1;
					}
				}
			}

			// build the table
			$table = array();
			
			foreach ($victims as $victim => $killers)
			{
				// Make sure that all the players are part of the roster
				foreach ($this->m_Matchup['players'] as $player)
				{
					if (!in_array($player, $killers))
					{
						$killers[] = $player;
						//$table[$victim][$player] = 0;
					}
				}
			}
			
			var_dump($table);
			
			$this->m_Matchup['table'] = $table;
		}
		
		return $this->m_Matchup;
	}
}

?>