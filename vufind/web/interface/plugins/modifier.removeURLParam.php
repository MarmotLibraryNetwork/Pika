<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
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

/*
 * Smarty plugin
 * -------------------------------------------------------------
 * Type:     modifier
 * Name:     removeURLParam
 * Purpose:  Remove a parameter from a URL with GET parameters.
 * -------------------------------------------------------------
 */
function smarty_modifier_removeURLParam($url, $param_to_remove) {
    // Break the base URL from the parameters:
    list($base, $params) = explode('?', $url);
    
    // Loop through the parameters and filter out the unwanted one:
    $params = explode('&', $params);
    $filtered_params = array();
    foreach ($params as $current_param) {
        list($name, $value) = explode('=', $current_param);
        if ($name != $param_to_remove) {
            $filtered_params[] = $current_param;
        }
    }

    // Reassemble the URL minus the unwanted parameter:
    return $base . '?' . implode('&', $filtered_params);
}
?>
