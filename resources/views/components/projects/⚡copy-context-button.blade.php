<?php

use App\Actions\Projects\BuildProjectContextAction;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
    }

    public function copyContext(BuildProjectContextAction $buildContext): string
    {
        $this->authorize('view', $this->project);

        /** @var User $user */
        $user = Auth::user();

        return $buildContext($this->project, $user);
    }
};
?>

<div
    x-data="{
        copying: false,
        async copy() {
            if (this.copying) {
                return;
            }

            this.copying = true;

            try {
                const markdown = await $wire.copyContext();

                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(markdown);
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = markdown;
                    textarea.setAttribute('readonly', '');
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    textarea.remove();
                }

                window.Flux && Flux.toast({
                    heading: @js(__('Context copied')),
                    text: @js(__('Project context copied for AI.')),
                    variant: 'success',
                });
            } catch (error) {
                window.Flux && Flux.toast({
                    heading: @js(__('Copy failed')),
                    text: @js(__('Could not copy project context.')),
                    variant: 'danger',
                });
            } finally {
                this.copying = false;
            }
        }
    }"
>
    <flux:button
        type="button"
        icon="clipboard-document"
        variant="ghost"
        x-on:click="copy()"
        x-bind:disabled="copying"
        aria-label="{{ __('Copy context for AI') }}"
        data-test="copy-context-button"
        class="max-md:min-h-11 max-md:min-w-11 max-md:gap-0 max-md:px-2.5"
    >
        <span class="hidden md:inline">{{ __('Copy context for AI') }}</span>
    </flux:button>
</div>
