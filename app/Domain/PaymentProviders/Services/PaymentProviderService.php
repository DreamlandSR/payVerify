<?php

namespace App\Domain\PaymentProviders\Services;

use App\Domain\PaymentProviders\Contracts\PaymentProviderInterface;
use App\Domain\PaymentProviders\Providers\MockQrisProvider;

class PaymentProviderService
{
    private PaymentProviderInterface $provider;

    public function __construct(?PaymentProviderInterface $provider = null)
    {
        $driver = config('services.payment_provider.driver', env('PAYMENT_PROVIDER_DRIVER', 'mock_qris'));

        $this->provider = $provider ?? match ($driver) {
            default => new MockQrisProvider,
        };
    }

    public function getProvider(): PaymentProviderInterface
    {
        return $this->provider;
    }
}
