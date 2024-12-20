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

import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileReader;
import java.io.IOException;
import java.sql.*;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Date;

import org.ini4j.Ini;
import org.ini4j.InvalidFileFormatException;
import org.ini4j.Profile.Section;

// Import log4j classes.
import org.apache.logging.log4j.Logger;
import org.apache.logging.log4j.LogManager;

public class Cron {

	private static Logger logger;
	private static String serverName;

	private static Connection          pikaConn;
	private static Connection          econtentConn;
	private static PikaSystemVariables systemVariables;

	/**
	 * @param args
	 */
	public static void main(String[] args) {
		if (args.length == 0) {
			System.out.println("The name of the server to run cron for must be provided as the first parameter.");
			System.exit(1);
		}
		serverName = args[0];
		args       = Arrays.copyOfRange(args, 1, args.length);


		// Initialize the logger
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j2.cron.xml");
		if (log4jFile.exists()) {
			System.setProperty("log4j.pikaSiteName", serverName);
			System.setProperty("log4j.configurationFile", log4jFile.getAbsolutePath());
			logger = LogManager.getLogger();
		} else {
			System.out.println("Could not find log4j configuration " + log4jFile);
			System.exit(1);
		}

		Date currentTime = new Date();
		logger.info(currentTime + ": Starting Cron");

		// Setup the MySQL driver
		try {
			// The newInstance() call is a work around for some
			// broken Java implementations
			Class.forName("com.mysql.jdbc.Driver").newInstance();

			logger.info("Loaded driver for MySQL");
		} catch (Exception ex) {
			logger.info("Could not load driver for MySQL, exiting.");
			return;
		}

		// Read the base INI file to get information about the server (current directory/conf/config.ini)
		PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

		//Connect to the database
		String databaseConnectionInfo = PikaConfigIni.getIniValue("Database", "database_vufind_jdbc");
		if (databaseConnectionInfo == null || databaseConnectionInfo.isEmpty()) {
			logger.error("Pika Database connection information not found in General Settings.  Please specify connection information in a database key.");
			return;
		}
		String econtentConnectionInfo = PikaConfigIni.getIniValue("Database", "database_econtent_jdbc");
		if (econtentConnectionInfo == null || econtentConnectionInfo.isEmpty()) {
			logger.error("eContent Database connection information not found in General Settings.  Please specify connection information in a database key.");
			return;
		}

		try {
			pikaConn = DriverManager.getConnection(databaseConnectionInfo);
		} catch (SQLException ex) {
			// handle any errors
			logger.error("Error establishing connection to database " + databaseConnectionInfo, ex);
			return;
		}
		try {
			econtentConn = DriverManager.getConnection(econtentConnectionInfo);
		} catch (SQLException ex) {
			// handle any errors
			logger.error("Error establishing connection to database " + econtentConnectionInfo, ex);
			return;
		}

		systemVariables = new PikaSystemVariables(logger, pikaConn);

		//Create a log entry for the cron process
		CronLogEntry cronEntry = new CronLogEntry();
		if (!cronEntry.saveToDatabase(pikaConn, logger)) {
			logger.error("Could not save log entry to database, quitting");
			return;
		}

		// Read the cron INI file to get information about the processes to run
		Ini cronIni = loadConfigFile("config.cron.ini");

		//Check to see if a specific task has been specified to be run
		ArrayList<ProcessToRun> processesToRun = new ArrayList<ProcessToRun>();
		// The Cron INI file has a main section for processes to be run
		// The processes are in the format:
		// name = handler class
		Section processes = cronIni.get("Processes");
		if (args.length >= 1) {
			logger.info("Found " + args.length + " arguments ");
			String processName         = args[0];
			String processHandlerClass = cronIni.get("Processes", processName);
			if (processHandlerClass == null) {
				processHandlerClass = processName;
			}
			ProcessToRun process = new ProcessToRun(processName, processHandlerClass);
			args = Arrays.copyOfRange(args, 1, args.length);
			if (args.length > 0) {
				process.setArguments(args);
			}
			loadLastRunTimeForProcess(process);
			processesToRun.add(process);
		} else {
			// Load processes to run
			processesToRun = loadProcessesToRun(cronIni, processes);
		}

		for (ProcessToRun processToRun : processesToRun) {
			Section processSettings;
			if (processToRun.getArguments() != null) {
				//Add arguments into the section
				for (String argument : processToRun.getArguments()) {
					String[] argumentOptions;
					try {
						argumentOptions = argument.split("=");
						logger.info("Adding section setting " + argumentOptions[0] + " = " + argumentOptions[1]);
						cronIni.put("runtimeArguments", argumentOptions[0], argumentOptions[1]);
					} catch (IndexOutOfBoundsException e) {
						cronIni.put("runtimeArguments", argument, true);
					}
				}
				processSettings = cronIni.get("runtimeArguments");
			} else {
				processSettings = cronIni.get(processToRun.getProcessName());
			}

			currentTime = new Date();
			logger.info(currentTime + ": Running Process " + processToRun.getProcessName());
			if (processToRun.getProcessClass() == null) {
				logger.error("Could not run process " + processToRun.getProcessName() + " because there is not a class for the process.");
				cronEntry.addNote("Could not run process " + processToRun.getProcessName() + " because there is not a class for the process.");
				continue;
			}
			// Load the class for the process using reflection
			try {
				@SuppressWarnings("rawtypes")
				Class processHandlerClass = Class.forName(processToRun.getProcessClass());
				Object processHandlerClassObject;
				try {
					processHandlerClassObject = processHandlerClass.newInstance();
					IProcessHandler processHandlerInstance = (IProcessHandler) processHandlerClassObject;
					cronEntry.addNote("Starting cron process " + processToRun.getProcessName());
					cronEntry.saveToDatabase(pikaConn, logger);

					//Mark the time the run was started rather than finished so really long running processes
					//can go on while faster processes execute multiple times in other threads.
					markProcessStarted(processToRun);
					processHandlerInstance.doCronProcess(serverName, processSettings, pikaConn, econtentConn, cronEntry, logger, systemVariables);
					//Log how long the process took
					Date  endTime        = new Date();
					long  elapsedMillis  = endTime.getTime() - currentTime.getTime();
					float elapsedMinutes = (float) (elapsedMillis) / 60000;
					logger.info("Finished process " + processToRun.getProcessName() + " in " + elapsedMinutes + " minutes (" + elapsedMillis + " milliseconds)");
					cronEntry.addNote("Finished process " + processToRun.getProcessName() + " in " + elapsedMinutes + " minutes (" + elapsedMillis + " milliseconds)");

				} catch (InstantiationException e) {
					logger.error("Could not run process " + processToRun.getProcessName() + " because the handler class " + processToRun.getProcessClass() + " could not be be instantiated.");
					cronEntry.addNote("Could not run process " + processToRun.getProcessName() + " because the handler class " + processToRun.getProcessClass() + " could not be be instantiated.");
				} catch (IllegalAccessException e) {
					logger.error("Could not run process " + processToRun.getProcessName() + " because the handler class " + processToRun.getProcessClass() + " generated an Illegal Access Exception.");
					cronEntry.addNote("Could not run process " + processToRun.getProcessName() + " because the handler class " + processToRun.getProcessClass() + " generated an Illegal Access Exception.");
				}

			} catch (ClassNotFoundException e) {
				logger.error("Could not run process " + processToRun.getProcessName() + " because the handler class " + processToRun.getProcessClass() + " could not be be found.");
				cronEntry.addNote("Could not run process " + processToRun.getProcessName() + " because the handler class " + processToRun.getProcessClass() + " could not be be found.");
			}
		}

		cronEntry.setFinished();
		cronEntry.addNote("Cron run finished");
		cronEntry.saveToDatabase(pikaConn, logger);
	}

	private static void loadLastRunTimeForProcess(ProcessToRun newProcess) {
		String processVariableName = "last_" + newProcess.getProcessName().toLowerCase().replace(' ', '_') + "_time";
		newProcess.setLastRunTime(systemVariables.getLongValuedVariable(processVariableName));
	}

	private static void markProcessStarted(ProcessToRun processToRun) {
		String processVariableName = "last_" + processToRun.getProcessName().toLowerCase().replace(' ', '_') + "_time";
		Long   finishTime          = new Date().getTime() / 1000;
		systemVariables.setVariable(processVariableName, finishTime);
	}

	private static ArrayList<ProcessToRun> loadProcessesToRun(Ini cronIni, Section processes) {
		ArrayList<ProcessToRun> processesToRun = new ArrayList<ProcessToRun>();
		Date                    currentTime    = new Date();
		for (String processName : processes.keySet()) {
			String processHandlerClass = cronIni.get("Processes", processName);
			// Each process has its own configuration section which can include:
			// - time last run
			// - interval to run the process
			// - additional configuration information for the process
			// Check to see when the process was last run
			boolean      runProcess     = false;
			String       frequencyHours = cronIni.get(processName, "frequencyHours");
			ProcessToRun newProcess     = new ProcessToRun(processName, processHandlerClass);
			if (frequencyHours == null || frequencyHours.length() == 0) {
				//If the frequency isn't set, automatically run the process 
				runProcess = true;
			} else if (frequencyHours.trim().compareTo("-1") == 0) {
				// Process has to be run manually
				runProcess = false;
				logger.info("Skipping Process " + processName + " because it must be run manually.");
			} else {
				loadLastRunTimeForProcess(newProcess);

				//Frequency is a number of hours.  See if we should run based on the last run.
				if (newProcess.getLastRunTime() == null) {
					runProcess = true;
				} else {
					// Check the interval to see if the process should be run
					try {
						if (frequencyHours.trim().compareTo("0") == 0) {
							// There should not be a delay between cron runs
							runProcess = true;
						} else {
							int frequencyHoursInt = Integer.parseInt(frequencyHours);
							if ((double) (currentTime.getTime() / 1000 - newProcess.getLastRunTime()) / (double) (60 * 60) >= frequencyHoursInt) {
								// The elapsed time is greater than the frequency to run
								runProcess = true;
							} else {
								logger.info("Skipping Process " + processName + " because it has already run in the specified interval.");
							}

						}
					} catch (NumberFormatException e) {
						logger.warn("Warning: the lastRun setting for " + processName + " was invalid. " + e.toString());
					}
				}
			}
			if (runProcess) {
				logger.info("Running process " + processName);
				processesToRun.add(newProcess);
			}
		}
		return processesToRun;
	}

	private static Ini loadConfigFile(String filename) {
		//First load the default config file 
		String configName = "../../sites/default/conf/" + filename;
		logger.info("Loading configuration from " + configName);
		File configFile = new File(configName);
		if (!configFile.exists()) {
			logger.error("Could not find configuration file " + configName);
			System.exit(1);
		}

		// Parse the configuration file
		Ini ini = new Ini();
		try {
			ini.load(new FileReader(configFile));
		} catch (InvalidFileFormatException e) {
			logger.error("Configuration file is not valid.  Please check the syntax of the file.", e);
		} catch (FileNotFoundException e) {
			logger.error("Configuration file could not be found.  You must supply a configuration file in conf called config.ini.", e);
		} catch (IOException e) {
			logger.error("Configuration file could not be read.", e);
		}

		//Now override with the site specific configuration
		String siteSpecificFilename = "../../sites/" + serverName + "/conf/" + filename;
		logger.info("Loading site specific config from " + siteSpecificFilename);
		File siteSpecificFile = new File(siteSpecificFilename);
		if (!siteSpecificFile.exists()) {
			logger.error("Could not find server specific config file");
			System.exit(1);
		}
		try {
			Ini siteSpecificIni = new Ini();
			siteSpecificIni.load(new FileReader(siteSpecificFile));
			for (Section curSection : siteSpecificIni.values()) {
				for (String curKey : curSection.keySet()) {
					//logger.debug("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
					//System.out.println("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
					ini.put(curSection.getName(), curKey, curSection.get(curKey));
				}
			}
		} catch (InvalidFileFormatException e) {
			logger.error("Site Specific config file is not valid.  Please check the syntax of the file.", e);
		} catch (IOException e) {
			logger.error("Site Specific config file could not be read.", e);
		}

		//Now override with the site specific configuration
//		String passwordFilename = "../../sites/" + serverName + "/conf/config.pwd.ini";
		String passwordFilename = siteSpecificFilename.replaceFirst(".ini", ".pwd.ini");
		logger.info("Loading site specific config from " + passwordFilename);
		File siteSpecificPasswordFile = new File(passwordFilename);
		if (!siteSpecificPasswordFile.exists()) {
			logger.info("Could not find server specific config password file: " + passwordFilename);
		} else {
			try {
				Ini siteSpecificIni = new Ini();
				siteSpecificIni.load(new FileReader(siteSpecificPasswordFile));
				for (Section curSection : siteSpecificIni.values()) {
					for (String curKey : curSection.keySet()) {
						//logger.debug("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
						//System.out.println("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
						ini.put(curSection.getName(), curKey, curSection.get(curKey));
					}
				}
			} catch (InvalidFileFormatException e) {
				logger.error("Site Specific config file is not valid.  Please check the syntax of the file.", e);
			} catch (IOException e) {
				logger.error("Site Specific config file could not be read.", e);
			}
		}
		return ini;
	}

}
