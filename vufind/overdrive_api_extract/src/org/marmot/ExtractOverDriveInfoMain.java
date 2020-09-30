/*
 * Copyright (C) 2020  Marmot Library Network
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

package org.marmot;

import java.io.*;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.util.Arrays;
import java.util.Date;

import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;

import org.pika.*;

public class ExtractOverDriveInfoMain {
	private static Logger              logger;
	private static String              serverName;
	private static Connection          pikaConn;
	private static PikaSystemVariables systemVariables;

	public static void main(String[] args) {
		if (args.length == 0) {
			System.out.println("The name of the server to extract OverDrive data for must be provided as the first parameter.");
			System.exit(1);
		}
		//System.out.println("Starting overdrive extract");

		serverName = args[0];
		args       = Arrays.copyOfRange(args, 1, args.length);
		boolean doFullReload          = false;
		String  individualIdToProcess = null;
		if (args.length == 1) {
			//Check to see if we got a full reload parameter
			String firstArg = args[0].replaceAll("\\s", "");
			if (firstArg.matches("^fullReload(=true|1)?$")) {
				doFullReload = true;

			}else if (firstArg.equals("singleWork")){
				//Process a specific overdrive title

				//Prompt for the overdrive Id to process
				System.out.print("Enter the Overdrive ID of the record to update from OverDrive: ");

				//  open up standard input
				BufferedReader br = new BufferedReader(new InputStreamReader(System.in));

				//  read the work from the command-line; need to use try/catch with the
				//  readLine() method
				try {
					individualIdToProcess = br.readLine().trim();
				} catch (IOException ioe) {
					System.out.println("IO error trying to read the work to process!");
					System.exit(1);
				}
			}
		}


		Date currentTime = new Date();
		File log4jFile   = new File("../../sites/" + serverName + "/conf/log4j.overdrive_extract.properties");
		if (log4jFile.exists()) {
			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile.toString());
		}
		logger = Logger.getLogger(ExtractOverDriveInfoMain.class);
		logger.info(currentTime.toString() + ": Starting OverDrive Extract");

//		// Setup the MySQL driver
//		try {
//			// The newInstance() call is a work around for some
//			// broken Java implementations
//			Class.forName("com.mysql.jdbc.Driver").newInstance();
//
//			if (logger.isDebugEnabled()) {
//				logger.debug("Loaded driver for MySQL");
//			}
//		} catch (Exception e) {
//			logger.error("Could not load driver for MySQL, exiting.", e);
//			return;
//		}
		// Read the base INI file to get information about the server (current directory/cron/config.ini)
		PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

		String databaseConnectionInfo = PikaConfigIni.getIniValue("Database", "database_vufind_jdbc");
		if (databaseConnectionInfo == null || databaseConnectionInfo.length() == 0) {
			logger.error("Pika Database connection information not found in Database Section.  Please specify connection information in database_vufind_jdbc.");
			System.exit(1);
		}
		try {
			pikaConn = DriverManager.getConnection(databaseConnectionInfo);
		} catch (SQLException e) {
			logger.error("Could not connect to pika database : " + e.getMessage());
			System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
		}


		//Connect to the database
		String econtentConnectionInfo = PikaConfigIni.getIniValue("Database", "database_econtent_jdbc");
		if (econtentConnectionInfo == null || econtentConnectionInfo.length() == 0) {
			logger.error("eContent Database connection information not found in General Settings.  Please specify connection information in a database key.");
			return;
		}

		Connection econtentConn;
		try {
			econtentConn = DriverManager.getConnection(econtentConnectionInfo);
		} catch (SQLException e) {
			logger.error("Could not connect to econtent database : " + e.getMessage());
			System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
			return;
		}

		OverDriveExtractLogEntry logEntry = new OverDriveExtractLogEntry(econtentConn, logger);
		if (!logEntry.saveResults()) {
			logger.error("Could not save log entry to database, quitting");
			return;
		}

		systemVariables = new PikaSystemVariables(logger, pikaConn);

		ExtractOverDriveInfo extractor = new ExtractOverDriveInfo();
		extractor.extractOverDriveInfo(systemVariables, pikaConn, econtentConn, logEntry, doFullReload, individualIdToProcess);

		logEntry.setFinished();
		logEntry.addNote("Finished OverDrive extraction");
		logEntry.saveResults();
		if (logger.isInfoEnabled()) {
			logger.info("Finished OverDrive extraction");
			Date endTime     = new Date();
			long elapsedTime = (endTime.getTime() - currentTime.getTime()) / 1000;
			logger.info("Elapsed time " + String.format("%f2", ((float) elapsedTime / 60f)) + " minutes");
		}
	}

}
