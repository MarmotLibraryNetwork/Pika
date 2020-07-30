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

package org.pika;

public enum ItemStatus {
	ONSHELF,
	LIBRARYUSEONLY,
	ONORDER,
	INPROCESSING,
	CATALOGING,
	CHECKEDOUT,
	ONHOLDSHELF,
	INTRANSIT,
	INREPAIRS,
	DAMAGED,
	LOST,
	WITHDRAWN,
	SUPPRESSED
	;

	@Override
	public String toString() {
		switch (this){
			case ONSHELF:
				return "On Shelf";
			case LIBRARYUSEONLY:
				return "Library Use Only";
			case ONORDER:
				return "On Order";
			case INPROCESSING:
				return "In Processing";
			case CATALOGING:
				return "Cataloging";
			case CHECKEDOUT:
				return "Checked Out";
			case ONHOLDSHELF:
				return "On Hold Shelf";
			case INTRANSIT:
				return "In Transit";
			case LOST:
				return "Lost";
			case DAMAGED:
				return "Damaged";
			case WITHDRAWN:
				return "Withdrawn";
			case INREPAIRS:
				return "In Repair";
			case SUPPRESSED:
				return "Suppressed";
		}
		return null;
	}

}
