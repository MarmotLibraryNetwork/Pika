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
