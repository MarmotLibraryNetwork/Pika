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

import java.sql.ResultSet;
import java.sql.SQLException;

public class Obituary {
	public String obituaryId;
    public String personId;
    public String source;
    public String dateDay;
    public String dateMonth;
    public String dateYear;
    public String sourcePage;
    public String contents;
    public String picture;
    
    public Obituary(ResultSet rs, boolean loadId){
    	try {
			if (loadId){
				obituaryId = rs.getString("obituaryId");
			}
			personId = rs.getString("personId");
	    	if (rs.wasNull()) personId = null;
	    	source = rs.getString("source");
	    	if (rs.wasNull()) source = null;
	    	sourcePage = rs.getString("sourcePage");
	    	if (rs.wasNull()) sourcePage = null;
	    	
	    	dateDay = rs.getString("dateDay");
	    	if (rs.wasNull()) dateDay = null;
	    	dateMonth = rs.getString("dateMonth");
	    	if (rs.wasNull()) dateMonth = null;
	    	dateYear = rs.getString("dateYear");
	    	if (rs.wasNull()) dateYear = null;
	    	
	    	picture = rs.getString("picture");
	    	if (rs.wasNull()) picture = null;
	    	contents = rs.getString("contents");
	    	if (rs.wasNull()) contents = null;
		} catch (SQLException e) {
			System.err.println("Error loading obituary " + e.toString());
		}
    }
}
