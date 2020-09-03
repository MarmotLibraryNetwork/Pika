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

public class TestBookcovers extends TestTask{

	@Override
	public String getTestUrl() {
		TestResource testResource = getRandomTestResource();
		if (testResource.getSource().equalsIgnoreCase("econtent")){
			return this.baseUrl + "/bookcover.php?econtent=true&id=" + testResource.getRecord_id() + "&size=medium&isn=" + testResource.getIsbn() + "&upc=" + testResource.getUpc() + "&category=" + testResource.getFormat_category();
		}else{
			return this.baseUrl + "/bookcover.php?id=" + testResource.getRecord_id() + "&size=medium&isn=" + testResource.getIsbn() + "&upc=" + testResource.getUpc() + "&category=" + testResource.getFormat_category();
		}
	}

	@Override
	public boolean validateTest(String pageContents) {
		return true;
	}

	@Override
	public boolean expectHTML() {
		return false;
	}

	@Override
	public boolean expectImage() {
		return true;
	}
}
