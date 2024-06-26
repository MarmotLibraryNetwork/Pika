<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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

/**
 * Open Library Utilities
 *
 * Class for accessing helpful Open Library APIs.
 *
 * @category Pika
 * @package  Support_Classes
 * @author   Eoghan � Carrag�in <eoghan.ocarragain@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
// Very likely obsolete.  Keeping as reference for any potential future use. Pascal 12/30/2019
//class OpenLibraryUtils
//{
//    /**
//     * Returns an array of elements for each work matching the
//     *    parameters. An API call will be made for each subjectType until
//     *    data is returned
//     *
//     * @param string $subject        The subject term to be looked for
//     * @param string $publishedIn    Date range in the form YYYY-YYYY
//     * @param array  $subjectTypes   An array of subject types to check
//     * @param bool   $ebooks         Whether to use ebook filter
//     * @param bool   $details        Whether to return full details
//     * @param int    $limit          The number of works to return
//     * @param int    $offset         Paging offset
//     * @param bool   $publicFullText Only return publically available, full-text
//     * works
//     *
//     * @return array
//     * @access public
//     */
//    public function getSubjects(
//        $subject, $publishedIn, $subjectTypes, $ebooks = true, $details = false,
//        $limit = 5, $offset = null, $publicFullText = true
//    ) {
//        // empty array to hold the result
//        $result = array();
//
//        // normalise subject term
//        $subject = $this->_normaliseSubjectString($subject);
//        if ($ebooks) {
//            $ebooks = "true";
//        }
//        if ($details) {
//            $details = "true";
//        }
//
//        for ($i = 0; $i < count($subjectTypes); $i++) {
//            if (empty($result)) {
//                $subjectType = "";
//                $subjectType = $subjectTypes[$i] == "topic" ? "" :
//                    $subjectTypes[$i] . ":";
//
//                // build url
//                // ebooks paramater does not work at present, so limit has been set
//                // to 50 to increase likelihood of full-text, public scans being
//                // returned. see https://bugs.launchpad.net/openlibrary/+bug/709772
//                $url= "http://openlibrary.org/subjects/" . $subjectType . $subject .
//                    ".json?ebooks=" . $ebooks . "&details=" . $details .
//                    "&offset=" . $offset . "&limit=50&published_in=" . $publishedIn;
//
//                // make API call
//                $result = $this->_processSubjectsApi($url, $limit, $publicFullText);
//            }
//        }
//        return $result;
//    }
//
//     /**
//     * Return the following array of values for each work:
//     * title, cover_id, cover_id_type, key, ia, mainAuthor
//     *
//     * @param string $url            URL to request
//     * @param int    $limit          The number of works to return
//     * @param bool   $publicFullText Only return publically available, full-text
//     * works
//     *
//     * @return array
//     * @access private
//     */
//    private function _processSubjectsApi($url, $limit, $publicFullText)
//    {
//        // empty array to hold the result
//        $result = array();
//
//        //find out if there are any reviews
//        $client = new Proxy_Request();
//        $client->setMethod('GET');
//        $client->setURL($url);
//        $response = $client->sendRequest();
//        // Was the request successful?
//        if (!PEAR_Singleton::isError($response)) {
//            // grab the response:
//            $json = $client->getResponseBody();
//            // parse json
//            $data = json_decode($json, true);
//            if ($data) {
//                $i = 1;
//                foreach ($data['works'] as $work) {
//                    if ($i <= $limit) {
//                        if ($publicFullText && (!$work['public_scan']
//                            || !$work['has_fulltext'])
//                        ) {
//                            continue;
//                        }
//                        $result[$i]['title'] = $work['title'];
//                        if (isset($work['cover_id'])) {
//                            $result[$i]['cover_id_type'] = 'ID';
//                            $result[$i]['cover_id'] = $work['cover_id'];
//                        } elseif (isset($work['cover_edition_key'])) {
//                            $result[$i]['cover_id_type'] = 'OLID';
//                            $result[$i]['cover_id'] = $work['cover_edition_key'];
//                        }
//                        $result[$i]['key'] = $work['key'];
//                        $result[$i]['ia'] = $work['ia'];
//                        $result[$i]['mainAuthor'] = $work['authors'][0]['name'];
//                        $i++;
//                    }
//                }
//            }
//        }
//        return $result;
//    }
//
//     /**
//     * Support function to return a normalised version of the search string
//     *     for use in the API url
//     *
//     * @param string $subject Search string to normalise
//     *
//     * @return string
//     * @access private
//     */
//    private function _normaliseSubjectString($subject)
//    {
//        //normalise search term
//        $subject = str_replace('"', "", $subject);
//        $subject = str_replace(",", "", $subject);
//        $subject = trim(strtolower($subject));
//        $subject = preg_replace("/\s+/", "_", $subject);
//        return $subject;
//    }
//}
