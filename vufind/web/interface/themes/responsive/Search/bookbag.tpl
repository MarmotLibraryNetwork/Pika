{strip}
<div class="bookbag-container" id="bookbag-container" style="display:none;">
	<div class="bookbag"></div>
	<div class="cart-container cartIn">
		<h3>Bookbag</h3>
		<div class="cart">
			<ul class="list-striped list-unstyled" id="cartList"></ul>

		</div>
		<button class="btn btn-sm btn-info" onclick="Pika.GroupedWork.addSelectedToList()" id="bookbagHoldBtn">Add To List</button>
	</div>
</div>

	<script>
		window.onscroll = function () {ldelim}
			Pika.ResultsList.staticPosition(413,"bookbag-container");
		{rdelim};
	</script>

{/strip}