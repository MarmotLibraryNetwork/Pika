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

import au.com.bytecode.opencsv.CSVWriter;
import org.apache.logging.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile;
import org.pika.CronLogEntry;
import org.pika.CronProcessLogEntry;
import org.pika.IProcessHandler;
import org.pika.PikaConfigIni;
import org.pika.PikaSystemVariables;

import java.io.File;
import java.io.FileWriter;
import java.io.IOException;
import java.sql.*;

/**
 * Pika
 * User: Mark Noble
 * Date: 5/18/14
 * Time: 8:24 PM
 */
public class SierraReports implements IProcessHandler {
	private CronProcessLogEntry processLog;
	private Logger logger;
	private String ils;
	@Override
	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		this.logger = logger;
		processLog = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Sierra Reports");
		processLog.saveToDatabase(pikaConn, logger);
		String reportsPath = PikaConfigIni.getIniValue("Site", "reportPath");

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
					if (sierraDBUser.startsWith("\"")){
						sierraDBUser = sierraDBUser.substring(1, sierraDBUser.length() - 1);
					}
					if (sierraDBPassword.startsWith("\"")){
						sierraDBPassword = sierraDBPassword.substring(1, sierraDBPassword.length() - 1);
					}
					conn = DriverManager.getConnection(url, sierraDBUser, sierraDBPassword);
				} else {
					conn = DriverManager.getConnection(url);
				}

				createStudentReportsByHomeroom(conn, processSettings, reportsPath);
				conn.close();
			}catch(Exception e){
				logger.error("Error: ", e);
				processLog.incErrors();
				processLog.addNote("Error ended Sierra Reports");
			}
		}

		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	private void createStudentReportsByHomeroom(Connection conn, Profile.Section processSettings, String reportsPath) throws SQLException, IOException {
		//Get a list of libraries that we should create reports for
		String   allLibrariesToCreateReportsFor = processSettings.get("librariesToCreateReportsFor");
		String[] librariesToCreateReportsFor    = allLibrariesToCreateReportsFor.split(",");
		PreparedStatement patronsToProcessStmt = conn.prepareStatement("SELECT DISTINCT py.field_content AS gradelvl, pr.field_content AS homeroom, patron_record_fullname.last_name, patron_record_fullname.first_name, patron_record_fullname.middle_name, pb.field_content as barcode, patron_record.id, patron_record.ptype_code, patron_record.home_library_code, patron_record.owed_amt, patron_record.pcode1, addr1, addr2, addr3, city, region, postal_code, patron_record_address_type_id, patron_record_address.display_order from sierra_view.patron_record \n" +
				"LEFT OUTER JOIN sierra_view.varfield pr\n" +
				"ON patron_record.id = pr.record_id AND pr.varfield_type_code = 'r'\n" +
				"LEFT OUTER JOIN sierra_view.varfield pb\n" +
				"ON patron_record.id = pb.record_id AND pb.varfield_type_code = 'b'\n" +
				"LEFT OUTER JOIN sierra_view.varfield py\n" +
				"ON patron_record.id = py.record_id AND py.varfield_type_code = 'y'\n" +
				"INNER JOIN sierra_view.patron_record_fullname\n" +
				"ON patron_record_id = patron_record.id \n" +
				"LEFT OUTER JOIN sierra_view.patron_record_address\n" +
				"ON patron_record.id = patron_record_address.patron_record_id AND patron_record_address_type_id = 1\n" +
				"where home_library_code like ? AND (checkout_count > 0 OR owed_amt > 0)\n" +
				"ORDER BY gradelvl, homeroom, last_name", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		PreparedStatement itemsOutStmt = conn.prepareStatement("SELECT due_gmt, checkout_gmt, location_code, item_view.record_num AS itemNumber, item_status_code, barcode, bib_view.record_num, bib_view.record_type_code, title, ia.field_content AS callNumber \n" +
				"FROM sierra_view.checkout \n" +
				"INNER JOIN sierra_view.item_view ON checkout.item_record_id = item_view.id \n" +
				"INNER JOIN sierra_view.bib_record_item_record_link ON item_view.id = bib_record_item_record_link.item_record_id \n" +
				"INNER JOIN sierra_view.bib_view ON bib_record_item_record_link.bib_record_id = bib_view.id \n" +
				"LEFT OUTER JOIN sierra_view.varfield ia\n" +
				"ON item_view.id = ia.record_id AND ia.varfield_type_code = 'c'\n" +
				"where patron_record_id = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		for (String curLibraryPrefix : librariesToCreateReportsFor){
			logger.info("Writing report for " + curLibraryPrefix);
			File patronReportFile = new File (reportsPath + "/" + curLibraryPrefix + "_school_report.csv");
			try (
							FileWriter patronReportWriter = new FileWriter(patronReportFile, false);
							CSVWriter patronReportCsvWriter = new CSVWriter(patronReportWriter)
			) {
				//Write headers
				patronReportWriter.write("P Type,P Code 1, Patron Name, Home Lib,P Barcode,Grd Lvl,Home Room,$ Owed,Call #,Title,Item Barcode,Item Loc,Due Date,Stat,Address");
				patronReportWriter.write("\r\n");
				//Get a list of users that belong to that branch who have titles checked out or fines
				patronsToProcessStmt.setString(1, curLibraryPrefix);
				ResultSet patronsForSchoolRS = patronsToProcessStmt.executeQuery();
				while (patronsForSchoolRS.next()) {
					//Gather information about the patron
					long     patronId    = patronsForSchoolRS.getLong("id");
					String[] patronInfo  = new String[16];
					String   lastName    = patronsForSchoolRS.getString("last_name");
					String   firstName   = patronsForSchoolRS.getString("first_name");
					String   middleName  = patronsForSchoolRS.getString("middle_name");
					String   fullName    = lastName + ", " + firstName + " " + middleName;
					String   fullAddress = patronsForSchoolRS.getString("addr1") + " " + patronsForSchoolRS.getString("city") + ", " + patronsForSchoolRS.getString("region") + " " + patronsForSchoolRS.getString("postal_code");
					patronInfo[0]  = patronsForSchoolRS.getString("ptype_code");
					patronInfo[1]  = patronsForSchoolRS.getString("pcode1");
					patronInfo[2]  = fullName;
					patronInfo[3]  = patronsForSchoolRS.getString("home_library_code");
					patronInfo[4]  = patronsForSchoolRS.getString("barcode");
					patronInfo[5]  = patronsForSchoolRS.getString("gradelvl");
					patronInfo[6]  = patronsForSchoolRS.getString("homeroom");
					patronInfo[7]  = patronsForSchoolRS.getString("owed_amt");
					patronInfo[14] = fullAddress;

					//Get a list of items that are checked out to each user
					itemsOutStmt.setLong(1, patronId);
					ResultSet itemsOutRS      = itemsOutStmt.executeQuery();
					int       numItemsWritten = 0;
					while (itemsOutRS.next()) {
						String callNumber = itemsOutRS.getString("callnumber");
						if (callNumber == null) {
							callNumber = "";
						} else {
							callNumber = callNumber.replaceAll("\\|\\w", "");
						}
						patronInfo[8]  = callNumber;
						patronInfo[9]  = itemsOutRS.getString("title");
						patronInfo[10] = itemsOutRS.getString("barcode");
						patronInfo[11] = itemsOutRS.getString("location_code");
						patronInfo[12] = itemsOutRS.getString("due_gmt");
						patronInfo[13] = itemsOutRS.getString("item_status_code");
						patronReportCsvWriter.writeNext(patronInfo);
						patronInfo[7] = ""; //Only display amount owed on the first row
						numItemsWritten++;
					}
					if (numItemsWritten == 0) {
						//No items are checked out
						patronReportCsvWriter.writeNext(patronInfo);
					}
				}
			}
			processLog.incUpdated();
			processLog.addNote("Updated report for " + curLibraryPrefix);
			processLog.saveToDatabase(conn, logger);
		}
	}

}
