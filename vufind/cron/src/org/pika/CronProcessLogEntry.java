/*
 * Copyright (C) 2023  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

package org.pika;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.Date;

import org.apache.logging.log4j.Logger;

public class CronProcessLogEntry {
	private Long              cronLogId;
	private Long              logProcessId;
	private String            processName = null;
	private Date              startTime;
	private Date              lastUpdate; //The last time the log entry was updated so we can tell if a process is stuck
	private Date              endTime;
	private int               numErrors;
	private int               numUpdates;
	private ArrayList<String> notes       = new ArrayList<String>();

	public CronProcessLogEntry(Long cronLogId, String processName) {
		this.cronLogId   = cronLogId;
		this.processName = processName;
		this.startTime   = new Date();
	}

	public Date getLastUpdate() {
		lastUpdate = new Date();
		return lastUpdate;
	}

	public String getProcessName() {
		return processName;
	}

	public int getNumErrors() {
		return numErrors;
	}

	public int getNumUpdates() {
		return numUpdates;
	}

	public void incErrors() {
		numErrors++;
	}

	public void incUpdated() {
		numUpdates++;
	}

	public void addUpdates(int updates) {
		numUpdates += updates;
	}

	public void setProcessName(String processName) {
		this.processName = processName;
	}

	public ArrayList<String> getNotes() {
		return notes;
	}

	public void addNote(String note) {
		if (this.notes.size() < 5000) {
			this.notes.add(note);
		}
	}

	public String getNotesHtml() {
		StringBuilder notesText = new StringBuilder("<ol class='cronProcessNotes'>");
		for (String curNote : notes) {
			String cleanedNote = curNote;
			cleanedNote = cleanedNote.replaceAll("<pre>", "<code>");
			cleanedNote = cleanedNote.replaceAll("</pre>", "</code>");
			//Replace multiple line breaks
			cleanedNote = cleanedNote.replaceAll("(?:<br?>\\s*)+", "<br>");
			cleanedNote = cleanedNote.replaceAll("<meta.*?>", "");
			cleanedNote = cleanedNote.replaceAll("<title>.*?</title>", "");
			notesText.append("<li>").append(cleanedNote).append("</li>");
		}
		notesText.append("</ol>");
		if (notesText.length() > 64000) {
			notesText.substring(0, 64000);
		}
		return notesText.toString();
	}

	private static boolean           statementsPrepared = false;
	private static PreparedStatement insertLogEntry;
	private static PreparedStatement updateLogEntry;

	public boolean saveToDatabase(Connection pikaConn, Logger logger) {
		try {
			if (!statementsPrepared) {
				insertLogEntry = pikaConn.prepareStatement("INSERT into cron_process_log (cronId, processName, startTime) VALUES (?, ?, ?)", PreparedStatement.RETURN_GENERATED_KEYS);
				updateLogEntry = pikaConn.prepareStatement("UPDATE cron_process_log SET lastUpdate = ?, endTime = ?, numErrors = ?, numUpdates = ?, notes = ? WHERE id = ?", PreparedStatement.RETURN_GENERATED_KEYS);
			}
		} catch (SQLException e) {
			logger.error("Error creating prepared statements to update log", e);
			return false;
		}
		try {
			if (logProcessId == null) {
				insertLogEntry.setLong(1, cronLogId);
				insertLogEntry.setString(2, processName);
				insertLogEntry.setLong(3, startTime.getTime() / 1000);
				insertLogEntry.executeUpdate();
				ResultSet generatedKeys = insertLogEntry.getGeneratedKeys();
				if (generatedKeys.next()) {
					logProcessId = generatedKeys.getLong(1);
				}
			} else {
				updateLogEntry.setLong(1, getLastUpdate().getTime() / 1000);
				if (endTime == null) {
					updateLogEntry.setNull(2, java.sql.Types.INTEGER);
				} else {
					updateLogEntry.setLong(2, endTime.getTime() / 1000);
				}
				updateLogEntry.setLong(3, numErrors);
				updateLogEntry.setLong(4, numUpdates);
				updateLogEntry.setString(5, getNotesHtml());
				updateLogEntry.setLong(6, logProcessId);
				updateLogEntry.executeUpdate();
			}
			return true;
		} catch (SQLException e) {
			logger.error("Error creating prepared statements to update log", e);
			return false;
		}
	}

	public void setFinished() {
		this.endTime = new Date();
	}
}
