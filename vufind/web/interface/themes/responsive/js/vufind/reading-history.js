VuFind.Account.ReadingHistory = (function(){
	return {
		deletedMarkedAction: function (){
			VuFind.confirm('The marked items will be irreversibly deleted from your reading history.  Proceed?',function () {
				$('#readingHistoryAction').val('deleteMarked');
				$('#readingListForm').submit();
			});
			return false;
		},

		deleteAllAction: function (){
			VuFind.confirm('Your entire reading history will be irreversibly deleted.  Proceed?',function(){
				$('#readingHistoryAction').val('deleteAll');
				$('#readingListForm').submit();
			});
			return false;
		},

		optOutAction: function (){
			VuFind.confirm('Opting out of Reading History will also delete your entire reading history irreversibly.  Proceed?', function(){
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
		}
	};
}(VuFind.Account.ReadingHistory || {}));
