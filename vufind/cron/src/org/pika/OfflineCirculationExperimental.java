package org.pika;

import org.apache.logging.log4j.Logger;
import org.ini4j.Profile;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.concurrent.Executor;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicInteger;

public class OfflineCirculationExperimental implements IProcessHandler  {

	private CronProcessLogEntry processLog;
	private Logger              logger;
	private String              userApiToken = "";
	private long                timeOut      = 90L;

	public void doCronProcess(String serverName, Profile.Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger, PikaSystemVariables systemVariables) {
		this.logger = logger;
		processLog  = new CronProcessLogEntry(cronEntry.getLogEntryId(), "Offline Circulation");
		processLog.saveToDatabase(pikaConn, logger);
		userApiToken = PikaConfigIni.getIniValue("System", "userApiToken");

		//Check to see if the system is offline
		Boolean offline_mode_when_offline_login_allowed = systemVariables.getBooleanValuedVariable("offline_mode_when_offline_login_allowed");
		if (offline_mode_when_offline_login_allowed == null){
			offline_mode_when_offline_login_allowed = false;
		}
		if (offline_mode_when_offline_login_allowed || PikaConfigIni.getBooleanIniValue("Catalog", "offline")) {
			logger.error("Pika Offline Mode is currently on. Ensure the ILS is available before running OfflineCirculation.");
			processLog.addNote("Not processing offline circulation because the system is currently offline.");
		}
		else {
			if (processSettings.containsKey("TimeOut")){
				String timeOutStr = processSettings.get("TimeOut");
				long timeout = Long.parseLong(timeOutStr);
				if (timeout > 0L){
					timeOut = timeout;
				}
			}

				processOfflineCirculationEntriesViaSierraAPI(pikaConn, processLog);
		}
		processLog.setFinished();
		processLog.saveToDatabase(pikaConn, logger);
	}

	/**
 * Processes any checkouts and check-ins that were done while the system was offline.
 *
 * @param pikaConn Connection to the database
 */
	private void processOfflineCirculationEntriesViaSierraAPI(Connection pikaConn, CronProcessLogEntry processLog) {
		ExecutorService executor = Executors.newFixedThreadPool(5);
		processLog.addNote("Processing offline checkouts via Sierra API");
		try (
						PreparedStatement circulationEntryToProcessStmt = pikaConn.prepareStatement("SELECT offline_circulation.* FROM offline_circulation WHERE status='Not Processed' ORDER BY login ASC, patronBarcode ASC, timeEntered ASC");
						PreparedStatement sierraVendorOpacUrlStmt = pikaConn.prepareStatement("SELECT vendorOpacUrl FROM account_profiles WHERE name = 'ils'")
		) {
			try (ResultSet sierraVendorOpacUrlRS = sierraVendorOpacUrlStmt.executeQuery()) {
				if (sierraVendorOpacUrlRS.next()) {
					String apiVersion = PikaConfigIni.getIniValue("Catalog", "api_version");
					if (apiVersion == null || apiVersion.isEmpty()) {
						logger.error("Sierra API version must be set.");
					} else {
						String baseApiUrl = sierraVendorOpacUrlRS.getString("vendorOpacUrl") + "/iii/sierra-api/v" + apiVersion;

						try (ResultSet circulationEntriesToProcessRS = circulationEntryToProcessStmt.executeQuery()) {
							while (circulationEntriesToProcessRS.next()) {
								Thread t = new Thread(new multipleOfflineCircs(baseApiUrl, circulationEntriesToProcessRS, pikaConn, logger, processLog));
								executor.execute(t);
							}
							executor.shutdown();
						}
					}
				}
			}
		} catch (SQLException e) {
			processLog.incErrors();
			processLog.addNote("Error processing offline circs " + e);
			logger.error("Error processing offline circs", e);
		}
		try {
			if (executor.awaitTermination(timeOut, TimeUnit.MINUTES)) {
				logger.info("Finished processing threads.");
				String note = multipleOfflineCircs.numProcessed + " offline circs processed.";
				logger.info(note);
				processLog.addNote(note);
				processLog.saveToDatabase(pikaConn, logger);
			}
		} catch (InterruptedException e) {
			logger.error("Interrupt Error : ", e);
		}
	}
}

