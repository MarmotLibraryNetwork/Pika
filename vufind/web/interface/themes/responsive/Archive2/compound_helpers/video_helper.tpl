{* Helper template that extracts data from mediaObject and includes the video viewer *}
{php}
    global $interface;

    // Get the mediaObject from the template variable
    $childMediaObject = $this->get_template_vars('childMediaObject');

    // Extract video data
    $video = $childMediaObject->getVideo();
    $interface->assign('videoUrl', $video->fileUrl);
    $interface->assign('videoMime', $video->mime);

    // Get poster
    $poster = $childMediaObject->getVideoPoster();
    $interface->assign('posterUrl', $poster->fileUrl);

    // Get captions
    $captions = $childMediaObject->getCaptions();
    $captionsArray = json_decode(json_encode($captions), true);
    $interface->assign('captions', $captionsArray);

    // Get transcripts
    $transcripts = $childMediaObject->getTranscripts();
    $interface->assign('transcripts', $transcripts);
{/php}

{* Now include the actual viewer template *}
{include file="Archive2/video.tpl"}
