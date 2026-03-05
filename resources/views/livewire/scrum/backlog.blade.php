<?php

use App\Models\Project;
use App\Models\UserStory;
use App\Services\UserStoryService;
use App\Services\SprintService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Backlog — Canopy')] class extends Component {
    public Project $project;

    public string $newStoryTitle = '';

    public ?string $filterEpicId = null;

    public bool $showCreateForm = false;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function createStory(): void
    {
        $this->validate([
            'newStoryTitle' => ['required', 'string', 'max:255'],
        ]);

        app(UserStoryService::class)->create([
            'title' => $this->newStoryTitle,
            'project_id' => $this->project->id,
        ], auth()->user());

        $this->newStoryTitle = '';
        $this->showCreateForm = false;
    }

    public function moveToSprint(string $storyId, string $sprintId): void
    {
        $story = UserStory::findOrFail($storyId);

        app(UserStoryService::class)->moveToSprint($story, $sprintId, auth()->user());
    }

    public function reorder(array $orderedIds): void
    {
        app(UserStoryService::class)->reorder($orderedIds, auth()->user());
    }

    #[Computed]
    public function stories(): mixed
    {
        $query = $this->project->userStories()
            ->backlog()
            ->with('epic', 'creator', 'tasks')
            ->ordered();

        if ($this->filterEpicId) {
            $query->where('epic_id', $this->filterEpicId);
        }

        return $query->get();
    }

    #[Computed]
    public function sprints(): mixed
    {
        return $this->project->sprints()
            ->whereIn('status', ['planning', 'active'])
            ->withCount('userStories')
            ->get();
    }

    #[Computed]
    public function epics(): mixed
    {
        return $this->project->epics()->orderBy('title')->get();
    }
}

?>

<x-project-layout :project="$project">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item href="/dashboard" wire:navigate>Projelerim</flux:breadcrumbs.item>
        <flux:breadcrumbs.item href="/projects/{{ $project->slug }}" wire:navigate>{{ $project->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Backlog</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Backlog</flux:heading>
        <flux:button variant="primary" icon="plus" wire:click="$toggle('showCreateForm')">
            Yeni Story
        </flux:button>
    </div>

    {{-- Inline Create Form --}}
    @if ($showCreateForm)
        <flux:card class="mb-4">
            <form wire:submit="createStory" class="flex items-end gap-3">
                <div class="flex-1">
                    <flux:input
                        wire:model="newStoryTitle"
                        label="Story Başlığı"
                        placeholder="Kullanıcı olarak ... istiyorum"
                        autofocus
                        required
                    />
                </div>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    Ekle
                </flux:button>
                <flux:button variant="ghost" wire:click="$toggle('showCreateForm')">
                    İptal
                </flux:button>
            </form>
        </flux:card>
    @endif

    <div class="flex gap-6">
        {{-- Story List (Left Column ~70%) --}}
        <div class="flex-1 min-w-0">
            {{-- Filters --}}
            <div class="flex items-center gap-3 mb-4">
                <flux:select wire:model.live="filterEpicId" placeholder="Tüm Epic'ler" size="sm" class="w-48">
                    <flux:select.option value="">Tüm Epic'ler</flux:select.option>
                    @foreach ($this->epics as $epic)
                        <flux:select.option :value="$epic->id">{{ $epic->title }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:badge size="sm" color="zinc">{{ $this->stories->count() }} story</flux:badge>
            </div>

            @if ($this->stories->isEmpty())
                <x-empty-state
                    icon="queue-list"
                    heading="Backlog boş"
                    description="İlk story'nizi ekleyerek başlayın"
                />
            @else
                <div
                    class="space-y-1"
                    x-data="{}"
                    x-init="
                        new Sortable($el, {
                            handle: '.drag-handle',
                            animation: 150,
                            ghostClass: 'opacity-30',
                            onEnd(evt) {
                                const ids = Array.from(evt.from.children).map(el => el.dataset.storyId);
                                $wire.reorder(ids);
                            }
                        })
                    "
                >
                    @foreach ($this->stories as $story)
                        <div
                            wire:key="story-{{ $story->id }}"
                            data-story-id="{{ $story->id }}"
                            class="flex items-center gap-3 px-3 py-2 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg hover:shadow-sm transition-shadow group"
                        >
                            {{-- Drag Handle --}}
                            <span class="drag-handle cursor-grab text-zinc-300 hover:text-zinc-500">
                                <flux:icon name="bars-2" variant="mini" class="size-4" />
                            </span>

                            {{-- Story Info --}}
                            <a href="/projects/{{ $project->slug }}/stories/{{ $story->id }}" wire:navigate
                               class="flex-1 min-w-0 flex items-center gap-2">
                                <x-status-badge :status="$story->status" />
                                <span class="text-sm font-medium truncate">{{ $story->title }}</span>
                            </a>

                            {{-- Epic Badge --}}
                            @if ($story->epic)
                                <flux:badge size="sm" :color="$story->epic->color ?? 'zinc'">
                                    {{ $story->epic->title }}
                                </flux:badge>
                            @endif

                            {{-- Points --}}
                            @if ($story->total_points)
                                <flux:badge size="sm" color="indigo">{{ $story->total_points }} SP</flux:badge>
                            @endif

                            {{-- Tasks Count --}}
                            <span class="text-xs text-zinc-400">
                                {{ $story->tasks->where('status.value', 'done')->count() }}/{{ $story->tasks->count() }}
                            </span>

                            {{-- Actions Dropdown --}}
                            <flux:dropdown>
                                <flux:button icon="ellipsis-vertical" variant="ghost" size="sm" class="opacity-0 group-hover:opacity-100" />
                                <flux:menu>
                                    @foreach ($this->sprints as $sprint)
                                        <flux:menu.item wire:click="moveToSprint('{{ $story->id }}', '{{ $sprint->id }}')">
                                            Sprint'e Taşı: {{ $sprint->name }}
                                        </flux:menu.item>
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Sprint Panel (Right Column ~30%) --}}
        <div class="w-72 shrink-0 hidden lg:block space-y-4">
            <flux:heading size="lg">Sprints</flux:heading>

            @forelse ($this->sprints as $sprint)
                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading>{{ $sprint->name }}</flux:heading>
                        <x-status-badge :status="$sprint->status" />
                    </div>
                    <div class="text-xs text-zinc-500 space-y-1">
                        <div>{{ $sprint->user_stories_count }} story</div>
                        @if ($sprint->start_date)
                            <div>{{ $sprint->start_date->format('d M') }} — {{ $sprint->end_date?->format('d M') }}</div>
                        @endif
                    </div>
                    <flux:button variant="outline" size="sm" href="/projects/{{ $project->slug }}/sprints/{{ $sprint->id }}" wire:navigate class="w-full">
                        Detay
                    </flux:button>
                </flux:card>
            @empty
                <flux:card>
                    <flux:text class="text-zinc-400 text-sm">Henüz sprint oluşturulmamış.</flux:text>
                </flux:card>
            @endforelse
        </div>
    </div>
</x-project-layout>
