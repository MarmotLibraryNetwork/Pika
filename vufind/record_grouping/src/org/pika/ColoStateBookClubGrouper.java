/*
 * Pika Discovery Layer
 * Copyright (C) 2025  Marmot Library Network
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

import java.sql.Connection;

public class ColoStateBookClubGrouper extends MarcRecordGrouper{

	private final String phraseToRemove = "(Colorado State Library Book Club Collection)";

	/**
	 * Creates a record grouping processor that saves results to the database.
	 *
	 * @param pikaConn - The Connection to the Pika database
	 * @param profile  - The profile that we are grouping records for
	 * @param logger   - A logger to store debug and error messages to.
	 */
	public ColoStateBookClubGrouper(Connection pikaConn, IndexingProfile profile, Logger logger) {
		super(pikaConn, profile, logger);
	}

	/**
	 * Remove phraseToRemove from 245a before doing the standard
	 * grouping title normalizations.
	 *
	 * @param field245 MARC field for the title
	 * @return The basic title to begin standard title normalizations with
	 */
	@Override
	protected String getBasicTitle(DataField field245) {
		return super.getBasicTitle(field245).replace(phraseToRemove, "");
	}

	/**
	 * Remove phraseToRemove from 245 subtitle fields before doing the standard
	 * grouping title normalizations.
	 *
	 * @param field245 MARC field for the title
	 * @return The basic subtitle to begin standard title normalizations with
	 */
	@Override
	protected String getBasicSubtitle(DataField field245) {
		return super.getBasicSubtitle(field245).replace(phraseToRemove, "");
	}
}
