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

public class Marriage {
	public String marriageId;
    public String personId;
    public String spouseName;
    public String spouseId;
    public String marriageDateDay;
    public String marriageDateMonth;
    public String marriageDateYear;
    public String comments;
    
    public Marriage(ResultSet rs, boolean loadId){
    	try {
			if (loadId){
				marriageId = rs.getString("marriageId");
			}
			personId = rs.getString("personId");
	    	if (rs.wasNull()) personId = null;
	    	spouseName = rs.getString("spouseName");
	    	if (rs.wasNull()) spouseName = null;
	    	spouseId = rs.getString("spouseId");
	    	if (rs.wasNull()) spouseId = null;
	    	
	    	marriageDateDay = rs.getString("marriageDateDay");
	    	if (rs.wasNull()) marriageDateDay = null;
	    	marriageDateMonth = rs.getString("marriageDateMonth");
	    	if (rs.wasNull()) marriageDateMonth = null;
	    	marriageDateYear = rs.getString("marriageDateYear");
	    	if (rs.wasNull()) marriageDateYear = null;
	    	
	    	comments = rs.getString("comments");
	    	if (rs.wasNull()) comments = null;
		} catch (SQLException e) {
			System.err.println("Error loading marriage " + e.toString());
		}
    }
}
