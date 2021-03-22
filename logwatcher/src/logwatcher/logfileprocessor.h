#ifndef _logfileprocessor_h_
#define _logfileprocessor_h_

#include "wx/arrstr.h"
#include "wx/string.h"

#include <map>
#include <vector>

class LogFileProcessor
{
private:
	struct Game
	{
		wxString game_name;
		wxString game_map;
		wxString game_date;
		wxString game_end;
		wxString frag_limit;
		wxString time_limit;

		wxFloat32 start_time;
		wxFloat32 end_time;

		bool started;

		Game(void)
		{
			start_time = 0;
			end_time = 0;

			started = false;
		}
	};

	struct Player
	{
		wxString player_name;
		wxFloat32 start_time;
		wxFloat32 stop_time;

		Player(void)
		{
			start_time = 0;
			stop_time = 0;
		}
	};

	struct Kill
	{
		wxFloat32 game_time;
		wxString killer;
		wxString victim;
		wxString weapon;
		wxUint8 suicide;

		Kill(void)
		{
			game_time = 0;
			suicide = 0;
		}
	};

	struct Item
	{
		wxFloat32 game_time;
		wxString player;
		wxString item_type;
	};


	typedef std::map<wxUint32, Player> playermap_t;
	typedef std::vector<Kill> killvec_t;
	typedef std::vector<Item> itemvec_t;

	Game m_Game;
	playermap_t m_Players;
	killvec_t m_Kills;
	itemvec_t m_Items;

public:
	LogFileProcessor(void);
	~LogFileProcessor(void);

	bool Process(const wxString &path,
		wxArrayString &gamesql,
		wxArrayString &playedsql,
		wxArrayString &killsql,
		wxArrayString &itemsql);

private:
	void ProcessGameEvent(const wxArrayString &tokens);
	void ProcessPlayerEvent(const wxArrayString &tokens);
	void ProcessKillEvent(const wxArrayString &tokens);
	void ProcessItemEvent(const wxArrayString &tokens);

	wxString& SearchAndReplace(wxString &_in, wxChar find, wxChar replace);
};

#endif
