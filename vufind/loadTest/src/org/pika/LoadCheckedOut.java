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

public class LoadCheckedOut extends TestTask{
	@Override
	public String getTestUrl() {
		//Get test user
		TestUser testUser = getRandomTestUser();
		
		return this.baseUrl + "/API/UserAPI?method=getPatronCheckedOutItems&username=" + testUser.getUsername() + "&password=" + testUser.getPassword();
	}

	@Override
	public boolean validateTest(String pageContents) {
		if (pageContents.matches("(?si)\\{\"result\":\\{\"success\":true,\"checkedOutItems\":.*?\\}\\}")){
			return true;
		}
		return false;
	}
	
	@Override
	public boolean expectHTML() {
		return true;
	}

	@Override
	public boolean expectImage() {
		return false;
	}
}
