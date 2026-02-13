@props(['currentStep' => 1, 'totalSteps' => 2])

<div class="flex items-center gap-2">
    @for ($step = 1; $step <= $totalSteps; $step++)
        <div class="flex items-center">
            <div @class([
                'flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium',
                'bg-blue-600 text-white' => $step === $currentStep,
                'bg-emerald-600 text-white' => $step < $currentStep,
                'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' => $step > $currentStep,
            ])>
                {{ $step }}
            </div>

            @if ($step < $totalSteps)
                <div @class([
                    'mx-2 h-0.5 w-8',
                    'bg-emerald-600' => $step < $currentStep,
                    'bg-zinc-200 dark:bg-zinc-700' => $step >= $currentStep,
                ])></div>
            @endif
        </div>
    @endfor
</div>

<div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
    <strong>{{ __('Step :current of :total', ['current' => $currentStep, 'total' => $totalSteps]) }}:</strong>
    {{ $currentStep === 1 ? __('Setup') : __('Build') }}
</div>
