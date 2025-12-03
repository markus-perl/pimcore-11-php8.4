<?php
declare(strict_types=1);

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

namespace Pimcore\Tool;

use InvalidArgumentException;
use Pimcore;
use Pimcore\Event\SystemEvents;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class MaintenanceModeHelper implements MaintenanceModeHelperInterface
{
    public function __construct(protected RequestStack $requestStack)
    {
    }

    public function activate(string $sessionId): void
    {
        if (empty($sessionId)) {
            $sessionId = $this->requestStack->getSession()->getId();
        }

        if (empty($sessionId)) {
            throw new InvalidArgumentException('Pass sessionId to activate the maintenance mode');
        }

        $this->addEntry($sessionId);

        Pimcore::getEventDispatcher()->dispatch(new GenericEvent(), SystemEvents::MAINTENANCE_MODE_ACTIVATE);
    }

    public function deactivate(): void
    {
        $this->removeEntry();

        Pimcore::getEventDispatcher()->dispatch(new GenericEvent(), SystemEvents::MAINTENANCE_MODE_DEACTIVATE);
    }

    public function isActive( ?string $matchSessionId = null): bool
    {
        if ($maintenanceModeEntry = $this->getEntry()) {
            if ($matchSessionId === null || $matchSessionId !== $maintenanceModeEntry) {
                return true;
            }
        }

        return false;
    }

    protected function addEntry(string $sessionId): void
    {
        file_put_contents($this->getFilePath(), $sessionId);
    }

    protected function getEntry(): ?string
    {
        $file = $this->getFilePath();
        if (file_exists($file)) {
            $content = file_get_contents($file);
            return $content !== false ? $content : null;
        }

        return null;
    }

    protected function removeEntry(): void
    {
        $file = $this->getFilePath();
        if (file_exists($file)) {
            unlink($file);
        }
    }

    private function getFilePath(): string
    {
        return PIMCORE_PRIVATE_VAR . '/maintenance_mode';
    }
}
