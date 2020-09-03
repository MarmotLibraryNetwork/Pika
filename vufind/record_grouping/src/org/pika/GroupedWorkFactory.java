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

import java.sql.Connection;

/**
 * Basic Grouped Work Object factory
 * Pika
 * User: Mark Noble
 * Date: 1/26/2015
 * Time: 8:57 AM
 */
class GroupedWorkFactory {
	static GroupedWorkBase getInstance(int version, Connection pikaConn){
		switch (version){
			case 1:
				return new GroupedWork1();
			case 2:
				return new GroupedWork2();
			case 3:
				return new GroupedWork3();
			case 4:
				return new GroupedWork4();
			case 5:
			default:
				return new GroupedWork5(pikaConn);
		}
	}

	static GroupedWorkBase getInstance(int version){
		switch (version){
			case 1:
				return new GroupedWork1();
			case 2:
				return new GroupedWork2();
			case 3:
				return new GroupedWork3();
			case 4:
				return new GroupedWork4();
			case 5:
			default:
				return new GroupedWork5();
		}
	}
}
