{literal}
<script>
	$(function() {
		Pika.Archive.loadExploreMore('{/literal}{$pid}{literal}');
		if (document.getElementById("audio-player")) {
			let audio = document.getElementById("audio-player");
			audio.addEventListener('play', function(ev) {
				$.idleTimer('destroy');
			});
			audio.addEventListener('pause', function(ev) {
				var timeout;
				if (Globals.loggedIn) {
					timeout = Globals.automaticTimeoutLength * 1000;
				} else {
					timeout = Globals.automaticTimeoutLengthLoggedOut * 1000;
				}
				if (timeout > 0) {
					$.idleTimer(timeout); // start the Timer
				}
			});
			$(document).on("idle.idleTimer", function() {
				$.idleTimer(
				'destroy'); // turn off Timer, so that when it is re-started in will work properly
				if (Globals.loggedIn) {
					showLogoutMessage();
				} else {
					showRedirectToHomeMessage();
				}
			});
		}
		let video = document.getElementById("video-player");
		video.addEventListener('play', function(ev) {
			$.idleTimer('destroy');
		});
		video.addEventListener('pause', function(ev) {
			var timeout;
			if (Globals.loggedIn) {
				timeout = Globals.automaticTimeoutLength * 1000;
			} else {
				timeout = Globals.automaticTimeoutLengthLoggedOut * 1000;
			}
			if (timeout > 0) {
				$.idleTimer(timeout); // start the Timer
			}

			$(document).on("idle.idleTimer", function() {
				$.idleTimer(
				'destroy'); // turn off Timer, so that when it is re-started in will work properly
				if (Globals.loggedIn) {
					showLogoutMessage();
				} else {
					showRedirectToHomeMessage();
				}
			});
		});
	});
</script>
{/literal}