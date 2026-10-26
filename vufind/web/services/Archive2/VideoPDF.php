<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace Archive2;

require_once ROOT_DIR . '/services/Archive2/ArchiveObject.php';

/**
 * Responsible for displaying a "Video + PDF" object from Islandora2: a compound
 * node with both a video file and a PDF attached directly to it (not as children).
 * Renders the video player above the PDF.js viewer.
 */
class VideoPDF extends ArchiveObject
{

    public function launch()
    {
        global $interface;
        global $library;

        // parent::launch() checks for a null mediaObject (e.g. missing/invalid id in the
        // URL) and shows the unavailable page before this method touches $this->mediaObject.
        parent::launch();

        // The video and the PDF can both be "Original File" media, so getOriginalMedia()
        // can't tell them apart; pick each one by its media bundle instead. Like
        // VideoObject::getVideo(), prefer the video's Service File, since the original
        // may be in a format browsers can't play.
        $serviceVideo  = null;
        $originalVideo = null;
        $pdf           = null;
        foreach ($this->mediaObject->getMedia() as $m) {
            if ($m->bundle === 'video') {
                if ($serviceVideo === null && $m->useIs('service file')) {
                    $serviceVideo = $m;
                } elseif ($originalVideo === null && $m->useIs('original file')) {
                    $originalVideo = $m;
                }
            } elseif ($pdf === null && $m->bundle === 'document' && $m->mime === 'application/pdf') {
                $pdf = $m;
            }
        }
        $video = $serviceVideo ?? $originalVideo;

        if ($video === null) {
            $this->logger->warning('Video media not found for video + pdf node.', ['nid' => $this->mediaObject->getNodeId()]);
            $interface->assign('videoUrl', null);
            $interface->assign('videoMime', null);
        } else {
            $interface->assign('videoUrl', $video->fileUrl);
            $interface->assign('videoMime', $video->mime);
        }

        $poster = $this->mediaObject->getThumbnail();
        $interface->assign('posterUrl', $poster->fileUrl ?? null);

        $captions = method_exists($this->mediaObject, 'getCaptions') ? $this->mediaObject->getCaptions() : [];
        $interface->assign('captions', json_decode(json_encode($captions), true));

        $transcripts = method_exists($this->mediaObject, 'getTranscripts') ? $this->mediaObject->getTranscripts() : [];
        $interface->assign('transcripts', $transcripts);

        if ($pdf === null) {
            $this->logger->error('PDF media not found for video + pdf node.', ['nid' => $this->mediaObject->getNodeId()]);
            $interface->assign('pdf_url', null);
            $interface->assign('iframe_src', null);
        } else {
            $interface->assign('pdf_url', $pdf->fileUrl);
            $libraryUrl = rtrim($library->catalogUrl, "/");
            $protocol = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $iframeSrc = "/js/pdfjs/web/viewer.html?file=" . urlencode($protocol . $libraryUrl . "/Archive2/AJAX?method=fetchPDFFile&pdf_file=" . $pdf->fileUrl . "&nid=" . $this->mediaObject->getNodeId());
            $interface->assign('iframe_src', $iframeSrc);
        }

        $interface->assign('viewer', 'video_pdf');

        $title = $this->mediaObject->getTitle();
        parent::display('wrapper.tpl', $title, 'Search/home-sidebar.tpl');
    }

}
