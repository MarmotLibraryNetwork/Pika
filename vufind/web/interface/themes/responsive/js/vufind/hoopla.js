VuFind.Hoopla = (function(){
	return {
		checkOutHooplaTitle: function (hooplaId, patronId) {
			VuFind.Account.ajaxLogin(function (){
				if (typeof patronId === 'undefined') {
					patronId = $('#patronId', '#pickupLocationOptions').val(); // Lookup selected user from the options form
				}
				var url = Globals.path + '/Hoopla/'+ hooplaId + '/AJAX',
						params = {
							'method' : 'checkOutHooplaTitle',
							patronId : patronId
						};
				if ($('#stopHooplaConfirmation').prop('checked')){
					params['stopHooplaConfirmation'] = true;
				}
				$.getJSON(url, params, function (data) {
					if (data.success) {
						VuFind.showMessageWithButtons(data.title, data.message, data.buttons);
					} else {
						VuFind.showMessage("Checking Out Title", data.message);
					}
				}).fail(VuFind.ajaxFail)
			});
			return false;
		},

		getHooplaCheckOutPrompt: function (hooplaId) {
			VuFind.Account.ajaxLogin(function (){
				var url = Globals.path + "/Hoopla/" + hooplaId + "/AJAX?method=getHooplaCheckOutPrompt";
				$.getJSON(url, function (data) {
					VuFind.showMessageWithButtons(data.title, data.body, data.buttons);
				}).fail(VuFind.ajaxFail);
			});
			return false;
		},

		returnHooplaTitle: function (patronId, hooplaId) {
			VuFind.confirm('Are you sure you want to return this title?', function () {
				VuFind.Account.ajaxLogin(function (){
					VuFind.showMessage("Returning Title", "Returning your title in Hoopla.");
					var url = Globals.path + "/Hoopla/" + hooplaId + "/AJAX",
							params = {
								'method': 'returnHooplaTitle'
								,patronId: patronId
							};
					$.getJSON(url, params, function (data) {
						VuFind.showMessage(data.success ? 'Success' : 'Error', data.message, data.success, data.success);
					}).fail(VuFind.ajaxFail);
				});
			});
			return false;
		}

	}
}(VuFind.Hoopla || {}));