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

/**
 * RecommendationFactory Class
 *
 * This is a factory class to build recommendation modules for use in searches.
 *
 * @author      Demian Katz <demian.katz@villanova.edu>
 * @access      public
 */
class RecommendationFactory {
    /**
     * initRecommendation
     *
     * This constructs a recommendation module object.
     *
     * @access  public
     * @param   string  $module     The name of the recommendation module to build
     * @param   object  $searchObj  The SearchObject using the recommendations.
     * @param   string  $params     Configuration string to send to the constructor
     * @return  mixed               The $module object on success, false otherwise
     */
    static function initRecommendation($module, $searchObj, $params)
    {
        global $configArray;
        $path = "{$configArray['Site']['local']}/sys/Recommend/{$module}.php";
        if (is_readable($path)) {
            require_once $path;
            if (class_exists($module)) {
                $recommend = new $module($searchObj, $params);
                return $recommend;
            }
        }
        
        return false;
    }
}
?>
