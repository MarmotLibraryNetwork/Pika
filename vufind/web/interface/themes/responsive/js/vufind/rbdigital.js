/*
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
 *
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
VuFind.RBdigital = (function() {
	return {

		returnRBdigitalMagazine: function(userId, issueId) {
			VuFind.confirm('Are you sure you want to return this title?', function () {
				VuFind.Account.ajaxLogin(function (){
					VuFind.showMessage("Returning Title", "Returning your magazine.");
					var url = "/RBdigital/AJAX",
							params = {
								method: 'returnRBdigitalMagazine',
								issueId:issueId,
								userId: userId
							};
					$.getJSON(url, params, function (data) {
						VuFind.showMessage(data.success ? 'Success' : 'Error', data.message, data.success, data.success);
					}).fail(VuFind.ajaxFail);
				});
			});
			return false;
		},

		// readMagazineOnline: function(issueId, userId) {
		// 	VuFind.Account.ajaxLogin(function (){
		// 		var url = "/RBdigital/AJAX",
		// 				params = {
		// 					method: 'readMagazineOnline',
		// 					issueId:issueId,
		// 					userId: userId
		// 				};
		// 		$.getJSON(url, params, function (data) {
		// 				// console log
		// 		})
		// 	});
		// }



		// end returnRBdigitalMagazine
	}
}
(VuFind.RBdigital || {}));
