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
		try (PreparedStatement preparedStatement = pikaConn.prepareStatement("SELECT * from variables WHERE name = '?'", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY)) {
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

	public boolean setVariable(String name, Long value) {
		if (name != null && !name.isEmpty()) {
			try (PreparedStatement preparedStatement = pikaConn.prepareStatement("INSERT INTO variables (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)")) {
				preparedStatement.setString(1, name);
				preparedStatement.setString(2, value.toString());
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
