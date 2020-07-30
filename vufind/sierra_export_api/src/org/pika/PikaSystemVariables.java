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

import org.apache.log4j.Logger;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

/**
 * Pika
 *
 * @author pbrammeier
 * 		Date:   6/13/2019
 */
public class PikaSystemVariables {
	Logger     logger;
	Connection pikaConn;

	public PikaSystemVariables(Logger logger, Connection pikaConn) {
		this.logger   = logger;
		this.pikaConn = pikaConn;
	}

	public Long getVariableId(String name) {
		try (PreparedStatement preparedStatement = pikaConn.prepareStatement("SELECT * from variables WHERE name = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
			preparedStatement.setString(1, name);
			try (ResultSet resultSet = preparedStatement.executeQuery()) {
				if (resultSet.next()) {
					return resultSet.getLong("id");
				}
			}
		} catch (Exception e) {
			logger.error("Unable to load " + name + " Id from variables", e);
		}
		return null;
	}

	public Long getLongValuedVariable(String name) {
		try (PreparedStatement preparedStatement = pikaConn.prepareStatement("SELECT * from variables WHERE name = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
			preparedStatement.setString(1, name);
			try (ResultSet resultSet = preparedStatement.executeQuery()) {
				if (resultSet.next()) {
					return resultSet.getLong("value");
				}
			}
		} catch (Exception e) {
			logger.error("Unable to load " + name + " from variables", e);
		}
		return null;
	}

	public Boolean getBooleanValuedVariable(String name) {
		try (PreparedStatement preparedStatement = pikaConn.prepareStatement("SELECT * from variables WHERE name = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
			preparedStatement.setString(1, name);
			try (ResultSet resultSet = preparedStatement.executeQuery()) {
				if (resultSet.next()) {
					String value = resultSet.getString("value");
					return value.equals("1") || value.equalsIgnoreCase("true");
				}
			}
		} catch (Exception e) {
			logger.error("Unable to load " + name + " from variables", e);
		}
		return null;
	}

	public String getStringValuedVariable(String name) {
		try (PreparedStatement preparedStatement = pikaConn.prepareStatement("SELECT * FROM variables WHERE name = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
			preparedStatement.setString(1, name);
			try (ResultSet resultSet = preparedStatement.executeQuery()) {
				if (resultSet.next()) {
					return resultSet.getString("value");
				}
			}
		} catch (Exception e) {
			logger.error("Unable to load " + name + " from variables", e);
		}
		return null;
	}

	public boolean setVariable(String name, Long value) {
		return setVariable(name, value.toString());
	}

	public boolean setVariable(String name, boolean value) {
		return setVariable(name, Boolean.toString(value));
	}

	public boolean setVariable(String name, String value) {
		if (name != null && !name.isEmpty()) {
			try (PreparedStatement preparedStatement = pikaConn.prepareStatement("INSERT INTO variables (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)")) {
				preparedStatement.setString(1, name);
				preparedStatement.setString(2, value);
				int result = preparedStatement.executeUpdate();
				return result == 1;
			} catch (SQLException e) {
				logger.error("Unable to save variable " + name, e);
			}
		}
		return false;
	}

	public boolean setVariableById(Long id, String value) {
		if (id != null) {
			try (PreparedStatement updateVariableStmt = pikaConn.prepareStatement("UPDATE variables set value = ? WHERE id = ?")) {
				updateVariableStmt.setString(1, value);
				updateVariableStmt.setLong(2, id);
				int result = updateVariableStmt.executeUpdate();
				return result == 1;
			} catch (SQLException e) {
				logger.error("Unable to save variable " + id, e);
			}
		}
		return false;
	}
}
