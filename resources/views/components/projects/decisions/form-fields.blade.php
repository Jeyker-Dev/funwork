@props([
    'statuses',
    'supersedingOptions',
    'currentStatus' => '',
])

<div class="space-y-4">
    <flux:field>
        <flux:label>{{ __('Title') }}</flux:label>
        <flux:input wire:model="form.title" placeholder="e.g. Use PostgreSQL for primary storage" />
        <flux:error name="form.title" />
    </flux:field>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <flux:field>
            <flux:label>{{ __('Status') }}</flux:label>
            <flux:select wire:model.live="form.status">
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.status" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Decided Date') }}</flux:label>
            <flux:input type="date" wire:model="form.decidedAt" />
            <flux:error name="form.decidedAt" />
        </flux:field>
    </div>

    @if ($currentStatus === \App\Enums\DecisionStatus::Superseded->value)
        <flux:field>
            <flux:label>{{ __('Superseded By') }}</flux:label>
            <flux:select wire:model="form.supersededById" placeholder="{{ __('Select superseding decision...') }}">
                <flux:select.option value="">{{ __('Select superseding decision...') }}</flux:select.option>
                @foreach ($supersedingOptions as $option)
                    <flux:select.option :value="$option->id">{{ $option->code }}: {{ $option->title }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="form.supersededById" />
        </flux:field>
    @endif

    <flux:field>
        <flux:label>{{ __('Context') }}</flux:label>
        <flux:textarea wire:model="form.context" rows="4" placeholder="What is the issue motivating this decision? (Markdown supported)" />
        <flux:error name="form.context" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Decision') }}</flux:label>
        <flux:textarea wire:model="form.decision" rows="4" placeholder="What change are we proposing or have agreed to? (Markdown supported)" />
        <flux:error name="form.decision" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Consequences') }}</flux:label>
        <flux:textarea wire:model="form.consequences" rows="4" placeholder="What becomes easier or harder because of this change? (Markdown supported)" />
        <flux:error name="form.consequences" />
    </flux:field>
</div>
