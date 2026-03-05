<?php

use App\Models\Epic;
use App\Models\Project;
use App\Services\EpicService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Epic\'ler — Canopy')] class extends Component {
    public Project $project;

    public bool $showCreateForm = false;

    public string $title = '';

    public string $description = '';

    public string $color = '#6366F1';

    public ?string $editingEpicId = null;

    public string $editTitle = '';

    public string $editDescription = '';

    public string $editColor = '';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function createEpic(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'required|string|max:7',
        ]);

        app(EpicService::class)->create([
            'title' => $this->title,
            'description' => $this->description,
            'color' => $this->color,
        ], $this->project, auth()->user());

        $this->reset(['title', 'description', 'color', 'showCreateForm']);
        $this->color = '#6366F1';
    }

    public function editEpic(string $epicId): void
    {
        $epic = Epic::findOrFail($epicId);
        $this->editingEpicId = $epicId;
        $this->editTitle = $epic->title;
        $this->editDescription = $epic->description ?? '';
        $this->editColor = $epic->color ?? '#6366F1';
    }

    public function updateEpic(): void
    {
        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editDescription' => 'nullable|string',
            'editColor' => 'required|string|max:7',
        ]);

        $epic = Epic::findOrFail($this->editingEpicId);
        app(EpicService::class)->update($epic, [
            'title' => $this->editTitle,
            'description' => $this->editDescription,
            'color' => $this->editColor,
        ]);

        $this->editingEpicId = null;
    }

    public function deleteEpic(string $epicId): void
    {
        $epic = Epic::findOrFail($epicId);
        app(EpicService::class)->delete($epic);
    }

    #[Computed]
    public function epics(): mixed
    {
        return $this->project->epics()
            ->withCount('userStories')
            ->orderBy('order')
            ->get();
    }
}

?>

<x-project-layout :project="$project">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Epic'ler</flux:heading>
        <flux:button variant="primary" icon="plus" wire:click="$toggle('showCreateForm')">Yeni Epic</flux:button>
    </div>

    @if ($showCreateForm)
        <flux:card class="mb-6">
            <flux:heading class="mb-4">Yeni Epic Oluştur</flux:heading>
            <form wire:submit="createEpic" class="space-y-4">
                <flux:input wire:model="title" label="Epic Başlığı" placeholder="Kullanıcı Yönetimi" required />
                <flux:textarea wire:model="description" label="Açıklama" rows="3" placeholder="Epic açıklaması..." />
                <div>
                    <flux:text class="text-sm font-medium mb-1">Renk</flux:text>
                    <input type="color" wire:model="color" class="h-10 w-20 rounded cursor-pointer border border-zinc-200 dark:border-zinc-700" />
                </div>
                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">Oluştur</flux:button>
                    <flux:button variant="ghost" wire:click="$toggle('showCreateForm')">İptal</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    @if ($this->epics->isEmpty())
        <x-empty-state
            icon="rectangle-stack"
            title="Henüz epic yok"
            description="Epic'ler, ilgili story'leri gruplamak için kullanılır."
        />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->epics as $epic)
                <flux:card wire:key="epic-{{ $epic->id }}" class="relative overflow-hidden">
                    {{-- Color indicator strip --}}
                    <div class="absolute top-0 left-0 right-0 h-1" style="background-color: {{ $epic->color ?? '#6366F1' }}"></div>

                    @if ($editingEpicId === $epic->id)
                        <form wire:submit="updateEpic" class="space-y-3 pt-2">
                            <flux:input wire:model="editTitle" label="Başlık" required />
                            <flux:textarea wire:model="editDescription" label="Açıklama" rows="2" />
                            <div>
                                <flux:text class="text-sm font-medium mb-1">Renk</flux:text>
                                <input type="color" wire:model="editColor" class="h-8 w-16 rounded cursor-pointer border border-zinc-200 dark:border-zinc-700" />
                            </div>
                            <div class="flex gap-2">
                                <flux:button type="submit" variant="primary" size="sm">Kaydet</flux:button>
                                <flux:button variant="ghost" size="sm" wire:click="$set('editingEpicId', null)">İptal</flux:button>
                            </div>
                        </form>
                    @else
                        <div class="pt-2 space-y-2">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <flux:heading>{{ $epic->title }}</flux:heading>
                                    @if ($epic->description)
                                        <flux:text class="text-sm text-zinc-500 mt-1 line-clamp-2">{{ $epic->description }}</flux:text>
                                    @endif
                                </div>
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" wire:click="editEpic('{{ $epic->id }}')">Düzenle</flux:menu.item>
                                        <flux:menu.item icon="trash" variant="danger" wire:click="deleteEpic('{{ $epic->id }}')" wire:confirm="Bu epic silinecek ve story'lerin epic bağlantısı kaldırılacak. Emin misiniz?">Sil</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                            <div class="flex items-center gap-3">
                                <flux:badge size="sm" color="zinc">{{ $epic->user_stories_count }} Story</flux:badge>
                                @if ($epic->completion_percentage)
                                    <div class="flex items-center gap-2 flex-1">
                                        <div class="flex-1 h-1.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full transition-all" style="width: {{ $epic->completion_percentage }}%; background-color: {{ $epic->color ?? '#6366F1' }}"></div>
                                        </div>
                                        <flux:text class="text-xs">%{{ $epic->completion_percentage }}</flux:text>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif
</x-project-layout>
