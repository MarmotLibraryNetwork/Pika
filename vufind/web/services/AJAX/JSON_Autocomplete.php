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

require_once ROOT_DIR . '/sys/Autocomplete/AutocompleteFactory.php';
require_once ROOT_DIR .'/service/AJAX/JSON.php';

/**
 * AJAX action for the Autocomplete module.
 *
 * @category Pika
 * @package  Controller_AJAX
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
class JSON_Autocomplete extends JSON
{
    // define some status constants
    // ( used by JSON_Autocomplete )
    const STATUS_OK        = 'OK';           // good
    const STATUS_ERROR     = 'ERROR';        // bad
    const STATUS_NEED_AUTH = 'NEED_AUTH';    // must login first

    /**
     * Process search query and display suggestion as a JSON object.
     *
     * @return void
     * @access public
     */
    public function getSuggestions()
    {
        $this->output(
            array_values(AutocompleteFactory::getSuggestions()), JSON::STATUS_OK
        );
    }

    /**
     * Send output data and exit.
     *
     * @param mixed  $data   The response data
     * @param string $status Status of the request
     *
     * @return void
     * @access public
     */
    protected function output($data, $status) {
        header('Content-type: application/javascript');
        header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        $output = array('data'=>$data,'status'=>$status);
        echo json_encode($output);
        exit;
    }
}
?>
