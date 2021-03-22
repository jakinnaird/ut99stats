<?php

require_once 'Weapon.php';

class Player
{
	private $m_Database;
	private $m_PlayerName;

	private $m_GamesPlayed;
	private $m_GamesWon;
	private $m_Kills;
	private $m_Deaths;
	private $m_FastestWinMap;
	private $m_FastestWinGameId;
	private $m_FastestWinTime;
	private $m_TotalWinTime;
	private $m_FavoriteWeapon;
	private $m_FavoriteWeaponKills;
	private $m_BestMap;
	private $m_BestMapWins;
	
	private $m_RankingInfo;
	private $m_Stats;
	private $m_Powerups;
	private $m_WeaponTable;
	private $m_WeeklyResults;
	
	public function __construct($database, $playername)
	{
		$this->m_Database = $database;
		$this->m_PlayerName = $playername;
		$this->m_GamesWon = 0;
		$this->m_GamesPlayed = 0;
		$this->m_Kills = 0;
		$this->m_Deaths = 0;
		
		$this->m_RankingInfo = array();
		$this->m_Stats = array();
		$this->m_Powerups = array();
		$this->m_WeaponTable = array();
		$this->m_WeeklyResults = array();
	}
	
	public function GetRankingInfo()
	{
		$database = $this->m_Database;
		$playername = $this->m_PlayerName;
		
		if (empty($this->m_RankingInfo))
		{
			// Build the player statistics
			$games = $database->ExecuteQuery(
				sprintf("select game_id from played where player_name='%s'", $playername));
			if ($games)
			{
				while ($row = $games->GetNextRow())
				{
					// We played this game too
					$this->m_GamesPlayed++;
					
					// Get the number of player kills in this game
					$kills = $database->ExecuteScalarQuery(
						sprintf("select count(kill_id) from kills where game_id = %d and killer = '%s' and suicide = 0;",
							$row['game_id'], $playername));
					if ($kills)
					{
						// Update our kill count
						$this->m_Kills += $kills;
					}
					
					// Get the number of player deaths in this game
					$deaths = $database->ExecuteScalarQuery(
						sprintf("select count(kill_id) from kills where game_id = %d and victim = '%s';",
							$row['game_id'], $playername));
					if ($deaths)
					{
						// Update our kill count
						$this->m_Deaths += $deaths;
					}

					// Check if we won this game
					$gameinfo = $database->ExecuteQuery(
						sprintf("select frag_limit, game_map, game_length from GAMES where GAME_ID = %d and GAME_END = 'fraglimit';",
						$row['game_id']));
					if ($gameinfo)
					{
						while ($gr = $gameinfo->GetNextRow())
						{
							// did we win?
							if ($kills >= $gr['frag_limit'])
							{
								$this->m_GamesWon++;
								
								$this->m_TotalWinTime += $gr['game_length'];
								
								// Is this the fastest win?
								if (!isset($this->m_FastestWinTime)) // first pass
								{
									$this->m_FastestWinTime = $gr['game_length'];
									$this->m_FastestWinMap = $gr['game_map'];
									$this->m_FastestWinGameId = $row['game_id'];
								}
								else	// previously set
								{
									if ($gr['game_length'] < $this->m_FastestWinTime)
									{
										$this->m_FastestWinTime = $gr['game_length'];
										$this->m_FastestWinMap = $gr['game_map'];
										$this->m_FastestWinGameId = $row['game_id'];
									}
								}
							}
						}
					}
					
					// @TODO
					// Handle time limit games
				}
			}

			// Calculate any last stats			
			$winpercent = 0;
			if ($this->m_GamesPlayed > 0)
				$winpercent = round($this->m_GamesWon / $this->m_GamesPlayed * 100.0, 2);

			$killdeath = 0;
			if ($this->m_Deaths > 0)
				$killdeath = round($this->m_Kills / $this->m_Deaths, 2);
			else
				$killdeath = $this->m_Kills;

			$this->m_RankingInfo = array(
				'name' => $this->m_PlayerName,
				'played' => $this->m_GamesPlayed, 
				'won' => $this->m_GamesWon, 
				'winpercent' => $winpercent, 
				'kills' => $this->m_Kills, 
				'deaths' => $this->m_Deaths, 
				'killdeath' => $killdeath
			);

		}
		
		return $this->m_RankingInfo;
	}
	
	public function GetStats()
	{
		$playername = $this->m_PlayerName;
		$database = $this->m_Database;
		
		if (empty($this->m_Stats))
		{
			if (empty($this->m_RankingInfo))
				$this->GetRankingInfo();

			// Determine the favorite weapon
			$favwep = $database->ExecuteQuery(
				sprintf("select TOP 1 Weapon, count(kill_id) as totalfrags from KILLS where KILLER = '%s' and VICTIM <> '%s' and SUICIDE = 0 group by WEAPON order by totalfrags desc;",
				$playername, $playername));
			if ($favwep)
			{
				while ($fwr = $favwep->GetNextRow())
				{
					$this->m_FavoriteWeapon = $fwr['Weapon'];
					$this->m_FavoriteWeaponKills = $fwr['totalfrags'];
				}
			}

			$avgwintime = 0;
			if ($this->m_GamesWon > 0)
				$avgwintime = round($this->m_TotalWinTime / $this->m_GamesWon, 0);

			$this->m_Stats = array(
				'name' => $this->m_PlayerName,
				'fastwin' => $this->m_FastestWinMap,
				'fastwingameid' => $this->m_FastestWinGameId,
				'fastwintime' => $this->m_FastestWinTime,
				'avgwintime' => $avgwintime,
				'favwep' => $this->m_FavoriteWeapon,
				'favwepurl' => urlencode($this->m_FavoriteWeapon),
				'favwepkills' => $this->m_FavoriteWeaponKills
			);
		}
		
		return $this->m_Stats;
	}
	
	public function GetWeaponStatsTable()
	{
		$playername = $this->m_PlayerName;
		$database = $this->m_Database;

		if (empty($this->m_WeaponTable))
		{
			$weaponlist = array();
			$playerlist = array();
						
			// Enum all weapons
			$weapons = $database->ExecuteQuery(
				"select distinct (weapon) from kills order by WEAPON asc;");
			if ($weapons)
			{
				while ($wep = $weapons->GetNextRow())
				{
					$weaponlist[] = new Weapon($database, $wep['weapon']);
				}
			}		

			// Enum all players
			$players = $database->ExecuteQuery(
				"select distinct (player_name) from played order by player_name asc;");
			if ($players)
			{
				while ($player = $players->GetNextRow())
				{
					$playerlist[] = $player['player_name'];
				}
			}		

			// Table header
			$header = $playerlist;
			$this->m_WeaponTable['header'] = $header;

			// Table body
			$body = array();
			foreach ($weaponlist as $weapon)
			{
				$row = array(
					array(
						'name' => $weapon->GetName(), 
						'url' => '/weapon/' . urlencode($weapon->GetName())
					)
				);
				
				$totalkills = 0;
				
				foreach ($playerlist as $player)
				{
					$numkills = $weapon->GetKills($this->m_PlayerName, $player);
					$row[] = $numkills;

					if ($player != $this->m_PlayerName)
						$totalkills += $numkills;
				}
				
				$row[] = $totalkills;
				$body[] = $row;
			}
			$this->m_WeaponTable['body'] = $body;
			
			// Table footer
			$footer = array();
			foreach ($playerlist as $player)
			{
				$totalkills = 0;
				foreach ($weaponlist as $weapon)
				{
					$totalkills += $weapon->GetKills($this->m_PlayerName, $player);
				}
				
				$footer[] = $totalkills;
			}
			$this->m_WeaponTable['footer'] = $footer;
		}
		
		return $this->m_WeaponTable;
	}
	
	public function GetPowerups()
	{
		$playername = $this->m_PlayerName;
		$database = $this->m_Database;

		if (empty($this->m_Powerups))
		{			
			// Get all the powerups
			$powerups = $database->ExecuteQuery(
				sprintf("select COUNT(case when ITEM_TYPE = 'Damage Amplifier' then 1 end) as dmg_amp, COUNT(case when ITEM_TYPE = 'ShieldBelt' then 1 end) as shield_belt, COUNT(case when ITEM_TYPE = 'Super Health Pack' then 1 end) as keg, COUNT(case when ITEM_TYPE = 'AntiGrav Boots' then 1 end) as boots, COUNT(case when ITEM_TYPE = 'Invisibility' then 1 end) as invis from ITEMS where PLAYER = '%s' group by PLAYER;",
				$playername));
			if ($powerups)
			{
				if ($row = $powerups->GetNextRow())
				{
					$this->m_Powerups[] = array(
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
	
	public function GetWeeklyResults()
	{
		$playername = $this->m_PlayerName;
		$database = $this->m_Database;

		if (empty($this->m_WeeklyResults))
		{			
			$days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
			foreach ($days as $day)
			{
				$this->m_WeeklyResults[$day] = array(
					'name' => $day,
					'played' => 0,
					'won' => 0);

				$gamelist = $database->ExecuteQuery(
					sprintf("select g.game_id from games g inner join played p on p.game_id = g.game_id where DATENAME(weekday, g.game_date) = '%s' and p.player_name = '%s'",
					$day, $playername));
				if ($gamelist)
				{
					while ($row = $gamelist->GetNextRow())
					{
						$this->m_WeeklyResults[$day]['played']++;
						$game = new Game($database, $row['game_id']);
						$results = $game->GetPreview();
						if ($results['winner'] == $playername)
							$this->m_WeeklyResults[$day]['won']++;
					}
				}
			}
		}
		
		return $this->m_WeeklyResults;	
	}
}

?>