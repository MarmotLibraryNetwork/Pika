package org.pika;

class RecordIdentifier {
	private String  source;
	private String  identifier;

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

	String getSourceAndId(){
		return toString();
	}

	String getSource() {
		return source;
	}

	String getIdentifier() {
		return identifier;
	}

	void setValue(String source, String identifier) {
		this.source     = source.toLowerCase();
		identifier      = identifier.trim();
		this.identifier = identifier;
	}

}

