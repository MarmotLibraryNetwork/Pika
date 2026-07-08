/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

Pika.Prospector = (function(){
	return {
		getProspectorResults: function(prospectorNumTitlesToLoad, prospectorSavedSearchId){
			var url = "/Search/AJAX",
					params = {
						'method': 'getProspectorResults',
						prospectorNumTitlesToLoad: prospectorNumTitlesToLoad,
						prospectorSavedSearchId: prospectorSavedSearchId,
					};
			$.get(url, params, function (data) {
				$("#prospectorSearchResultsPlaceholder").html(data);
			});
		},

		loadRelatedProspectorTitles: function (id) {
			var url = "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
					params = {'method': 'getProspectorInfo'};
			$.getJSON(url, params, function (data) {
				if (data.numTitles === 0) {
					$("#prospectorPanel").hide();
				}else{
					$("#inProspectorPlaceholder").html(data.formattedData);
				}
			});
		}
	}
}(Pika.Prospector || {}));