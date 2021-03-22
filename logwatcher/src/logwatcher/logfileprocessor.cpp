#include "wx/wxprec.h"

#ifdef __BORLANDC__
#pragma hdrstop
#endif

#ifndef WX_PRECOMP
#include "wx/wx.h"
#endif

#include "wx/filename.h"
#include "wx/txtstrm.h"
#include "wx/tokenzr.h"
#include "wx/wfstream.h"

#include "logfileprocessor.h"

#include <fstream>

class UTF16File
{
private:
	std::ifstream m_File;

public:
	UTF16File(const std::wstring &filename)
	{
		m_File.open(filename.c_str(), std::ios_base::binary);
	}

	~UTF16File(void)
	{
	}

	bool IsOk(void) { return (m_File.is_open() && m_File.good()); }
	bool Eof(void) { return m_File.eof(); }

	std::string ReadLine(const char _delim = '\n')
	{
		std::string _out;

		while (!m_File.eof())
		{
			char ch = 0;
			m_File.read(&ch, 1);

			if (ch == _delim)
				break;

			if (ch > 0 && (isblank(ch) || isprint(ch)))
				_out.push_back(ch);
		}

		return _out;
	}

private:
	int isblank(int c)
	{
		if (c == 0x09 || // tab
			c == 0x20)	// space
			return c;

		return 0;
	}

};

LogFileProcessor::LogFileProcessor(void)
{
}

LogFileProcessor::~LogFileProcessor(void)
{
}

bool LogFileProcessor::Process(const wxString &path,
							   wxArrayString &gamesql,
							   wxArrayString &playedsql,
							   wxArrayString &killsql,
							   wxArrayString &itemsql)
{
	UTF16File file_input(path.wc_str());
	if (!file_input.IsOk())
		return false;

	while (!file_input.Eof())
	{
		// Read the next line
		std::string line = file_input.ReadLine();

		// Tokenize it
		wxStringTokenizer tokenizer(line, wxT("\t"));
		wxArrayString tokens;
		while (tokenizer.HasMoreTokens())
		{
			tokens.push_back(tokenizer.GetNextToken());
		}

		if (tokens.GetCount() == 0)
			continue;	// something bad happened

		// Process the line
		if (tokens.Item(1) == wxT("info") ||
			tokens.Item(1) == wxT("map") ||
			tokens.Item(1) == wxT("game") ||
			tokens.Item(1) == wxT("game_start") ||
			tokens.Item(1) == wxT("game_end"))
		{
			ProcessGameEvent(tokens);
		}
		else if (tokens.Item(1) == wxT("player"))
		{
			ProcessPlayerEvent(tokens);
		}
		else if (tokens.Item(1) == wxT("teamkill") ||
			tokens.Item(1) == wxT("kill") ||
			tokens.Item(1) == wxT("suicide"))
		{
			ProcessKillEvent(tokens);
		}
		else if (tokens.Item(1) == wxT("item_get"))
		{
			ProcessItemEvent(tokens);
		}
	}

	// Assemble the SQL
	gamesql.push_back(wxString::Format(
		wxT("INSERT INTO GAMES (GAME_NAME, GAME_MAP, GAME_DATE, GAME_LENGTH, GAME_END, FRAG_LIMIT, TIME_LIMIT) VALUES ('%s', '%s', '%s', %d, '%s', %s, %s);"),
		m_Game.game_name, 
		m_Game.game_map, 
		m_Game.game_date,
		(int)(m_Game.end_time - m_Game.start_time), 
		m_Game.game_end,
		m_Game.frag_limit, 
		m_Game.time_limit));

	for (playermap_t::iterator player = m_Players.begin();
		player != m_Players.end(); player++)
	{
		playedsql.push_back(wxString::Format(
			wxT("INSERT INTO PLAYED (GAME_ID, PLAYER_NAME, PLAY_TIME) VALUES (%s, '%s', %d);"),
			wxT("(SELECT MAX(GAME_ID) FROM GAMES)"),
			player->second.player_name, 
			(int)(player->second.stop_time - player->second.start_time)));
	}

	for (killvec_t::iterator kill = m_Kills.begin();
		kill != m_Kills.end(); kill++)
	{
		killsql.push_back(wxString::Format(
			wxT("INSERT INTO KILLS (GAME_ID, GAME_TIME, KILLER, VICTIM, WEAPON, SUICIDE) VALUES (%s, %d, '%s', '%s', '%s', %d);"),
			wxT("(SELECT MAX(GAME_ID) FROM GAMES)"),
			(int)(kill->game_time - m_Game.start_time),
			kill->killer,
			kill->victim,
			kill->weapon,
			kill->suicide));
	}

	for (itemvec_t::iterator item = m_Items.begin();
		item != m_Items.end(); item++)
	{
		itemsql.push_back(wxString::Format(
			wxT("INSERT INTO ITEMS (GAME_ID, GAME_TIME, PLAYER, ITEM_TYPE) VALUES (%s, %d, '%s', '%s');"),
			wxT("(SELECT MAX(GAME_ID) FROM GAMES)"),
			(int)(item->game_time - m_Game.start_time),
			item->player,
			item->item_type));
	}

	return true;
}

void LogFileProcessor::ProcessGameEvent(const wxArrayString &tokens)
{
	if (tokens.Item(1) == wxT("game_start"))
	{
		double _time;
		tokens.Item(0).ToDouble((double*)&_time);
		m_Game.start_time = (wxFloat32)_time;

		m_Game.started = true;

		// Update the player start times
		for (playermap_t::iterator player = m_Players.begin();
			player != m_Players.end(); /*player++*/)
		{
			if (player->second.stop_time > 0 &&
				player->second.stop_time < m_Game.start_time)
				player = m_Players.erase(player); // Player disconnected before game start
			else
			{
				player->second.start_time = m_Game.start_time;
				player++;
			}
		}
	}
	else if (tokens.Item(1) == wxT("game_end"))
	{
		double _time;
		tokens.Item(0).ToDouble((double*)&_time);
		m_Game.end_time = (wxFloat32)_time;

		m_Game.started = false;

		// Update the player start times
		for (playermap_t::iterator player = m_Players.begin();
			player != m_Players.end(); player++)
		{
			if (player->second.stop_time > 0 &&
				player->second.stop_time < m_Game.end_time)
				continue; // player disconnected before game end, ignore
			else
				player->second.stop_time = m_Game.end_time;
		}

		m_Game.game_end = tokens.Item(2);
	}
	else if (tokens.Item(1) == wxT("info"))
	{
		if (tokens.Item(2) == wxT("Absolute_Time"))
		{
			wxString date = tokens.Item(3);
			m_Game.game_date = wxString::Format(wxT("%s %s"),
				SearchAndReplace(date.substr(0, 10), wxT('.'), wxT('-')),
				SearchAndReplace(date.substr(11, 8), wxT('.'), wxT(':')));
		}
	}
	else if (tokens.Item(1) == wxT("map"))
	{
		if (tokens.Item(2) == wxT("Name"))
		{
			wxString map_name = tokens.Item(3);
			wxFileName fn(map_name);
			m_Game.game_map = fn.GetName();//map_name.substr(0, map_name.length() - 4);
		}
	}
	else if (tokens.Item(1) == wxT("game"))
	{
		if (tokens.Item(2) == wxT("GameName"))
			m_Game.game_name = tokens.Item(3);
		else if (tokens.Item(2) == wxT("FragLimit"))
			m_Game.frag_limit = tokens.Item(3);
		else if (tokens.Item(2) == wxT("TimeLimit"))
			m_Game.time_limit = tokens.Item(3);
	}
}

void LogFileProcessor::ProcessPlayerEvent(const wxArrayString &tokens)
{
	if (tokens.Item(1) == wxT("player"))
	{
		double _time;
		tokens.Item(0).ToDouble((double*)&_time);

		if (tokens.Item(2) == wxT("Connect"))
		{
			wxUint32 player_id;
			tokens.Item(4).ToULong((unsigned long*)&player_id);

			m_Players[player_id].player_name = tokens.Item(3);
			m_Players[player_id].start_time = (wxFloat32)_time;
		}
		else if (tokens.Item(2) == wxT("Disconnect"))
		{
			wxUint32 player_id;
			tokens.Item(3).ToULong((unsigned long*)&player_id);
			m_Players[player_id].stop_time = (wxFloat32)_time;
		}
	}
}

void LogFileProcessor::ProcessKillEvent(const wxArrayString &tokens)
{
	Kill _kill;

	double _time;
	tokens.Item(0).ToDouble((double*)&_time);
	_kill.game_time = (wxFloat32)_time;

	if (tokens.Item(1) == wxT("kill") ||
		tokens.Item(1) == wxT("teamkill"))
	{
		unsigned long _killer, _victim;
		tokens.Item(2).ToULong(&_killer);
		tokens.Item(4).ToULong(&_victim);

		_kill.killer = m_Players[_killer].player_name;
		_kill.victim = m_Players[_victim].player_name;
		_kill.weapon = tokens.Item(3);
		_kill.suicide = 0;
	}
	else if (tokens.Item(1) == wxT("suicide"))
	{
		unsigned long _killer;
		tokens.Item(2).ToULong(&_killer);
		_kill.killer = m_Players[_killer].player_name;
		_kill.victim = m_Players[_killer].player_name;
		_kill.weapon = tokens.Item(3);
		_kill.suicide = 1;
	}
	else
		return;

	m_Kills.push_back(_kill);
}

void LogFileProcessor::ProcessItemEvent(const wxArrayString &tokens)
{
	Item _item;

	double _time;
	tokens.Item(0).ToDouble((double*)&_time);
	_item.game_time = (wxFloat32)_time;

	if (tokens.Item(1) == wxT("item_get"))
	{
		wxString item = tokens.Item(2);
		if (item == wxT("Damage Amplifier") ||
			item == wxT("ShieldBelt") ||
			item == wxT("AntiGrav Boots") ||
			item == wxT("Invisibility") ||
			item == wxT("Super Health Pack"))
		{
			unsigned long _player;
			tokens.Item(3).ToULong(&_player);

			_item.player = m_Players[_player].player_name;
			_item.item_type = item;
		}
		else
			return;
	}
	else
		return;

	m_Items.push_back(_item);
}

wxString& LogFileProcessor::SearchAndReplace(wxString &_in, 
											 wxChar find, wxChar replace)
{
	for (size_t pos = 0; pos < _in.length(); pos++)
	{
		if (_in[pos] == find)
			_in[pos] = replace;
	}

	return _in;
}
