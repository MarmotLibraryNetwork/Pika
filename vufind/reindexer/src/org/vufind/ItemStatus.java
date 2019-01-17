package org.vufind;

public enum ItemStatus {
	ONSHELF,
	LIBRARYUSEONLY,
	ONORDER,
	CHECKEDOUT,
	ONHOLDSHELF,
	INTRANSIT,
	LONGOVERDUE, //TODO: going to use?
	LOST,
	WITHDRAWN,
	DAMAGED,
	BILLED, //TODO: going to use?
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
		}
		return null;
	}

}
