<?php

declare(strict_types=1);

namespace App\Services\Sms\Drivers;

use App\Contracts\SmsSender;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Sends messages through the Faraz SMS (IPPanel Edge) API using predefined patterns.
 *
 * @see https://ippanelcom.github.io/Edge-Document/docs/send/pattern
 */
final readonly class FarazSmsDriver implements SmsSender
{
    /**
     * @param  array{base_url: string, api_key: ?string, from_number: ?string, patterns: array{otp: ?string}, connect_timeout: int, timeout: int}  $config
     */
    public function __construct(private array $config) {}

    public function sendOtp(string $phoneNumber, string $code): void
    {
        $this->sendPattern((string) $this->config['patterns']['otp'], $phoneNumber, ['code' => $code]);
    }

    /**
     * @param  array<string, string>  $params
     */
    private function sendPattern(string $patternCode, string $phoneNumber, array $params): void
    {
        $this->client()
            ->post('/api/send', [
                'sending_type' => 'pattern',
                'from_number' => $this->config['from_number'],
                'code' => $patternCode,
                'recipients' => [PhoneNumber::toInternational($phoneNumber)],
                'params' => $params,
            ])
            ->throw();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->withHeaders(['Authorization' => (string) $this->config['api_key']])
            ->acceptJson()
            ->connectTimeout($this->config['connect_timeout'])
            ->timeout($this->config['timeout']);
    }
}
