<?php

class Weapon
{
	private $m_Database;
	private $m_Name;
/*	private $m_Kills;
	private $m_Deaths;
*/	
	public function __construct($database, $name)
	{
		$this->m_Database = $database;
		$this->m_Name = $name;
/*		$this->m_Kills = array();
		
		// Enum all players
		$players = $database->ExecuteQuery(
			"select distinct (player_name) from played order by player_name asc;");
		if ($players)
		{
			while ($player = $players->GetNextRow())
			{
				// Get the total kills per player
				$kills = $database->ExecuteScalarQuery(
					sprintf("select count(kill_id) as kills from KILLS where WEAPON = '%s' and KILLER = '%s' and suicide = 0;",
					$name, $player['player_name']));
					
				$this->m_Kills[$player['player_name']] = $kills;
									
				// Get the total deaths per player
				$deaths = $database->ExecuteScalarQuery(
					sprintf("select count(kill_id) as deaths from KILLS where WEAPON = '%s' and VICTIM = '%s';",
					$name, $player['player_name']));

				$this->m_Deaths[$player['player_name']] = $deaths;
			}
		}
*/	}
	
	public function GetName()
	{
		return $this->m_Name;
	}
	
	public function GetKills($killer, $victim)
	{
		return $this->m_Database->ExecuteScalarQuery(
			sprintf("select count(kill_id) from kills where weapon = '%s' and killer = '%s' and victim = '%s';",
				$this->m_Name, $killer, $victim));

		/*return $this->m_Kills[$player];*/
	}
	
	public function GetDeaths($killer, $victim)
	{
		/*return $this->m_Deaths[$player];*/
	}
}

?>
