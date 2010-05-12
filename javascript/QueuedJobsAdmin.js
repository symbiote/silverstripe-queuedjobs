Behaviour.register({
	'#Form_EditForm' : {
		getPageFromServer : function(id) {
			statusMessage("loading...");
			var requestURL = 'admin/_queued-jobs/showqueue/' + id;
			this.loadURLFromServer(requestURL);
			$('sitetree').setCurrentByIdx(id);
		}
	},
});