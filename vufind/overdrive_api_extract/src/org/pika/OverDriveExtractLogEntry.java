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

public class OverDriveExtractLogEntry {
	private Long              logEntryId             = null;
	private Date              startTime;
	private Date              endTime;
	private ArrayList<String> notes                  = new ArrayList<>();
	private int               numProducts            = 0;
	private int               numErrors              = 0;
	private int               numAdded               = 0;
	private int               numDeleted             = 0;
	private int               numUpdated             = 0;
	private int               numSkipped             = 0;
	private int               numAvailabilityChanges = 0;
	private int               numMetadataChanges     = 0;
	private int               numTitlesProcessed     = 0;
	private Logger            logger;

	public OverDriveExtractLogEntry(Connection econtentConn, Logger logger) {
		this.logger    = logger;
		this.startTime = new Date();
		try {
			insertLogEntry = econtentConn.prepareStatement("INSERT INTO overdrive_extract_log (startTime) VALUES (?)", PreparedStatement.RETURN_GENERATED_KEYS);
			updateLogEntry = econtentConn.prepareStatement("UPDATE overdrive_extract_log SET lastUpdate = ?, endTime = ?, notes = ?, numProducts = ?, numErrors = ?, numAdded = ?, numUpdated = ?, numSkipped = ?, numDeleted = ?, numAvailabilityChanges = ?, numMetadataChanges = ? , numTitlesProcessed = ? WHERE id = ?", PreparedStatement.RETURN_GENERATED_KEYS);
		} catch (SQLException e) {
			logger.error("Error creating prepared statements to update log", e);
		}
	}

	public void addNote(String note) {
		this.notes.add(note);
	}

	public String getNotesHtml() {
		StringBuilder notesText = new StringBuilder("<ol class='cronNotes'>");
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
		String returnText = notesText.toString();
		if (returnText.length() > 25000) {
			returnText = returnText.substring(0, 25000) + "<li>Additional notes were truncated</li>";
		}
		returnText += "</ol>";
		return returnText;
	}

	private static PreparedStatement insertLogEntry;
	private static PreparedStatement updateLogEntry;

	public boolean saveResults() {
		try {
			if (logEntryId == null) {
				insertLogEntry.setLong(1, startTime.getTime() / 1000);
				insertLogEntry.executeUpdate();
				ResultSet generatedKeys = insertLogEntry.getGeneratedKeys();
				if (generatedKeys.next()) {
					logEntryId = generatedKeys.getLong(1);
				}
			} else {
				int curCol = 0;
				updateLogEntry.setLong(++curCol, new Date().getTime() / 1000);
				if (endTime == null) {
					updateLogEntry.setNull(++curCol, java.sql.Types.INTEGER);
				} else {
					updateLogEntry.setLong(++curCol, endTime.getTime() / 1000);
				}
				updateLogEntry.setString(++curCol, getNotesHtml());
				updateLogEntry.setInt(++curCol, numProducts);
				updateLogEntry.setInt(++curCol, numErrors);
				updateLogEntry.setInt(++curCol, numAdded);
				updateLogEntry.setInt(++curCol, numUpdated);
				updateLogEntry.setInt(++curCol, numSkipped);
				updateLogEntry.setInt(++curCol, numDeleted);
				updateLogEntry.setInt(++curCol, numAvailabilityChanges);
				updateLogEntry.setInt(++curCol, numMetadataChanges);
				updateLogEntry.setInt(++curCol, numTitlesProcessed);
				updateLogEntry.setLong(++curCol, logEntryId);
				updateLogEntry.executeUpdate();
			}
			return true;
		} catch (SQLException e) {
			logger.error("Error creating updating log", e);
			return false;
		}
	}

	public void setFinished() {
		this.endTime = new Date();
	}

	public void incrementErrors() {
		numErrors++;
	}

	public void incrementAdded() {
		numAdded++;
	}

	public void incrementDeleted() {
		numDeleted++;
	}

	public void incrementUpdated() {
		numUpdated++;
	}

	public void incrementSkipped() {
		numSkipped++;
	}

	public void incrementAvailabilityChanges() {
		numAvailabilityChanges++;
	}

	public void incrementMetadataChanges() {
		numMetadataChanges++;
	}

	public void incrementTitlesProcessed() {
		numTitlesProcessed++;
	}

	public void setNumProducts(int size) {
		numProducts = size;
	}

	public boolean hasErrors() {
		return numErrors > 0;
	}

}
