package org.pika;

import java.sql.Connection;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile.Section;

public interface IProcessHandler {
	public void doCronProcess(String serverName, Ini configIni, Section processSettings, Connection pikaConn, Connection econtentConn, CronLogEntry cronEntry, Logger logger);
}
