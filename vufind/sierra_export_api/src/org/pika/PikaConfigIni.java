package org.pika;

import org.ini4j.Ini;
import org.ini4j.InvalidFileFormatException;
import org.ini4j.Profile;

import java.io.File;
import java.io.FileNotFoundException;
import java.io.FileReader;
import java.io.IOException;

import org.apache.log4j.Logger;

/**
 * Singleton Instance of Pika Config Ini settings
 *
 * @author pbrammeier
 * 		Date:   6/16/2019
 */
public class PikaConfigIni {
	private static Ini ourInstance = new Ini();

	public static Ini getInstance() {
		return ourInstance;
	}

	private PikaConfigIni() {
	}

	public static Ini loadConfigFile(String filename, String serverName, Logger logger) {
		//First load the default config file
		String configName = "../../sites/default/conf/" + filename;
		logger.info("Loading configuration from " + configName);
		File configFile = new File(configName);
		if (!configFile.exists()) {
			logger.error("Could not find configuration file " + configName);
			System.exit(1);
		}

		// Parse the configuration file
		try {
			ourInstance.load(new FileReader(configFile));
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
			for (Profile.Section curSection : siteSpecificIni.values()) {
				for (String curKey : curSection.keySet()) {
					//logger.debug("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
					//System.out.println("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
					ourInstance.put(curSection.getName(), curKey, curSection.get(curKey));
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
				for (Profile.Section curSection : siteSpecificIni.values()) {
					for (String curKey : curSection.keySet()) {
						//logger.debug("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
						//System.out.println("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
						ourInstance.put(curSection.getName(), curKey, curSection.get(curKey));
					}
				}
			} catch (InvalidFileFormatException e) {
				logger.error("Site Specific config file is not valid.  Please check the syntax of the file.", e);
			} catch (IOException e) {
				logger.error("Site Specific config file could not be read.", e);
			}
		}

		return ourInstance;
	}

	public static String cleanIniValue(String value) {
		if (value == null) {
			return null;
		}
		value = value.trim();
		if (value.startsWith("\"")) {
			value = value.substring(1);
		}
		if (value.endsWith("\"")) {
			value = value.substring(0, value.length() - 1);
		}
		return value;
	}

	public static boolean getBooleanIniValue(String sectionName, String optionName) {
		String booleanValueStr = cleanIniValue(ourInstance.get(sectionName, optionName));
		if (booleanValueStr != null) {
			return booleanValueStr.equalsIgnoreCase("true") || booleanValueStr.equals("1");
		}
		return false;
	}


	public static Integer getIntIniValue(String sectionName, String optionName) {
		String intValueStr = cleanIniValue(ourInstance.get(sectionName, optionName));
		if (intValueStr != null) {
			return Integer.parseInt(intValueStr);
		}
		return null;
	}

	public static String getIniValue(String sectionName, String optionName) {
		return cleanIniValue(ourInstance.get(sectionName, optionName));
	}

}
