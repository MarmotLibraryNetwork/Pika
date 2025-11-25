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

use Pika\Logger;

require_once ROOT_DIR . '/sys/Islandora2/MediaObjectInterface.php';
require_once ROOT_DIR . '/sys/Islandora2/I2Object.php';
require_once ROOT_DIR . '/sys/Islandora2/ImageObject.php';
require_once ROOT_DIR . '/sys/Islandora2/VideoObject.php';
require_once ROOT_DIR . '/sys/Islandora2/PdfObject.php';
require_once ROOT_DIR . '/sys/Islandora2/DocumentObject.php';
require_once ROOT_DIR . '/sys/Islandora2/DefaultMediaObject.php';
require_once ROOT_DIR . '/sys/Islandora2/Request.php';

/**
 * Factory for creating Islandora 2 media objects.
 *
 * The factory inspects node payloads and returns the most suitable
 * MediaObjectInterface implementation. It keeps a static registry so that
 * custom projects can register their own media types.
 *
 * Usage:
 *   $factory = new I2ObjectFactory();
 *   $mediaObject = $factory->fromNodeId(123);
 *
 * @todo Allow injecting a Request implementation so tests can provide fixtures.
 */
class I2ObjectFactory
{
    /**
     * @var array<string, class-string<MediaObjectInterface>>
     */
    private static array $registry = [];

    private static bool $bootstrapped = false;

    private Logger $logger;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger ?? new Logger(__CLASS__);
        self::bootstrap();
    }

    /**
     * Register the built-in media types on first use.
     */
    private static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        self::$bootstrapped = true;

        // Built-in registrations; class names added in step three.
        self::registerType('image', ImageObject::class);
        self::registerType('audio', ImageObject::class);
        self::registerType('video', VideoObject::class);
        self::registerType('pdf', PdfObject::class);
        self::registerType('document', DocumentObject::class);
        self::registerType('default', DefaultMediaObject::class);
    }

    /**
     * Factory entry point when you only have a node id.
     *
     * @param int $nodeId
     * @return MediaObjectInterface|null
     */
    public function fromNodeId(int $nodeId): ?MediaObjectInterface
    {
        if ($nodeId <= 0) {
            $this->logger->warning('Attempted to build Islandora object with invalid node id.', ['nodeId' => $nodeId]);
            return null;
        }

        $request = new Request($nodeId);
        $node = $request->fetch();

        if ($node === null) {
            $this->logger->warning('Failed to fetch Islandora 2 node for factory.', ['nodeId' => $nodeId]);
            return null;
        }

        return $this->fromNode($node);
    }

    /**
     * Factory entry point when the node data is already available.
     *
     * @param array $node
     * @return MediaObjectInterface|null
     */
    public function fromNode(array $node): ?MediaObjectInterface
    {
        $class = $this->resolveClass($node);
        if ($class === null) {
            $this->logger->notice('No media handler resolved for Islandora 2 node; using default handler.', [
                'nodeId' => $node['id'] ?? null,
            ]);
            $class = self::$registry['default'] ?? null;
        }

        if ($class === null) {
            $this->logger->error('Islandora 2 factory has no default handler registered.');
            return null;
        }

        return new $class($node);
    }

    /**
     * Register a class in the factory.
     *
     * @param string $key
     * @param class-string<MediaObjectInterface> $class
     */
    public static function registerType(string $key, string $class): void
    {
        if (!is_subclass_of($class, MediaObjectInterface::class)) {
            throw new \InvalidArgumentException(sprintf(
                'Class %s must implement %s to be registered as an Islandora 2 media type.',
                $class,
                MediaObjectInterface::class
            ));
        }

        self::$registry[$key] = $class;
    }

    public static function unregisterType(string $key): void
    {
        unset(self::$registry[$key]);
    }

    /**
     * @param array $node
     * @return class-string<MediaObjectInterface>|null
     */
    private function resolveClass(array $node): ?string
    {
        foreach (self::$registry as $key => $class) {
            if ($key === 'default') {
                continue;
            }
            if ($class::supports($node)) {
                return $class;
            }
        }

        return null;
    }
}
