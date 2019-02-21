package org.vufind;

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
