<?php

namespace App\Livewire\Forms;

use App\Models\InboxItem;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Form;

class QuickCaptureForm extends Form
{
    public ?Project $project = null;

    #[Validate('required|string|max:1000')]
    public string $content = '';

    public function setProject(Project $project)
    {
        $this->project = $project;
    }

    public function save()
    {
        $this->validate();

        Gate::authorize('create', [InboxItem::class, $this->project]);

        InboxItem::create([
            'user_id' => auth()->id(),
            'project_id' => $this->project?->id,
            'content' => $this->content,
        ]);

        $this->reset('content');

        $this->component->dispatch('item-captured');
    }
}
