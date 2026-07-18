<?php

namespace App\Livewire\Projects\Files;

use App\Models\Project;
use Livewire\Component;

class DeleteConfirmationModal extends Component
{
    public Project $project;

    // We get these from the orchestrator, or orchestrator calls delete directly.
    // Actually, if we extract it, we need a way to receive the selectedIds, or we keep delete logic in Orchestrator.
    // The design says: "Extract the Modals into Livewire components... keeping the parent Livewire orchestrator."
    // Wait, if the parent has the selectedIds and handles delete logic, the Delete Confirmation can just be a Blade component or a Livewire component that triggers the parent.
    // If it's a Livewire component, it needs the selectedIds.
    // Let's pass selectedIds to it or let it call a method on the parent. Or maybe Delete Confirmation is just a stateless blade component inside the orchestrator?
    // Task specifically said "Livewire component" for DeleteConfirmationModal.
    // We can use Reactive props (in Livewire v4 it's #[Reactive] or modelable), or just pass them in.

    // Let's make it accept selectedFoldersCount, selectedFilesCount, and when confirmed, dispatch 'delete-confirmed' or call parent method.
    // Given Livewire's nature, dispatching an event 'delete-confirmed' is perfect.

    public int $selectedFoldersCount = 0;

    public int $selectedFilesCount = 0;

    public function delete()
    {
        $this->dispatch('delete-confirmed');
    }

    public function render()
    {
        return view('livewire.projects.files.delete-confirmation-modal');
    }
}
