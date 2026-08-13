{{--
    The shopper's half of Promotions.

    Nothing merchant-facing is reachable from here. The domain publishes, for
    every offer that did not apply, a machine-readable reason — expired,
    exhausted, minimum not met, eligibility unresolvable — and this view renders
    none of them. One refusal message, whatever went wrong, because a
    per-reason answer tells a stranger which codes exist.

    Every amount comes from the domain's `Money`, and the per-line figures come
    from the allocation it published. Neither is re-derived from a float.
--}}
<div>
    <form wire:submit="apply">
        <label>
            <span>Promotion code</span>
            <input type="text" wire:model="code" autocomplete="off" maxlength="64">
        </label>

        <button type="submit">Apply</button>

        @error('code')
            <p role="alert">{{ $message }}</p>
        @enderror
    </form>

    @if ($appliedCodes !== [])
        <ul aria-label="Codes you have applied">
            @foreach ($appliedCodes as $applied)
                <li>
                    <span>{{ $applied }}</span>
                    <button type="button" wire:click="remove('{{ $applied }}')">Remove {{ $applied }}</button>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($offers !== [])
        <ul aria-label="Reductions applied to your basket">
            @foreach ($offers as $offer)
                <li>
                    <span>{{ $offer['name'] }}</span>
                    <span>&minus;{{ $offer['amount']->decimal() }} {{ $offer['amount']->currency }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($shipping !== null)
        <p>Shipping reduced by {{ $shipping->decimal() }} {{ $shipping->currency }}</p>
    @endif

    @if ($lines !== [])
        <ul aria-label="How the reduction is spread across your basket">
            @foreach ($lines as $line)
                <li>{{ $line['lineRef'] }}: &minus;{{ $line['amount']->decimal() }} {{ $line['amount']->currency }}</li>
            @endforeach
        </ul>
    @endif

    @if ($total !== null)
        <p>Total reduction {{ $total->decimal() }} {{ $total->currency }}</p>
    @endif
</div>
