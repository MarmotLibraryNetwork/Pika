{strip}
	<div class="row">
		<div id="pika-roles-label" class="col-tn-12 lead">Roles</div>
	</div>
	<div class="row">
		<div class="col-tn-12">
			<ul aria-labelledby="pika-roles-label">
				{foreach from=$profile->roles item=role}
					<li>
						{if $role == "archives"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#Archives-Role" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "cataloging"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#Cataloging" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "circulationReports"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#Circulation-Reports" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "contentEditor"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#Content-Editor" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "genealogyContributor"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#Genealogy-Contributor" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "libraryAdmin"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#Library-Admin" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "libraryManager"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#Library-Manager" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "library_material_requests"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#Library-Material-Requests" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "listPublisher"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#List-Publisher" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "locationManager"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#Location-Manager" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "locationReports"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#Location-Reports" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "opacAdmin"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#OPAC-Admin" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{elseif $role == "userAdmin"}
							<a href ="https://marmot-support.atlassian.net/wiki/spaces/MKB/pages/928088073/Detailed+Pika+Administrator+Roles#User-Admin" title="Pika {$role} documentation" target="_blank">{$role}</a>
						{else}
							{$role}
						{/if}
					</li>
				{/foreach}
			</ul>
		</div>
		<div class="col-tn-12">
			<div class="alert alert-info">
				For more information about what each role can do, see the <a target="_blank" href="https://marmot-support.atlassian.net/l/c/zJP1kcDf">online documentation</a>.
			</div>
		</div>
	</div>

	<form action="" method="post" class="form-horizontal" id="staffSettingsForm">
		<input type="hidden" name="updateScope" value="staffSettings">

		{if $userIsStaff}
			<div class="row">
				<div class="col-tn-12 lead">Staff Auto Logout Bypass</div>
			</div>
			<div class="form-group row">
				<div class="col-xs-4"><label for="bypassAutoLogout" class="control-label">{translate text='Bypass Automatic Logout'}:</label></div>
				<div class="col-xs-8">
					{if !$offline}
						<input type="checkbox" name="bypassAutoLogout" id="bypassAutoLogout" {if $profile->bypassAutoLogout==1}checked='checked'{/if} data-switch="">
					{else}
						{if $profile->bypassAutoLogout==0}No{else}Yes{/if}
					{/if}
				</div>
			</div>
		{/if}

		{if $profile->hasRole('library_material_requests')}
			<div class="row">
				<div class="lead col-tn-12">Materials Request Management</div>
			</div>
			<div class="form-group row">
				<div class="col-xs-4">
					<label for="materialsRequestReplyToAddress" class="control-label">Reply-To Email Address:</label>
				</div>
				<div class="col-xs-8">
					{if !$offline}
						<input type="text" id="materialsRequestReplyToAddress" name="materialsRequestReplyToAddress" class="form-control multiemail" value="{$user->materialsRequestReplyToAddress}">
					{else}
						{$user->materialsRequestReplyToAddress}
					{/if}
				</div>
			</div>
			<div class="form-group row">
				<div class="col-xs-4">
					<label for="materialsRequestEmailSignature" class="control-label">Email Signature:</label>
				</div>
				<div class="col-xs-8">
					{if !$offline}
						<textarea id="materialsRequestEmailSignature" name="materialsRequestEmailSignature" class="form-control">{$user->materialsRequestEmailSignature}</textarea>
					{else}
						{$user->materialsRequestEmailSignature}
					{/if}
				</div>
			</div>
		{/if}


		{if !$offline}
			<div class="form-group">
				<div class="col-xs-8 col-xs-offset-4">
					<input type="submit" value="Update My Staff Settings" name="updateStaffSettings" class="btn btn-sm btn-primary">
				</div>
			</div>
		{/if}
	</form>
{/strip}