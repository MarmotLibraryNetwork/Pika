package org.pika;

/**
 * Description goes here
 * Rampart Marc Conversion
 * User: Mark Noble
 * Date: 10/18/13
 * Time: 10:27 AM
 */
class RecordIdentifier {
	private String  source;
	private String  identifier;
	private boolean suppressed;

	RecordIdentifier(String source, String identifier){
		setValue(source, identifier);
	}

	@Override
	public int hashCode() {
		return toString().hashCode();
	}

	private String myString = null;
	public String toString(){
		if (myString == null && source != null && identifier != null){
			myString = source + ":" + identifier;
		}
		return myString;
	}

	@Override
	public boolean equals(Object obj) {
		if (obj instanceof  RecordIdentifier){
			RecordIdentifier tmpObj = (RecordIdentifier)obj;
			return (tmpObj.source.equals(source) && tmpObj.identifier.equals(identifier));
		}else{
			return false;
		}
	}

	String getSource() {
		return source;
	}

	boolean isValid() {
		return identifier.length() > 0;
	}

	String getIdentifier() {
		return identifier;
	}

	void setValue(String source, String identifier) {
		this.source     = source.toLowerCase();
		identifier      = identifier.trim();
		this.identifier = identifier;
	}

	boolean isSuppressed() {
		return suppressed;
	}

	void setSuppressed(boolean suppressed) {
		this.suppressed = suppressed;
	}

}
