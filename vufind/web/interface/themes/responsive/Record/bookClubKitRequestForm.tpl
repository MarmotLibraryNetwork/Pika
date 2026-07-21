{strip}
	<div class="alert alert-info">
		<p>
			Thank you for your interest in this book club set from the <a href="https://www.coloradovirtuallibrary.org/resource-sharing/co-book-clubs/cooler-climes-call-for-cozy-characters/">Colorado Book Club Resource</a>. Please note, this collection is owned and operated by the Colorado State Library. {if $librarySystemName}{$librarySystemName}{else}Your library{/if} has access to the collection and library staff will request the set on your behalf.
		</p>
		<h3 class="h4">Borrowing Guidelines:</h3>
		<ul>
			<li>Please allow up to <strong>two weeks</strong> for an <strong>available</strong> set to be delivered to your library. You will be notified when it’s ready to pick up. You will receive a due date when you pick up your set.</li>
			<li>The loan period for the sets is <strong>eight weeks</strong>, and renewals are available if no other holds are waiting.</li>
			<li>There may be a wait for a set to become available. Search the <a href="https://csl.catalog.aspencat.info/">standalone catalog</a> to see whether a set is checked out or currently available.</li>
			<li>The library cannot guarantee sets for a future date as all items are shared on a first-come-first-served basis.</li>
			<li>Find resources, policies, and FAQs on the <a href="https://www.coloradovirtuallibrary.org/uncategorized/key-information-for-users-of-kits-book-club-sets/">Colorado Virtual Library website</a>.</li>
		</ul>
	</div>
	<form id="bookClubKitRequestForm">
		<input type="hidden" name="submit" value="submitted">
		<input type="hidden" name="homeLibraryId" value="{$homeLibraryId}">
		<input type="hidden" name="recordId" value="{$recordId}">
		<div class="form-group">
			<label for="libraryCardNumber" class="control-label">Library Card Number:</label>
			<input type="text" name="libraryCardNumber" id="libraryCardNumber" class="form-control" maxlength="20" size="20" disabled="disabled" aria-disabled="true" value="{$libraryCardNumber}">
		</div>
		<div class="form-group">
			<label for="name" class="control-label">Name: <span class="required-input">*</span></label>
			<input type="text" name="name" id="name" class="required form-control" aria-required="true" maxlength="120" size="60" value="{$name}">
		</div>
		<div class="form-group">
			<label for="email" class="control-label">E-mail: <span class="required-input">*</span></label>
			<input type="text" name="email" id="email" class="required email form-control" aria-required="true" maxlength="120" size="60" value="{$email}">
		</div>
		<div class="form-group">
			<label for="title" class="control-label">Title:</label>
			<input type="text" name="title" id="title" class="form-control" maxlength="255" size="60" disabled="disabled" aria-disabled="true" value="{$title}">
		</div>
	</form>
{/strip}
{literal}
	<script>
		$(function(){
			$('#bookClubKitRequestForm').validate({
				submitHandler: function(){
					Pika.Record.submitBookClubKitRequestForm();
				}
			});
		});
	</script>
{/literal}
