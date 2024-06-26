Pika.Hoopla = (function(){
	return {
		checkOutHooplaTitle: function (hooplaId, patronId) {
			Pika.Account.ajaxLogin(function (){
				if (typeof patronId === 'undefined') {
					patronId = $('#patronId', '#pickupLocationOptions').val(); // Lookup selected user from the options form
				}
				var url = '/Hoopla/'+ hooplaId + '/AJAX',
						params = {
							'method' : 'checkOutHooplaTitle',
							patronId : patronId
						};
				if ($('#stopHooplaConfirmation').prop('checked')){
					params['stopHooplaConfirmation'] = true;
				}
				$.getJSON(url, params, function (data) {
					if (data.success) {
						Pika.showMessageWithButtons(data.title, data.message, data.buttons);
					} else {
						Pika.showMessage("Checking Out Title", data.message);
					}
				}).fail(Pika.ajaxFail)
			});
			return false;
		},

		getHooplaCheckOutPrompt: function (hooplaId) {
			Pika.Account.ajaxLogin(function (){
				var url = "/Hoopla/" + hooplaId + "/AJAX?method=getHooplaCheckOutPrompt";
				$.getJSON(url, function (data) {
					Pika.showMessageWithButtons(data.title, data.body, data.buttons);
				}).fail(Pika.ajaxFail);
			});
			return false;
		},

		returnHooplaTitle: function (patronId, hooplaId) {
			Pika.confirm('Are you sure you want to return this title?', function () {
				Pika.Account.ajaxLogin(function (){
					Pika.showMessage("Returning Title", "Returning your title in Hoopla.");
					var url = "/Hoopla/" + hooplaId + "/AJAX",
							params = {
								'method': 'returnHooplaTitle'
								,patronId: patronId
							};
					$.getJSON(url, params, function (data) {
						Pika.showMessage(data.success ? 'Success' : 'Error', data.message, data.success, data.success);
					}).fail(Pika.ajaxFail);
				});
			});
			return false;
		}

	}
}(Pika.Hoopla || {}));