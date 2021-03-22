<?php

class MSSQLDatabase
{
	private $m_Database;
	
	public function __construct($options)
	{
		$opts = array(
			'Database' => $options['database'],
			'UID' => $options['user'],
			'PWD' => $options['pass']);
			
		$database = sqlsrv_connect($options['host'], $opts);
		if ($database)
		{
			$this->m_Database = $database;
		}
		else
		{
			echo "Connection could not be established.<br />";
			die( print_r( sqlsrv_errors(), true));
     	}
	}
	
	public function ExecuteQuery($sql)
	{
		$query = sqlsrv_query($this->m_Database, $sql);
		if ($query)
			return new MSSQLResultSet($query);
		else
			return false;
	}
	
	public function ExecuteScalarQuery($sql)
	{
		$query = sqlsrv_query($this->m_Database, $sql);
		if ($query)
		{
			$row = sqlsrv_fetch_array($query, SQLSRV_FETCH_NUMERIC);
			return $row[0];			
		}
		else
			return false;
	}
}

class MSSQLResultSet
{
	private $m_Query;
	
	public function __construct($query)
	{
		$this->m_Query = $query;
	}
	
	public function GetNextRow()
	{
		return sqlsrv_fetch_array($this->m_Query, SQLSRV_FETCH_ASSOC);
	}
}

?>