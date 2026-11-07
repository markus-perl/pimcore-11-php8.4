<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Model\Tool\TmpStore;

use __PHP_Incomplete_Class;
use Exception;
use Pimcore\Db\Helper;
use Pimcore\Model;
use Pimcore\Tool\Serialize;

/**
 * @internal
 *
 * @property \Pimcore\Model\Tool\TmpStore $model
 */
class Dao extends Model\Dao\AbstractDao
{
    public function add(string $id, mixed $data, ?string $tag = null, ?int $lifetime = null): bool
    {
        try {
            $serialized = false;
            if (is_object($data) || is_array($data)) {
                $serialized = true;
                $data = serialize($data);
            }

            Helper::upsert($this->db, 'tmp_store', [
                'id' => $id,
                'data' => $data,
                'tag' => $tag,
                'date' => time(),
                'expiryDate' => (time() + $lifetime),
                'serialized' => (int) $serialized,
            ], $this->getPrimaryKey('tmp_store'));

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function delete(string $id): void
    {
        $this->db->delete('tmp_store', ['id' => $id]);
    }

    public function getById(string $id): bool
    {
        $item = $this->db->fetchAssociative('SELECT * FROM tmp_store WHERE id = ?', [$id]);

        if ($item) {
            if ($item['serialized']) {
                // CVE-2026-45162 (backport of upstream 2026.x): only the value objects that Pimcore
                // itself stores in tmp_store (deferred image thumbnails, video thumbnail jobs) may be
                // instantiated; anything else becomes __PHP_Incomplete_Class and the item is refused.
                $data = Serialize::unserialize((string) $item['data'], self::ALLOWED_CLASSES);
                if (($data === false && $item['data'] !== serialize(false)) || self::containsIncomplete($data)) {
                    return false;
                }
                $item['data'] = $data;
            }

            $item['serialized'] = (bool)$item['serialized'];
            $this->assignVariablesToModel($item);

            return true;
        }

        return false;
    }

    public function getIdsByTag(string $tag): array
    {
        $items = $this->db->fetchFirstColumn('SELECT id FROM tmp_store WHERE tag = ?', [$tag]);

        return $items;
    }

    /**
     * Classes Pimcore core stores in tmp_store (list as in upstream 2026.x).
     */
    private const ALLOWED_CLASSES = [
        Model\Asset\Image\Thumbnail\Config::class,
        Model\Asset\Video\Thumbnail\Processor::class,
        Model\Asset\Video\Thumbnail\Config::class,
        \Pimcore\Video\Adapter\Ffmpeg::class,
    ];

    /**
     * @param array<int, true> $seen object ids already visited (cycles)
     */
    private static function containsIncomplete(mixed $value, array &$seen = []): bool
    {
        if ($value instanceof __PHP_Incomplete_Class) {
            return true;
        }

        if (is_object($value)) {
            $id = spl_object_id($value);
            if (isset($seen[$id])) {
                return false;
            }
            $seen[$id] = true;
            $value = (array) $value;
        }

        if (is_array($value)) {
            foreach ($value as $v) {
                if ((is_array($v) || is_object($v)) && self::containsIncomplete($v, $seen)) {
                    return true;
                }
            }
        }

        return false;
    }
}
