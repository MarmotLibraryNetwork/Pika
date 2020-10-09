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

import org.json.JSONException;
import org.json.JSONObject;

import java.util.HashSet;

/**
 * Information about an OverDrive Title as taken from the API
 * specifically the products call with URLs of the form:
 * https://api.overdrive.com/v2/collections/{collectionId}/products/{productId}
 */
public class OverDriveRecordInfo {
	private String id; // The overdrive Id
	private long crossRefId;
	private String mediaType;
	private String title;
	private String subtitle;
	private String series;
	private String primaryCreatorRole;
	private String primaryCreatorName;
	private HashSet<String> formats = new HashSet<>();
	private String coverImage;
	private HashSet<Long> collections = new HashSet<>(); // libraryIds for the collections that own this title
	//Data from metadata call
	private String rawData;

	public OverDriveRecordInfo(){}

	/**
	 * Load a product entry from the API into an instance of OverDriveRecordInfo
	 *
	 * @param libraryId  the pika library id for an Advantage collection of the shared collection id
	 * @param curProduct The JSON Object representing the main title information from the API
	 * @throws JSONException
	 */
	public OverDriveRecordInfo(Long libraryId, JSONObject curProduct) throws JSONException {
		String curProductId = curProduct.getString("id");
		this.setId(curProductId);

		if (curProduct.has("title")) {
			this.setTitle(curProduct.getString("title"));
		}
		this.setCrossRefId(curProduct.getLong("crossRefId"));
		if (curProduct.has("subtitle")) {
			this.setSubtitle(curProduct.getString("subtitle"));
		}
		this.setMediaType(curProduct.getString("mediaType"));
		if (curProduct.has("series")) {
			String series = curProduct.getString("series");
			if (series.length() > 215) {
				series = series.substring(0, 215);
			}
			this.setSeries(series);

		}
		if (curProduct.has("primaryCreator")) {
			this.setPrimaryCreatorName(curProduct.getJSONObject("primaryCreator").getString("name"));
			this.setPrimaryCreatorRole(curProduct.getJSONObject("primaryCreator").getString("role"));
		}
		if (curProduct.has("formats")) {
			for (int k = 0; k < curProduct.getJSONArray("formats").length(); k++) {
				this.getFormats().add(curProduct.getJSONArray("formats").getJSONObject(k).getString("id"));
			}
		}
		if (curProduct.has("images") && curProduct.getJSONObject("images").has("thumbnail")) {
			String thumbnailUrl = curProduct.getJSONObject("images").getJSONObject("thumbnail").getString("href");
			this.setCoverImage(thumbnailUrl);
		}
		this.getCollections().add(libraryId);
		this.setRawData(curProduct.toString(2));
	}

	public String getRawData() {
		return rawData;
	}

	public void setRawData(String rawData) {
		this.rawData = rawData;
	}

	public String getId() {
		return id;
	}
	public void setId(String id) {
		this.id = id.toLowerCase();
	}
	public long getCrossRefId(){
		return crossRefId;
	}
	public void setCrossRefId(long crossRefId){
		this.crossRefId = crossRefId;
	}
	public String getMediaType() {
		return mediaType;
	}
	public void setMediaType(String mediaType) {
		this.mediaType = mediaType;
	}
	public String getTitle() {
		return title;
	}
	public void setTitle(String title) {
		this.title = title.replaceAll("&#174;", "®");
	}
	public String getSeries() {
		return series;
	}
	public void setSeries(String series) {
		this.series = series;
	}
	
	public String getPrimaryCreatorRole() {
		return primaryCreatorRole;
	}
	public void setPrimaryCreatorRole(String primaryCreatorRole) {
		this.primaryCreatorRole = primaryCreatorRole;
	}
	public String getPrimaryCreatorName() {
		return primaryCreatorName;
	}
	public void setPrimaryCreatorName(String primaryCreatorName) {
		this.primaryCreatorName = primaryCreatorName;
	}
	public HashSet<String> getFormats() {
		return formats;
	}
	public String getCoverImage() {
		return coverImage;
	}
	public void setCoverImage(String coverImage) {
		this.coverImage = coverImage;
	}
	public HashSet<Long> getCollections() {
		return collections;
	}

	public String getSubtitle() {
		return subtitle;
	}

	public void setSubtitle(String subtitle) {
		this.subtitle = subtitle;
	}


}
