<?php

namespace App\Livewire\Forms;

use App\Models\InboxItem;
use Flux\Flux;
use Livewire\Attributes\Validate;
use Livewire\Form;

class InboxItemForm extends Form
{
    #[Validate('required|string|max:1000')]
    public string $content = '';

    public ?int $projectId = null;

    public ?int $userId = null;

    public function store(): void
    {
        $this->validate();

        InboxItem::create([
            'content' => $this->content,
            'project_id' => $this->projectId,
            'user_id' => $this->userId,
        ]);

        $this->reset('content');

        Flux::toast(
            heading: 'Idea Captured',
            text: 'Your idea has been added to the inbox.',
            variant: 'success',
            duration: 3000,
        );
    }
}
