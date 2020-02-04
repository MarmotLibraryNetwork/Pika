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
 * Autoloader callback function (needed for YAML)
 *
 * @param string $class Name of class to load.
 *
 * @return void
 */
function vuFindAutoloader($class)
{
    $filename = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    // treat as relative path to include path, root or sys folder
    $paths = explode(PATH_SEPARATOR, get_include_path());
    $paths[] = dirname(__FILE__);
    $paths[] = dirname(__FILE__).DIRECTORY_SEPARATOR.'sys';
    // go through each and look for file
    foreach ($paths as $path) {
        // check if we need to add the directory separator to the end
        if (substr($path, -1) == DIRECTORY_SEPARATOR) {
            $fullpath = $path.$filename;
        } else {
            $fullpath = $path.DIRECTORY_SEPARATOR.$filename;
        }
        // check if the file exists, load it and break out of the loop
        if (file_exists($fullpath)) {
            include_once $fullpath;
            break;
        }
    }
}

?>
