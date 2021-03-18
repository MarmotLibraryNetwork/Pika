/*
 * Copyright (C) 2021  Marmot Library Network
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

package org.innovative;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile;
import org.pika.*;

import java.sql.*;

/**
 * Export Sierra Volume Records that needs to happen infrequently.
 *
 * Pika
 * User: Mark Noble
 * Date: 11/24/2015
 * Time: 10:48 PM
 */
public class ExportSierraData implements IProcessHandler {
	private CronProcessLogEntry processLog;
	private Logger logger;
	private String ils;
	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		this.logger = logger;
		processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Export Sierra Data");
		processLog.saveToDatabase(pikaConn, logger);

		ils = PikaConfigIni.getIniValue("Catalog", "ils");
		if (!ils.equalsIgnoreCase("Sierra")){
			processLog.addNote("ILS is not Sierra, quiting");
		}else{
			//Connect to the sierra database
			String url              = PikaConfigIni.getIniValue("Catalog", "sierra_db");
			String sierraDBUser     = PikaConfigIni.getIniValue("Catalog", "sierra_db_user");
			String sierraDBPassword = PikaConfigIni.getIniValue("Catalog", "sierra_db_password");
			if (url.startsWith("\"")){
				url = url.substring(1, url.length() - 1);
			}
			Connection conn = null;
			try{
				//Open the connection to the database
				if (sierraDBUser != null && sierraDBPassword != null && !sierraDBPassword.isEmpty() && !sierraDBUser.isEmpty()) {
					// Use specific user name and password when the are issues with special characters
					if (sierraDBPassword.startsWith("\"")){
						sierraDBPassword = sierraDBPassword.substring(1, sierraDBPassword.length() - 1);
					}
					conn = DriverManager.getConnection(url, sierraDBUser, sierraDBPassword);
				} else {
					conn = DriverManager.getConnection(url);
				}

				exportVolumes(conn, pikaConn);

				conn.close();
			}catch(Exception e){
				System.out.println("Error: " + e.toString());
				e.printStackTrace();
			}
		}

		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	private void exportVolumes(Connection conn, Connection pikaConn) {
		try {
			logger.info("Starting export of volume information");
			PreparedStatement getVolumeInfoStmt = conn.prepareStatement("SELECT volume_view.id, volume_view.record_num AS volume_num, sort_order FROM sierra_view.volume_view " +
					"INNER JOIN sierra_view.bib_record_volume_record_link on bib_record_volume_record_link.volume_record_id = volume_view.id " +
					"WHERE volume_view.is_suppressed = 'f'", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			PreparedStatement getBibForVolumeStmt = conn.prepareStatement("SELECT record_num FROM sierra_view.bib_record_volume_record_link " +
					"INNER JOIN sierra_view.bib_view on bib_record_volume_record_link.bib_record_id = bib_view.id " +
					"WHERE volume_record_id = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			PreparedStatement getItemsForVolumeStmt = conn.prepareStatement("SELECT record_num from sierra_view.item_view " +
					"INNER JOIN sierra_view.volume_record_item_record_link ON volume_record_item_record_link.item_record_id = item_view.id " +
					"WHERE volume_record_id = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			PreparedStatement getVolumeNameStmt = conn.prepareStatement("SELECT * FROM sierra_view.subfield WHERE record_id = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);

			PreparedStatement removeOldVolumes = pikaConn.prepareStatement("DELETE FROM ils_volume_info WHERE recordId LIKE 'ils%'");
			PreparedStatement addVolumeStmt    = pikaConn.prepareStatement("INSERT INTO ils_volume_info (recordId, volumeId, displayLabel, relatedItems) VALUES (?,?,?,?)");

			ResultSet volumeInfoRS     = null;
			boolean   loadError        = false;
			boolean   updateError      = false;
			Savepoint transactionStart = pikaConn.setSavepoint("load_volumes");
			try {
				volumeInfoRS = getVolumeInfoStmt.executeQuery();
			} catch (SQLException e1) {
				logger.error("Error loading volume information", e1);
				loadError = true;
			}
			if (!loadError) {
				try {
					removeOldVolumes.executeUpdate();
				} catch (SQLException sqle) {
					logger.error("Error removing old volume information", sqle);
					updateError = true;
				}

				while (volumeInfoRS.next()) {
					Long recordId = volumeInfoRS.getLong("id");

					String volumeId = volumeInfoRS.getString("volume_num");
					volumeId = ".j" + volumeId + getCheckDigit(volumeId);

					getBibForVolumeStmt.setLong(1, recordId);
					ResultSet bibForVolumeRS = getBibForVolumeStmt.executeQuery();
					String    bibId          = "";
					if (bibForVolumeRS.next()) {
						bibId = bibForVolumeRS.getString("record_num");
						bibId = ".b" + bibId + getCheckDigit(bibId);
					}

					getItemsForVolumeStmt.setLong(1, recordId);
					ResultSet     itemsForVolumeRS = getItemsForVolumeStmt.executeQuery();
					StringBuilder itemsForVolume   = new StringBuilder();
					while (itemsForVolumeRS.next()) {
						String itemId = itemsForVolumeRS.getString("record_num");
						if (itemId != null) {
							itemId = ".i" + itemId + getCheckDigit(itemId);
							if (itemsForVolume.length() > 0) itemsForVolume.append("|");
							itemsForVolume.append(itemId);
						}
					}

					getVolumeNameStmt.setLong(1, recordId);
					ResultSet getVolumeNameRS = getVolumeNameStmt.executeQuery();
					String    volumeName      = "Unknown";
					if (getVolumeNameRS.next()) {
						volumeName = getVolumeNameRS.getString("content");
					}

					try {
						addVolumeStmt.setString(1, "ils:" + bibId);
						addVolumeStmt.setString(2, volumeId);
						addVolumeStmt.setString(3, volumeName);
						addVolumeStmt.setString(4, itemsForVolume.toString());
						addVolumeStmt.executeUpdate();
						processLog.incUpdated();
					} catch (SQLException sqle) {
						logger.error("Error adding volume", sqle);
						processLog.incErrors();
						updateError = true;
					}
				}
				volumeInfoRS.close();
			}
			if (updateError) {
				pikaConn.rollback(transactionStart);
			}
			pikaConn.setAutoCommit(true);
			logger.info("Finished export of volume information");
		} catch (Exception e) {
			logger.error("Error exporting volume information", e);
			processLog.incErrors();
			processLog.addNote("Error exporting volume information " + e.toString());

		}
		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	/**
	 * Calculates a check digit for a III identifier
	 * @param basedId String the base id without checksum
	 * @return String the check digit
	 */
	private static String getCheckDigit(String basedId) {
		int sumOfDigits = 0;
		for (int i = 0; i < basedId.length(); i++){
			int multiplier = ((basedId.length() +1 ) - i);
			sumOfDigits += multiplier * Integer.parseInt(basedId.substring(i, i+1));
		}
		int modValue = sumOfDigits % 11;
		if (modValue == 10){
			return "x";
		}else{
			return Integer.toString(modValue);
		}
	}

}
