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

import java.util.HashSet;

/**
 * Contains information about the lexile information related to a title.
 * Pika
 * User: Mark Noble
 * Date: 3/6/14
 * Time: 8:56 AM
 */
public class LexileTitle {
	private       String          title;
	private       String          author;
	private       String          lexileCode;
	private       int             lexileScore = -1;
	private       String          series;
	private final HashSet<String> awards      = new HashSet<>();

	public String getTitle() {
		return title;
	}

	public void setTitle(String title) {
		this.title = title;
	}

	public String getAuthor() {
		return author;
	}

	public void setAuthor(String author) {
		this.author = author;
	}

	public String getLexileCode() {
		return lexileCode;
	}

	public void setLexileCode(String lexileCode) {
		this.lexileCode = lexileCode;
	}

	public int getLexileScore() {
		return lexileScore;
	}

	public void setLexileScore(String lexileScore) throws NumberFormatException {
		if (lexileScore != null && !lexileScore.isEmpty()) {
			int value;
			try {
				value = Integer.parseInt(lexileScore);
			} catch (NumberFormatException e) {
				//TODO ought to log the error
				value = Integer.parseInt(lexileScore.replaceAll("[^0-9]", ""));
				// Strip out non-numeral text; if there is a parse error again, let that throw
			}
			this.lexileScore = value;
		}
	}

	public String getSeries() {
		return series;
	}

	public void setSeries(String series) {
		this.series = series;
	}

	public HashSet<String> getAwards() {
		return awards;
	}

	public void setAwards(String awards) {
		//Remove anything in quotes
		if (awards != null && !awards.isEmpty()) {
			//awards = awards.replaceAll("\\(.*?\\)", "");
			// Remove text within parentheses

			awards = awards.replaceAll("[\\[\\]\"]", "");
			//Remove brackets & double quote marks
			for (String individualAward : awards.split(", ")) {
				if (individualAward.startsWith("'") && individualAward.endsWith("'")) {
					// Remove surrounding single quotes
					individualAward = individualAward.substring(1, individualAward.length() - 1);
				}
				this.awards.add(individualAward.trim().replaceAll(" {2}", " "));
				// clean up double spaces, e.g. 'Robert F. Sibert Informational  Book Award'
				// (Because this.awards is a hashset, it will automatically prevent duplicate entries from
				// being added.)
			}
		}
	}
}
