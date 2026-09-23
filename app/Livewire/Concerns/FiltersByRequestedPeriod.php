<?php

namespace App\Livewire\Concerns;

use App\Domain\Pedidos\RequestedPeriodFilter;
use App\Enums\RequestedPeriodPreset;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Builder;

/**
 * Livewire glue for the "Solicitado" period control of the pedido listings
 * (RF-16..RF-19, NC-01, CT-03).
 *
 * The using component declares, with `#[Url]`, `string $requestedPreset`,
 * `string $requestedFrom` and `string $requestedTo`. This trait only
 * normalizes that state and delegates to {@see RequestedPeriodFilter}, the
 * single class that turns a period into UTC bounds on `requested_at` (local
 * days in the application's local timezone, F-01); it never builds a bound.
 *
 * `#[Url]` values are hydrated before the component `mount()` runs
 * (`SupportAttributes` is registered before `SupportLifecycleHooks`), so
 * {@see self::normalizeRequestedPeriod()} reads only properties, never the
 * request.
 */
trait FiltersByRequestedPeriod
{
    /**
     * Rewrites the raw state to its effective form: unknown → neutral (RF-19);
     * relative preset → custom dates cleared, so they leave the URL (RF-17);
     * empty preset with dates → Personalizado (RF-18).
     */
    public function normalizeRequestedPeriod(): void
    {
        $effective = RequestedPeriodFilter::effectivePreset($this->requestedPreset, $this->requestedFrom, $this->requestedTo);

        $this->requestedPreset = $effective?->value ?? '';

        if ($effective === null || $effective->isRelative()) {
            $this->requestedFrom = '';
            $this->requestedTo = '';
        }
    }

    public function updatedRequestedPreset(): void
    {
        $this->normalizeRequestedPeriod();
    }

    /**
     * Whether the De/Até fields are rendered (RF-15).
     */
    public function showsCustomRequestedPeriod(): bool
    {
        return RequestedPeriodFilter::effectivePreset($this->requestedPreset, $this->requestedFrom, $this->requestedTo)
            === RequestedPeriodPreset::Personalizado;
    }

    public function requestedPeriodIsActive(): bool
    {
        return RequestedPeriodFilter::effectivePreset($this->requestedPreset, $this->requestedFrom, $this->requestedTo) !== null;
    }

    /**
     * @param  Builder<Pedido>  $query
     */
    public function applyRequestedPeriod(Builder $query): void
    {
        RequestedPeriodFilter::apply($query, $this->requestedPreset, $this->requestedFrom, $this->requestedTo);
    }
}
