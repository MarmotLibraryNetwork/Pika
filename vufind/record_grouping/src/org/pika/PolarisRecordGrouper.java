/*
 * Pika Discovery Layer
 * Copyright (C) 2024  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

package org.pika;

import org.apache.logging.log4j.Logger;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.SQLException;
import java.util.List;

public class PolarisRecordGrouper extends MarcRecordGrouper {

	char itemRecordNumberSubfield;

	private PreparedStatement itemToRecordStatement;
	/**
	 * Creates a record grouping processor that saves results to the database.
	 *
	 * @param pikaConn - The Connection to the Pika database
	 * @param profile  - The profile that we are grouping records for
	 * @param logger   - A logger to store debug and error messages to.
	 */
	public PolarisRecordGrouper(Connection pikaConn, IndexingProfile profile, Logger logger) {
		super(pikaConn, profile, logger);
		if (profile.itemRecordNumberSubfield == ' ') {
			logger.error("Index profile itemRecordNumberSubfield is not set for Polaris ils profile");
			System.exit(0);
		}
		itemRecordNumberSubfield = profile.itemRecordNumberSubfield;
		try {
			itemToRecordStatement = pikaConn.prepareStatement("INSERT INTO `ils_itemIdToRecordId` (itemId, recordId) VALUES (?, ?) ON DUPLICATE KEY UPDATE recordId=VALUE(recordId)");
		} catch (SQLException e) {
			logger.error("Error preparing statement for Polaris item to record Ids");
		}
	}

	/**
	 * @param marcRecord
	 * @param recordSource
	 * @param doAutomaticEcontentSuppression
	 * @return
	 */
	@Override
	RecordIdentifier getPrimaryIdentifierFromMarcRecord(Record marcRecord, String recordSource, boolean doAutomaticEcontentSuppression) {
		RecordIdentifier identifier = super.getPrimaryIdentifierFromMarcRecord(marcRecord, recordSource, doAutomaticEcontentSuppression);

		if (identifier != null) {
			List<DataField> itemFields = getDataFields(marcRecord, itemTag);
			for (DataField itemField : itemFields) {
				Subfield subfield = itemField.getSubfield(itemRecordNumberSubfield);
				if (subfield != null) {
					String   itemId   = subfield.getData();
					try {
						// Ignore for suppressed items
						//TODO: should use a deleted date?  Delete with database clean-up at 6 months
						itemToRecordStatement.setString(1, itemId);
						itemToRecordStatement.setString(2, identifier.getIdentifier());
						int result = itemToRecordStatement.executeUpdate();
					} catch (SQLException e) {
						logger.error("Error setting item to record entry");
					}
				} else {
					if (!identifier.isSuppressed()) {
						logger.error("On record {}, item without id: {}", identifier, itemField.toString());
					}
				}
			}
		}


		return identifier;
	}
}
