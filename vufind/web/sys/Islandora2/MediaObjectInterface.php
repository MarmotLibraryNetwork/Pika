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

namespace Islandora2;

/**
 * Contract for Islandora 2 media objects exposed through the factory.
 */
interface MediaObjectInterface
{
    /**
     * Return the Islandora node id.
     *
     *
     * @return int
     */
    public function getNodeId(): ?int;

    /**
     * Return the Islandora node payload backing the object.
     *
     * The data should be the unmodified response from the Islandora JSON endpoint
     * so consumers can still access fields that do not yet have dedicated helpers.
     *
     * @return array
     */
    public function getRawNode(): array;

    /**
     * Normalised identifier for the object media type (e.g. image, video, pdf).
     *
     * @return string
     */
    public function getObjectModel(): string;

    /**
     * Return a human readable label for the media type.
     *
     * @return string
     */
    public function getObjectModelLabel(): string;

    /**
     * Return media associated with media object
     * 
     * @return array|null
     */
    public function getMedia(): ?array;

    /**
     * Convenience accessor for the primary derivative or file.
     *
     * @return array|null
     */
    public function getOriginalMedia(): ?array;

    /**
     * Optional hook for classes to report whether they are able to represent
     * the provided node. The factory uses this to select the most appropriate
     * media object implementation.
     *
     * @param array $node
     * @return bool
     */
    public static function supports(array $node): bool;

}
