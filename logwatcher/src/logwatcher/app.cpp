#include "wx/wxprec.h"

#ifdef __BORLANDC__
#pragma hdrstop
#endif

#ifndef WX_PRECOMP
#include "wx/wx.h"
#endif

#include "wx/app.h"
#include "wx/base/xmlconf.h"
#include "wx/cmdline.h"
#include "wx/database/database.h"
#include "wx/dir.h"
#include "wx/ffile.h"
#include "wx/filename.h"
#include "wx/msw/regconf.h"

#include "logfileprocessor.h"

class LogWatcher : public wxAppConsole
{
private:
	wxCmdLineParser m_CmdLine;

	wxString m_LogPath;
	wxDatabase *m_Database;

public:
	LogWatcher(void)
	{
		m_CmdLine.AddOption(_("logpath"), wxEmptyString,
			_("Path to UT log files"), wxCMD_LINE_VAL_STRING, wxCMD_LINE_OPTION_MANDATORY);
		m_CmdLine.AddOption(_("dbhost"), wxEmptyString,
			_("Database host"), wxCMD_LINE_VAL_STRING, wxCMD_LINE_OPTION_MANDATORY);
		m_CmdLine.AddOption(_("dbname"), wxEmptyString,
			_("Database name"), wxCMD_LINE_VAL_STRING, wxCMD_LINE_OPTION_MANDATORY);
		m_CmdLine.AddOption(_("dbuser"), wxEmptyString,
			_("Database user"), wxCMD_LINE_VAL_STRING, wxCMD_LINE_OPTION_MANDATORY);
		m_CmdLine.AddOption(_("dbpass"), wxEmptyString,
			_("Database password"), wxCMD_LINE_VAL_STRING, wxCMD_LINE_OPTION_MANDATORY);
		
		m_Database = NULL;
	}

	~LogWatcher(void)
	{
	}

	bool OnInit(void)
	{
		wxHandleFatalExceptions();

#if defined(NDEBUG)
		wxFFile *logfile = new wxFFile(
			wxT("C:\\Users\\public\\Documents\\utlogwatcher.txt"), wxT("a+"));
		wxLog::SetActiveTarget(new wxLogStderr(logfile->fp()));
		wxLog::SetLogLevel(wxLOG_Message);
#else
		wxLog::SetLogLevel(wxLOG_Info);
#endif

		m_CmdLine.SetCmdLine(argc, argv);
		if (m_CmdLine.Parse() != 0)
			return false;

		wxString dbhost, dbname, dbuser, dbpass;
		m_CmdLine.Found(_("logpath"), &m_LogPath);
		m_CmdLine.Found(_("dbhost"), &dbhost);
		m_CmdLine.Found(_("dbname"), &dbname);
		m_CmdLine.Found(_("dbuser"), &dbuser);
		m_CmdLine.Found(_("dbpass"), &dbpass);

		// Check if the log path exists
		if (!wxDir::Exists(m_LogPath))
		{
			wxLogError(wxString::Format(
				_("Specified log path does not exist: '%s'"),
				m_LogPath));
			return false;
		}

		wxString dsn = wxString::Format(wxT("Driver=SQL Server;Server=%s;Database=%s;UID=%s;PWD=%s;"),
			dbhost, dbname, dbuser, dbpass);

		wxString err;
		wxXmlConfig dbconf;
		dbconf.Write(wxT("/ODBC/DbType"), wxT("MSSQL Server"));
		dbconf.Write(wxT("/ODBC/Connection"), dsn);

		try
		{
			m_Database = wxDatabase::GetDatabase(dbconf, &err);
			dbconf.DeleteAll();	// we don't want to store this anywhere

			if (m_Database == NULL)
			{
				wxLogError(err);
				return false;
			}
		}
		catch (wxDatabaseException &e)
		{
			wxLogError(wxString::Format(
				wxT("Unable to open database: %s"), 
				e.GetErrorMessage()));

			dbconf.DeleteAll();	// we don't want to store this anywhere
			return false;
		}

		return true;
	}

	int OnRun(void)
	{
		try
		{
			// Confirm that the specified path is good
			wxDir logpath(m_LogPath);
			if (!logpath.IsOpened())
			{
				wxLogError(wxString::Format(
					_("Unable to open log path: '%s'"),
					m_LogPath));
				return -1;
			}

			// Get the list of previous log files from the database
			wxPreparedStatement *stmt = m_Database->PrepareStatement(
				wxT("select LOGFILE_PATH from LOGFILES;"));
			wxDatabaseResultSet *rsLogfiles = stmt->RunQueryWithResults();

			// Push the logfile list into something more useful
			wxArrayString logfiles;
			while (rsLogfiles && rsLogfiles->Next())
			{
				logfiles.push_back(rsLogfiles->GetResultString(1));
			}
			rsLogfiles->Close();
			stmt->Close();

			// Search the log file path for new log files
			wxString logfile;
			bool cont = logpath.GetFirst(&logfile, wxT("*.log"),
				wxDIR_FILES);
			while (cont)
			{
				bool process = true;
				wxFileName fn(m_LogPath, logfile);

				// check this file against the database list
				for (size_t i = 0; i < logfiles.GetCount(); i++)
				{
					if (logfiles.Item(i) == fn.GetFullPath())
					{
						process = false;
						break;
					}
				}

				if (process)
					ProcessLogFile(fn.GetFullPath());

				cont = logpath.GetNext(&logfile);
			}

		}
		catch (wxDatabaseException &e)
		{
			wxLogError(wxString::Format(
				wxT("Database error: %s"), 
				e.GetErrorMessage()));
			return -1;
		}

		return 0;
	}

	int OnExit(void)
	{
		if (m_Database)
		{
			delete m_Database;
			m_Database = NULL;
		}

		return wxAppConsole::OnExit();
	}

	void OnFatalException(void)
	{
		wxLogError(_("Fatal exception"));
	}

	void ProcessLogFile(const wxString &path)
	{
		wxArrayString gamesql, playedsql, killsql, itemsql;
		LogFileProcessor lfp;

		wxLogMessage(wxString::Format(
			_("Processing log file '%s'"), path));

		bool update_db = lfp.Process(path, gamesql, playedsql, 
			killsql, itemsql);
		if (!update_db)
		{
			wxLogError(wxString::Format(_("Unable to process log file: '%s'"),
				path));
		}

		// Update the database
		if (update_db)
		{
			for (size_t i = 0; i < gamesql.GetCount(); i++)
			{
				wxLogStatus(gamesql.Item(i));
				
				try
				{
					m_Database->ExecuteUpdate(gamesql.Item(i));
				}
				catch (wxDatabaseException &e)
				{
					wxLogError(wxString::Format(
						wxT("Database error: %s"), 
						e.GetErrorMessage()));
				}
			}

			for (size_t i = 0; i < playedsql.GetCount(); i++)
			{
				wxLogStatus(playedsql.Item(i));

				try
				{
					m_Database->ExecuteUpdate(playedsql.Item(i));
				}
				catch (wxDatabaseException &e)
				{
					wxLogError(wxString::Format(
						wxT("Database error: %s"), 
						e.GetErrorMessage()));
				}
			}

			for (size_t i = 0; i < killsql.GetCount(); i++)
			{
				wxLogStatus(killsql.Item(i));

				try
				{
					m_Database->ExecuteUpdate(killsql.Item(i));
				}
				catch (wxDatabaseException &e)
				{
					wxLogError(wxString::Format(
						wxT("Database error: %s"), 
						e.GetErrorMessage()));
				}
			}

			for (size_t i = 0; i < itemsql.GetCount(); i++)
			{
				wxLogStatus(itemsql.Item(i));

				try
				{
					m_Database->ExecuteUpdate(itemsql.Item(i));
				}
				catch (wxDatabaseException &e)
				{
					wxLogError(wxString::Format(
						wxT("Database error: %s"), 
						e.GetErrorMessage()));
				}
			}
		}

		try
		{
			// We add the log file to the database no matter what
			m_Database->ExecuteUpdate(wxString::Format(
					wxT("INSERT INTO LOGFILES (LOGFILE_PATH) VALUES ('%s');"),
					path));
		}
		catch (wxDatabaseException &e)
		{
			wxLogError(wxString::Format(
				wxT("Database error: %s"), 
				e.GetErrorMessage()));
		}
	}
};

IMPLEMENT_APP_CONSOLE(LogWatcher);
