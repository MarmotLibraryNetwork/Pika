<?php

require_once ROOT_DIR . '/AJAXHandler.php';

class Help_AJAX extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = array(
		'getSupportForm',
	);

	function getSupportForm(){
		global $interface;
		$user = UserAccount::getActiveUserObj();

		// Presets for the form to be filled out with
		$interface->assign('lightbox', true);
		if ($user){
			$name = $user->firstname . ' ' . $user->lastname;
			$interface->assign('name', $name);
			$interface->assign('email', $user->email);
		}

		$results = array(
			'title'        => 'eContent Support Request',
			'modalBody'    => $interface->fetch('Help/eContentSupport.tpl'),
			'modalButtons' => '<span class="tool btn btn-primary" onclick="VuFind.EContent.submitHelpForm();">Submit</span>',
		);
		return $results;
	}

}
