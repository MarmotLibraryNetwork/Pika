{foreach from=$staffCommentList item=comment}
	<div class='comment'>
		<div class="commentHeader">
			<div class='commentDate'>{$comment->created|date_format}
				{if $comment->user_id == $activeUserId}
				<button type="button" onclick='deleteComment({$id|escape:"url"}, {$comment->id}, {literal}{{/literal}save_error: "{translate text='comment_error_save'}", load_error: "{translate text='comment_error_load'}", save_title: "{translate text='Save Comment'}"{literal}}{/literal});' class="deleteComment" style="background:none;border:none;padding:0;margin:0;"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span>&nbsp;{translate text='Delete'}</button>
				{/if}
			</div>
			<div class="posted"><strong>{translate text='Posted by'} {if strlen($comment->displayName) > 0}{$comment->displayName}{else}{$comment->fullname}{/if}</strong></div>
		</div>
		{$comment->comment|escape:"html"}
	</div>
{/foreach}