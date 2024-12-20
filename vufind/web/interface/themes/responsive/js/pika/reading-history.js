Pika.Account.ReadingHistory = (function(){
	return {
		deletedMarkedAction: function (){
			Pika.confirm('<p class="alert alert-warning">The marked items will be irreversibly deleted from your reading history.  Proceed?</p>',function () {
				$('#readingHistoryAction').val('deleteMarked');
				$('#readingListForm').submit();
			});
			return false;
		},

		deleteAllAction: function (){
			Pika.confirm('<p class="alert alert-danger">Your entire reading history will be irreversibly deleted.  Proceed?</p>',function(){
				$('#readingHistoryAction').val('deleteAll');
				$('#readingListForm').submit();
			});
			return false;
		},

		optOutAction: function (){
			Pika.confirm('<p class="alert alert-danger">Opting out of Reading History will also <strong>delete your entire reading history</strong> irreversibly.  Proceed?</p>', function(){
				$('#readingHistoryAction').val('optOut');
				$('#readingListForm').submit();
			});
			return false;
		},

		optInAction: function (){
			$('#readingHistoryAction').val('optIn');
			$('#readingListForm').submit();
			return false;
		},

		exportListAction: function (){
			$('#readingHistoryAction').val('exportToExcel');
			$('#readingListForm').submit();
			return false;
		},

		searchReadingHistoryAction: function() { 
			$('#readingHistoryAction').val('searchReadingHistory');
			$('#readingListForm').submit();
			return false;
		}
	};
}(Pika.Account.ReadingHistory || {}));
