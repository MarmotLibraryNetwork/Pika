package org.pika;

import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;
import org.json.JSONArray;
import org.json.JSONObject;

import java.io.*;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.util.Date;

public class RBdigitalExportMain {
    private static Logger  logger = Logger.getLogger(RBdigitalExportMain.class);
    private static String  serverName;
    private static String  rbdApiUrl;
    private static Long    lastExportTime;
    private static Long    startTimeStamp;
    private static boolean updateTitlesInDBHadErrors = false;

    //Reporting information
    private static long              hooplaExportLogId;
    private static PreparedStatement addNoteToHooplaExportLogStmt;


    public static void main(String[] args) {
        if (args.length == 0) {
            System.out.println("Server name must be specified in the command line");
            System.exit(1);
        }
        serverName = args[0];
        String  singleRecordToProcess = null;
        boolean doFullReload          = false;

        // do args checks here

        File log4jFile = new File("../../sites/" + serverName + "/conf/log4j.rbdigital_export.properties");
        if (log4jFile.exists()) {
            PropertyConfigurator.configure(log4jFile.getAbsolutePath());
        } else {
            log4jFile = new File("../../sites/default/conf/log4j.rbdigital_export.properties");
            if (log4jFile.exists()) {
                PropertyConfigurator.configure(log4jFile.getAbsolutePath());
            } else {
                System.out.println("Could not find log4j configuration " + log4jFile.toString());
            }
        }

        Date startTime = new Date();
        logger.info(startTime.toString() + ": Starting RBdital Export");
        startTimeStamp = startTime.getTime() / 1000;

        // Read the base INI file to get information about the server (current directory/cron/config.ini)
        PikaConfigIni.loadConfigFile("config.ini", serverName, logger);

        //Connect to the pika database
        Connection pikaConn = null;
        try {
            String databaseConnectionInfo = PikaConfigIni.getIniValue("Database", "database_vufind_jdbc");
            if (databaseConnectionInfo != null) {
                pikaConn = DriverManager.getConnection(databaseConnectionInfo);
            } else {
                logger.error("No Pika database connection info");
                System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
            }
        } catch (Exception e) {
            logger.error("Error connecting to Pika database " + e.toString());
            System.exit(2); // Exiting with a status code of 2 so that our executing bash scripts knows there has been a database communication error
        }
    }
    // end main

    /**
     * Method that fetches and processes data from the Hoopla API.
     *
     * @param pikaConn     Connection to the Pika Database.
     * @param startTime    The time to limit responses to from the Hoopla API.  Fetch changes since this time.
     * @param doFullReload Fetch all the data in the Hoopla API
     * @return Return if the updating completed with out errors
     */
    private static boolean exportHooplaData(Connection pikaConn, Long startTime, boolean doFullReload) {
        try {
            //Find a library id to get data from
            String hooplaLibraryId = getRBdigitalLibraryId(pikaConn);
            if (hooplaLibraryId == null) {
                logger.error("No hoopla library id found");
                addNoteToRBdigitalExportLog("No hoopla library id found");
                return false;
            } else {
                addNoteToRBdigitalExportLog("Hoopla library id is " + hooplaLibraryId);
            }

            String accessToken = getAccessToken();
            if (accessToken == null || accessToken.isEmpty()) {
                addNoteToRBdigitalExportLog("Failed to get an Access Token for the API.");
                return false;
            }

            if (doFullReload) {
                addNoteToRBdigitalExportLog("Doing a full reload of Hoopla data.");
            }

            //Formulate the first call depending on if we are doing a full reload or not
            String url = rbdApiUrl + "/api/v1/libraries/" + hooplaLibraryId + "/content";
            if (!doFullReload && startTime != null) {
                url += "?startTime=" + startTime;
                addNoteToRBdigitalExportLog("Fetching updates since " + startTime);
            }

            // Initial Call
            int             numProcessed = 0;
            URLPostResponse response     = getURL(url, accessToken);
            JSONObject      responseJSON = new JSONObject(response.getMessage());
            if (responseJSON.has("titles")) {
                JSONArray responseTitles = responseJSON.getJSONArray("titles");
                if (responseTitles != null && responseTitles.length() > 0) {
                    numProcessed += updateTitlesInDB(pikaConn, responseTitles);
                } else {
                    logger.warn("Hoopla Extract call had no titles for updating: " + url);
                    if (startTime != null) {
                        addNoteToRBdigitalExportLog("Hoopla had no updates since " + startTime);
                    } else if (doFullReload) {
                        addNoteToRBdigitalExportLog("Hoopla gave no information for a full Reload");
                        logger.error("Hoopla gave no information for a full Reload. " + url);
                    }
                    // If working on a short time frame, it is possible there are no updates. But we expect to do this no more that once a day at this point
                    // so we expect there to be changes.
                    // Having this warning will give us a hint if there is something wrong with the data in the calls
                }

                // Addition Calls if needed
                String startToken = null;
                if (responseJSON.has("nextStartToken")) {
                    startToken = responseJSON.getString("nextStartToken");
                }
                while (startToken != null) {
                    url = rbdApiUrl + "/api/v1/libraries/" + hooplaLibraryId + "/content?startToken=" + startToken;
                    if (!doFullReload && startTime != null) {
                        url += "&startTime=" + startTime;
                    }
                    response     = getURL(url, accessToken);
                    responseJSON = new JSONObject(response.getMessage());
                    if (responseJSON.has("titles")) {
                        responseTitles = responseJSON.getJSONArray("titles");
                        if (responseTitles != null && responseTitles.length() > 0) {
                            numProcessed += updateTitlesInDB(pikaConn, responseTitles);
                        }
                    }
                    if (responseJSON.has("nextStartToken")) {
                        startToken = responseJSON.getString("nextStartToken");
                    } else {
                        startToken = null;
                    }
                    if (numProcessed % 10000 == 0) {
                        addNoteToRBdigitalExportLog("Processed " + numProcessed + " records from hoopla");
                    }
                }
                addNoteToRBdigitalExportLog("Processed a total of " + numProcessed + " records from hoopla");

            }
        } catch (Exception e) {
            logger.error("Error exporting hoopla data", e);
            addNoteToRBdigitalExportLog("Error exporting hoopla data " + e.toString());
            return false;
        }
        // UpdateTitlesInDB can also have errors. If it does it sets updateTitlesInDBHadErrors to true;
        return !updateTitlesInDBHadErrors;
    }

    private static String getRBdigitalLibraryId(Connection pikaConn) {
        ResultSet getLibraryIdRS;
        try (PreparedStatement getLibraryIdStmt = pikaConn.prepareStatement("SELECT hooplaLibraryID FROM library WHERE hooplaLibraryID IS NOT NULL AND hooplaLibraryID != 0 LIMIT 1")) {
            getLibraryIdRS = getLibraryIdStmt.executeQuery();
            if (getLibraryIdRS.next()) {
                return getLibraryIdRS.getString("hooplaLibraryID");
            }
        } catch (SQLException e) {
            logger.error("Failed to retrieve a Hoopla library id", e);
        }
        return null;
    }

    private static PreparedStatement updateRBdigitalMagazineInDB = null;
    private static PreparedStatement markGroupedWorkForBibAsChangedStmt = null;

    private static int updateTitlesInDB(Connection pikaConn, JSONArray responseTitles) {
        int numUpdates = 0;
        try {
            if (updateRBdigitalMagazineInDB == null) {
                updateRBdigitalMagazineInDB = pikaConn.prepareStatement("INSERT INTO hoopla_export (hooplaId, active, title, kind, pa, demo, profanity, rating, abridged, children, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY " +
                        "UPDATE active = VALUES(active), title = VALUES(title), kind = VALUES(kind), pa = VALUES(pa), demo = VALUES(demo), profanity = VALUES(profanity), " +
                        "rating = VALUES(rating), abridged = VALUES(abridged), children = VALUES(children), price = VALUES(price)");
            }
            for (int i = 0; i < responseTitles.length(); i++) {
                JSONObject curTitle = responseTitles.getJSONObject(i);
                long       titleId  = curTitle.getLong("titleId");
                updateRBdigitalMagazineInDB.setLong(1, titleId);
                updateRBdigitalMagazineInDB.setBoolean(2, curTitle.getBoolean("active"));
                updateRBdigitalMagazineInDB.setString(3, curTitle.getString("title"));
                updateRBdigitalMagazineInDB.setString(4, curTitle.getString("kind"));
                updateRBdigitalMagazineInDB.setBoolean(5, curTitle.getBoolean("pa"));
                updateRBdigitalMagazineInDB.setBoolean(6, curTitle.getBoolean("demo"));
                updateRBdigitalMagazineInDB.setBoolean(7, curTitle.getBoolean("profanity"));
                updateRBdigitalMagazineInDB.setString(8, curTitle.has("rating") ? curTitle.getString("rating") : "");
                updateRBdigitalMagazineInDB.setBoolean(9, curTitle.getBoolean("abridged"));
                updateRBdigitalMagazineInDB.setBoolean(10, curTitle.getBoolean("children"));
                updateRBdigitalMagazineInDB.setDouble(11, curTitle.getDouble("price"));

                int updated = updateRBdigitalMagazineInDB.executeUpdate();
                if (updated > 0) {
                    numUpdates++;
                    markGroupedWorkForReindexing(pikaConn, titleId);
                }
            }

        } catch (Exception e) {
            logger.error("Error updating hoopla data in Pika database", e);
            addNoteToRBdigitalExportLog("Error updating hoopla data in Pika database " + e.toString());
            updateTitlesInDBHadErrors = true;
        }
        return numUpdates;
    }

    private static StringBuffer     notes      = new StringBuffer();
    private static SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");

    private static void addNoteToRBdigitalExportLog(String note) {
        try {
            Date date = new Date();
            notes.append("<br>").append(dateFormat.format(date)).append(": ").append(note);
            addNoteToHooplaExportLogStmt.setString(1, trimTo(65535, notes.toString()));
            addNoteToHooplaExportLogStmt.setLong(2, new Date().getTime() / 1000);
            addNoteToHooplaExportLogStmt.setLong(3, hooplaExportLogId);
            addNoteToHooplaExportLogStmt.executeUpdate();
            logger.info(note);
        } catch (SQLException e) {
            logger.error("Error adding note to Export Log", e);
        }
    }

    private static String trimTo(int maxCharacters, String stringToTrim) {
        if (stringToTrim == null) {
            return null;
        }
        if (stringToTrim.length() > maxCharacters) {
            stringToTrim = stringToTrim.substring(0, maxCharacters);
        }
        return stringToTrim.trim();
    }

}
