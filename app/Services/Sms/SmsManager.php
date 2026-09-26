<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Services\Sms\Drivers\ArraySmsDriver;
use App\Services\Sms\Drivers\FarazSmsDriver;
use App\Services\Sms\Drivers\LogSmsDriver;
use Illuminate\Log\LogManager;
use Illuminate\Support\Manager;

/**
 * Resolves the configured SMS driver. Add a provider by adding a create{Name}Driver method.
 *
 * @method SmsSender driver(string|null $driver = null)
 */
final class SmsManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('sms.default');
    }

    protected function createLogDriver(): SmsSender
    {
        $channel = $this->config->get('sms.drivers.log.channel');

        return new LogSmsDriver($this->container->make(LogManager::class)->channel($channel));
    }

    protected function createArrayDriver(): SmsSender
    {
        return new ArraySmsDriver;
    }

    protected function createFarazDriver(): SmsSender
    {
        return new FarazSmsDriver($this->config->get('sms.drivers.faraz'));
    }
}
