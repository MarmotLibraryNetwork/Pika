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

import org.apache.logging.log4j.Logger;

import java.sql.Connection;
import java.sql.ResultSet;
import java.util.regex.Pattern;

/**
 * Pika
 *
 * @author Pascal Brammeier
 * 				Date:   6/14/2022
 */
public class HorizonRecordProcessor extends IlsRecordProcessor{
	HorizonRecordProcessor(GroupedWorkIndexer indexer, Connection pikaConn, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, pikaConn, indexingProfileRS, logger, fullReindex);
		try {
			String pattern = indexingProfileRS.getString("availableStatuses");
			if (pattern != null && pattern.length() > 0) {
				availableStatusesPattern = Pattern.compile("^(" + pattern + ")$");
			}
		} catch (Exception e) {
			logger.error("Could not load available statuses", e);
		}

	}

	private Pattern availableStatusesPattern = null;

	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		String status = itemInfo.getStatusCode();
		if (availableStatusesPattern != null && status != null && !status.isEmpty()) {
			return availableStatusesPattern.matcher(status.toLowerCase()).matches();
		}
		return false;
	}
}
