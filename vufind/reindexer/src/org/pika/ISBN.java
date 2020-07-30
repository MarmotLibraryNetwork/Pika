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

/**
 * Pika
 *
 * Simple Class to handle ISBNs
 * 10 digit isbns are converted to 13 digit isbns
 *
 * @author pbrammeier
 * 		Date:   10/31/2019
 */
public class ISBN {
	private String  isbn        = "";
	private boolean isValidIsbn = false;

	ISBN(String isbn) {
		if (isbn != null) {
			isbn = isbn.replaceAll("[^\\dX]", ""); // Strip any non-numeric characters (except X which can be a 10-digit ISBN check digit)
			if (isbn.length() == 10) {
				isbn = convertISBN10to13(isbn);
			}
			this.isbn = isbn;
			if (isbn.length() == 13) {
				this.isValidIsbn = true;
			}
		}
	}

	boolean isValidIsbn() {
		return isValidIsbn;
	}

	/**
	 * @return Return the ISBN as string
	 */
	@Override
	public String toString() {
		return isbn;
	}

	/**
	 * Convert 10 digit ISBNs to standard 13 digit ISBNs
	 *
	 * @param isbn10 a 10 digit ISBN
	 * @return a 13 digit ISBN
	 */
	static String convertISBN10to13(String isbn10) {
		if (isbn10.length() != 10) {
			return "";
		}
		String isbn = "978" + isbn10.substring(0, 9);
		//Calculate the 13 digit checksum
		int     sumOfDigits   = 0;
		boolean useMultiplier = false;
		for (char c: isbn.toCharArray()){
			int curDigit = Character.getNumericValue(c);
			sumOfDigits += useMultiplier ? 3 * curDigit : curDigit;
			useMultiplier = !useMultiplier;  // multiple by 3 every other digit
		}
//		int sumOfDigits = 0;
//		for (int i = 0; i < 12; i++) {
//			int multiplier = (i % 2 == 1) ? 3 : 1; // multiple the value of every second digit by 3
//			int curDigit   = Integer.parseInt(Character.toString(isbn.charAt(i)));
//			sumOfDigits += multiplier * curDigit;
//		}
		int modValue      = sumOfDigits % 10;
		int checksumDigit = (modValue == 0) ? 0 : 10 - modValue;
		return isbn + checksumDigit;
	}
}