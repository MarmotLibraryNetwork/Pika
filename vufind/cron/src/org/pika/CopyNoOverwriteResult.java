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

class CopyNoOverwriteResult {
	enum CopyResult {
		FILE_ALREADY_EXISTS, FILE_COPIED
	}

	private CopyResult	copyResult;
	private String			newFilename;

	public CopyResult getCopyResult() {
		return copyResult;
	}

	void setCopyResult(CopyResult copyResult) {
		this.copyResult = copyResult;
	}

	public String getNewFilename() {
		return newFilename;
	}

	void setNewFilename(String newFilename) {
		this.newFilename = newFilename;
	}

}
