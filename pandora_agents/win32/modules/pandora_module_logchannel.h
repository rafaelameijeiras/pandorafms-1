/* Pandora logchannel module. This module checks for log events that match a given
   pattern using XML functions provided by wevtapi.

   Copyright (C) 2017 Artica ST.
   Written by Fermin Hernandez.

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2, or (at your option)
   any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License along
   with this program; if not, write to the Free Software Foundation,
   Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

#ifndef	__PANDORA_MODULE_LOGCHANNEL_H__
#define	__PANDORA_MODULE_LOGCHANNEL_H__

#include "pandora_module.h"
#include "boost/regex.h"
#include "../windows/winevt.h"

// Log event read buffer size
#define	BUFFER_SIZE 1024

// Length of a timestamp string YYYY-MM-DD HH:MM:SS
#define	TIMESTAMP_LEN 19

// The EventID property equals the InstanceId with the top two bits masked off.
// See: http://msdn.microsoft.com/en-us/library/system.diagnostics.eventlogentry.eventid.aspx
//#define EVENT_ID_MASK 0x3FFFFFFF

// The Windows Event Log Viewer seems to ignore the most significant 16 bits.
#define EVENT_ID_MASK 0x0000FFFF

// Types for pointers to Wevtapi.dll functions
typedef EVT_HANDLE WINAPI (*EvtQueryT) (EVT_HANDLE Session, LPCWSTR Path, LPCWSTR Query, DWORD Flags);
typedef WINBOOL WINAPI (*EvtNextT) (EVT_HANDLE ResultSet, DWORD EventArraySize, EVT_HANDLE* EventArray, DWORD Timeout, DWORD Flags, PDWORD Returned);
typedef WINBOOL WINAPI (*EvtSeekT) (EVT_HANDLE ResultSet, LONGLONG Position, EVT_HANDLE Bookmark, DWORD Timeout, DWORD Flags);
typedef EVT_HANDLE WINAPI (*EvtCreateRenderContextT) (DWORD ValuePathsCount, LPCWSTR *ValuePaths, DWORD Flags);
typedef WINBOOL WINAPI (*EvtRenderT) (EVT_HANDLE Context, EVT_HANDLE Fragment, DWORD Flags, DWORD BufferSize, PVOID Buffer, PDWORD BufferUsed, PDWORD PropertyCount);
typedef WINBOOL WINAPI (*EvtCloseT) (EVT_HANDLE Object);
typedef WINBOOL WINAPI (*EvtFormatMessageT) (EVT_HANDLE PublisherMetadata, EVT_HANDLE Event, DWORD MessageId, DWORD ValueCount, PEVT_VARIANT Values, DWORD Flags, DWORD BufferSize, LPWSTR Buffer, PDWORD BufferUsed);
typedef EVT_HANDLE WINAPI (*EvtOpenPublisherMetadataT) (EVT_HANDLE Session, LPCWSTR PublisherIdentity, LPCWSTR LogFilePath, LCID Locale, DWORD Flags);
typedef EVT_HANDLE WINAPI (*EvtCreateBookmarkT) (LPCWSTR BookmarkXml);
typedef WINBOOL WINAPI (*EvtUpdateBookmarkT) (EVT_HANDLE Bookmark, EVT_HANDLE Event);

namespace Pandora_Modules {
    
	/**
	 * This module checks for log events that match a given
     * pattern. Events can be filtered by source and type.
	 */

	class Pandora_Module_Logchannel : public Pandora_Module {
	private:
		regex_t regexp;
		unsigned long id;
		int type;
		unsigned char first_run;
		string source;
		string application;
		string pattern;
        wstring bookmark_xml;
		HANDLE messages_dll;

        void initializeLogChannel ();
        bool updateBookmarkXML (EVT_HANDLE hBookmark);
        void getLogEvents (list<string> &event_list);
        void cleanBookmark ();
		LPWSTR GetMessageString(EVT_HANDLE hMetadata, EVT_HANDLE hEvent, EVT_FORMAT_MESSAGE_FLAGS FormatId);

	public:
		Pandora_Module_Logchannel (string name, string source, string type, string id, string pattern, string application);
		void run ();
	};
}

#endif
