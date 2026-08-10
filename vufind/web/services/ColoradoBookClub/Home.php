<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * Extends the Record Home class so that ColoradoBookClub record pages (e.g. "More Info" links)
 * continue to display normally now that this module has its own services directory.
 *
 * (This class has to exist because the parent directory causes the action loading to expect it)
 */

require_once ROOT_DIR . '/services/Record/Home.php';

class ColoradoBookClub_Home extends Record_Home {

}
