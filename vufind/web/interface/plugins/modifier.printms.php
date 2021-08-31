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
 * Name:     printms
 * Purpose:  Prints a human readable format from a number of milliseconds
 * -------------------------------------------------------------
 */
function smarty_modifier_printms($ms) {
    $seconds = floor($ms/1000);
    $ms = ($ms % 1000);

    $minutes = floor($seconds/60);
    $seconds = ($seconds % 60);

    $hours = floor($minutes/60);
    $minutes = ($minutes % 60);

    if ($hours) {
        $days = floor($hours/24);
        $hours = ($hours % 24);
        
        if ($days) {
            $years = floor($days/365);
            $days = ($days % 365);
            
            if ($years) {
                return sprintf("%d years, %d days, %d hours, %d minutes, %d seconds",
                               $years, $days, $hours, $minutes, $seconds);
            } else {
                return sprintf("%d days, %d hours, %d minutes, %d seconds",
                               $days, $hours, $minutes, $seconds);
            }
        } else {
            return sprintf("%d hours, %d minutes, %d seconds",
                           $hours, $minutes, $seconds);
        }
    } else {
        return sprintf("%d minutes, %d seconds",
                       $minutes, $seconds);
    }
}
?>
