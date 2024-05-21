{strip}

	{* HTML and styling for skip block navigation taken from https://developer.mozilla.org *}

	<ul id="nav-access" class="a11y-nav">
		<li><a id="skip-to-main" href="#main">Skip to main content</a></li>
		<li><a id="skip-to-search" href="#lookfor">Skip to search</a></li>
		{if !empty($sidebar)}
			<li><a id="skip-to-side-bar" href="#side-bar">Skip to sidebar</a></li>
		{/if}
	</ul>

{/strip}