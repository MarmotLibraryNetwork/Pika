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

/**
 * Stores information about a record that needs to be updated
 *
 * Created by mnoble on 10/31/2017.
 */
class MetaAvailUpdateData {
	public long databaseId;
	public long crossRefId;
	public long lastMetadataCheck;
	public long lastMetadataChange;
	public long lastAvailabilityChange;
	public String overDriveId;

	public boolean metadataUpdated = false;

	public boolean hadAvailabilityErrors = false;
	public boolean hadMetadataErrors = false;

}
