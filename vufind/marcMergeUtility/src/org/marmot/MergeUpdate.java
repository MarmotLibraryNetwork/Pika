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

// Import log4j classes.
import org.apache.logging.log4j.Logger;
import org.apache.logging.log4j.LogManager;

import org.ini4j.Ini;
import org.ini4j.InvalidFileFormatException;

import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileReader;
import java.io.IOException;
import java.util.Date;


public class MergeUpdate {
	private static Logger logger = LogManager.getLogger();

	public static void main(String[] args) {

		if (args.length == 0) {
			System.out.println("The .ini configuration file must be provided as first parameter.");
			System.exit(1);
		}

		String configFileName = args[0];
		if (!configFileName.endsWith("ini")) {
			System.out.println("invalid .ini configuration");
			System.exit(1);
		}


		Ini configIni = loadConfigFile(args[0]);
		Date currentTime = new Date();

		logger.info(currentTime + ": Starting Merge");
		MergeMarcUpdatesAndDeletes merge = new MergeMarcUpdatesAndDeletes();

		try {
			if (merge.startProcess(configIni, logger)) {
				currentTime = new Date();
				logger.info(currentTime + ": Successful Merge");
			} else {
				currentTime = new Date();
				logger.info(currentTime + ": Merge Failed");
			}
		} catch (Exception ex) {
			currentTime = new Date();
			logger.error(ex);
			logger.info(currentTime + ": Merge Failed");
		}
	}

	private static Ini loadConfigFile(String filename) {

		File configFile = new File(filename);
		if (!configFile.exists()) {
			logger.error("Could not find configuration file " + filename);
			System.exit(1);
		}

		// Parse the configuration file
		Ini ini = new Ini();
		try {
			ini.load(new FileReader(configFile));
		} catch (InvalidFileFormatException e) {
			logger.error("Configuration file is not valid.  Please check the syntax of the file.", e);
			System.exit(1);
		} catch (FileNotFoundException e) {
			logger.error("Configuration file could not be found.  You must supply a configuration file in conf called config.ini.", e);
			System.exit(1);
		} catch (IOException e) {
			logger.error("Configuration file could not be read.", e);
			System.exit(1);
		}

		return ini;
	}

}
