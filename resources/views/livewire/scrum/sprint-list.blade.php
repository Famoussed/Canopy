<?php

use App\Enums\SprintStatus;
use App\Exceptions\ActiveSprintAlreadyExistsException;
use App\Models\Project;
use App\Models\Sprint;
use App\Services\SprintService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Sprintler — Canopy')] class extends Component {
    public Project $project;

    public bool $showCreateForm = false;

    public string $name = '';

    public string $startDate = '';

    public string $endDate = '';

    public ?string $editingSprintId = null;

    public string $editName = '';

    public string $editStartDate = '';

    public string $editEndDate = '';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function createSprint(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
        ]);

        app(SprintService::class)->create([
            'name' => $this->name,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ], $this->project);

        $this->reset(['name', 'startDate', 'endDate', 'showCreateForm']);
    }

    public function editSprint(string $sprintId): void
    {
        $sprint = Sprint::findOrFail($sprintId);
        $this->editingSprintId = $sprintId;
        $this->editName = $sprint->name;
        $this->editStartDate = $sprint->start_date->format('Y-m-d');
        $this->editEndDate = $sprint->end_date->format('Y-m-d');
    }

    public function updateSprint(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editStartDate' => 'required|date',
            'editEndDate' => 'required|date|after:editStartDate',
        ]);

        $sprint = Sprint::findOrFail($this->editingSprintId);
        app(SprintService::class)->update($sprint, [
            'name' => $this->editName,
            'start_date' => $this->editStartDate,
            'end_date' => $this->editEndDate,
        ]);

        $this->editingSprintId = null;
    }

    public function startSprint(string $sprintId): void
    {
        $sprint = Sprint::findOrFail($sprintId);

        try {
            app(SprintService::class)->start($sprint, auth()->user());
        } catch (ActiveSprintAlreadyExistsException) {
            session()->flash('error', 'Zaten aktif bir sprint bulunuyor. Önce mevcut sprint\'i kapatın.');
        }
    }

    public function closeSprint(string $sprintId): void
    {
        $sprint = Sprint::findOrFail($sprintId);
        app(SprintService::class)->close($sprint, auth()->user());
    }

    public function deleteSprint(string $sprintId): void
    {
        $sprint = Sprint::findOrFail($sprintId);
        app(SprintService::class)->delete($sprint);
    }

    public function render(): mixed
    {
        $sprints = $this->project->sprints()
            ->withCount('userStories')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'planning' THEN 1 ELSE 2 END")
            ->orderBy('start_date', 'desc')
            ->get();

        return view($this->viewName(), compact('sprints'));
    }
}

?>

<x-project-layout :project="$project">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Sprintler</flux:heading>
        <flux:button variant="primary" icon="plus" wire:click="$toggle('showCreateForm')">Yeni Sprint</flux:button>
    </div>

    @session('error')
        <flux:card class="mb-4 border-red-200 bg-red-50 dark:bg-red-900/20">
            <flux:text class="text-red-600 dark:text-red-400">{{ $value }}</flux:text>
        </flux:card>
    @endsession

    @if ($showCreateForm)
        <flux:card class="mb-6">
            <flux:heading class="mb-4">Yeni Sprint Oluştur</flux:heading>
            <form wire:submit="createSprint" class="space-y-4">
                <flux:input wire:model="name" label="Sprint Adı" placeholder="Sprint 1" required />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="startDate" label="Başlangıç" type="date" required />
                    <flux:input wire:model="endDate" label="Bitiş" type="date" required />
                </div>
                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">Oluştur</flux:button>
                    <flux:button variant="ghost" wire:click="$toggle('showCreateForm')">İptal</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    @if ($sprints->isEmpty())
        <x-empty-state
            icon="calendar-days"
            title="Henüz sprint yok"
            description="İlk sprint'inizi oluşturarak başlayın."
        />
    @else
        <div class="space-y-4">
            @foreach ($sprints as $sprint)
                <flux:card wire:key="sprint-{{ $sprint->id }}">
                    @if ($editingSprintId === $sprint->id)
                        <form wire:submit="updateSprint" class="space-y-4">
                            <flux:input wire:model="editName" label="Sprint Adı" required />
                            <div class="grid grid-cols-2 gap-4">
                                <flux:input wire:model="editStartDate" label="Başlangıç" type="date" required />
                                <flux:input wire:model="editEndDate" label="Bitiş" type="date" required />
                            </div>
                            <div class="flex gap-2">
                                <flux:button type="submit" variant="primary" size="sm">Kaydet</flux:button>
                                <flux:button variant="ghost" size="sm" wire:click="$set('editingSprintId', null)">İptal</flux:button>
                            </div>
                        </form>
                    @else
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 mb-1">
                                    <flux:heading>{{ $sprint->name }}</flux:heading>
                                    <x-status-badge :status="$sprint->status" />
                                </div>
                                <flux:text class="text-sm text-zinc-500">
                                    {{ $sprint->start_date->format('d M Y') }} — {{ $sprint->end_date->format('d M Y') }}
                                    · {{ $sprint->user_stories_count }} Story
                                </flux:text>
                                @if ($sprint->status === SprintStatus::Active)
                                    @php
                                        $remaining = now()->diffInDays($sprint->end_date, false);
                                    @endphp
                                    <flux:text class="text-xs mt-1 {{ $remaining < 3 ? 'text-red-500' : 'text-zinc-400' }}">
                                        {{ $remaining > 0 ? $remaining . ' gün kaldı' : 'Süre doldu!' }}
                                    </flux:text>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if ($sprint->status === SprintStatus::Planning)
                                    <flux:button variant="primary" size="sm" icon="play" wire:click="startSprint('{{ $sprint->id }}')" wire:confirm="Bu sprint'i başlatmak istediğinize emin misiniz?">
                                        Başlat
                                    </flux:button>
                                    <flux:button variant="ghost" size="sm" icon="pencil" wire:click="editSprint('{{ $sprint->id }}')" />
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="deleteSprint('{{ $sprint->id }}')" wire:confirm="Bu sprint silinecek ve story'ler backlog'a dönecek. Emin misiniz?" class="text-red-500 hover:text-red-700" />
                                @elseif ($sprint->status === SprintStatus::Active)
                                    <flux:button variant="outline" size="sm" icon="stop" wire:click="closeSprint('{{ $sprint->id }}')" wire:confirm="Sprint'i kapatmak istediğinize emin misiniz? Tamamlanmamış story'ler backlog'a dönecek.">
                                        Kapat
                                    </flux:button>
                                    <flux:button variant="primary" size="sm" icon="view-columns" href="/projects/{{ $project->slug }}/board" wire:navigate>
                                        Board
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif
</x-project-layout>
