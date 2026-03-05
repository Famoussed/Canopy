<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Proje Dashboard — Canopy')] class extends Component {
    public Project $project;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    #[Computed]
    public function activeSprint(): mixed
    {
        return $this->project->sprints()->active()->first();
    }

    #[Computed]
    public function backlogCount(): int
    {
        return $this->project->userStories()->backlog()->count();
    }

    #[Computed]
    public function totalStories(): int
    {
        return $this->project->userStories()->count();
    }

    #[Computed]
    public function doneStories(): int
    {
        return $this->project->userStories()->byStatus('done')->count();
    }

    #[Computed]
    public function openIssues(): int
    {
        return $this->project->issues()->open()->count();
    }

    #[Computed]
    public function recentStories(): mixed
    {
        return $this->project->userStories()
            ->with('epic', 'sprint')
            ->latest()
            ->limit(5)
            ->get();
    }
}

?>

<x-project-layout :project="$project">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item href="/dashboard" wire:navigate>Projelerim</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $project->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <flux:heading size="xl" class="mb-6">{{ $project->name }}</flux:heading>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <x-stat-card
            label="Backlog"
            :value="$this->backlogCount"
            icon="queue-list"
        />
        <x-stat-card
            label="Toplam Story"
            :value="$this->totalStories"
            icon="document-text"
        />
        <x-stat-card
            label="Tamamlanan"
            :value="$this->doneStories"
            icon="check-circle"
        />
        <x-stat-card
            label="Açık Issue"
            :value="$this->openIssues"
            icon="exclamation-triangle"
        />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Active Sprint --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Aktif Sprint</flux:heading>
            @if ($this->activeSprint)
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:heading>{{ $this->activeSprint->name }}</flux:heading>
                        <x-status-badge :status="$this->activeSprint->status" />
                    </div>
                    <div class="flex items-center gap-4 text-sm text-zinc-500">
                        <span>{{ $this->activeSprint->start_date?->format('d M') }} — {{ $this->activeSprint->end_date?->format('d M Y') }}</span>
                        <span>{{ $this->activeSprint->userStories->count() }} story</span>
                    </div>
                    <flux:button variant="outline" size="sm" href="/projects/{{ $project->slug }}/board" wire:navigate>
                        Board'u Aç
                    </flux:button>
                </div>
            @else
                <flux:text class="text-zinc-400">Aktif sprint bulunmuyor.</flux:text>
            @endif
        </flux:card>

        {{-- Recent Stories --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Son Eklenen Story'ler</flux:heading>
            @if ($this->recentStories->isEmpty())
                <flux:text class="text-zinc-400">Henüz story eklenmemiş.</flux:text>
            @else
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @foreach ($this->recentStories as $story)
                        <a href="/projects/{{ $project->slug }}/stories/{{ $story->id }}" wire:navigate
                           class="flex items-center justify-between py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 -mx-2 px-2 rounded">
                            <div class="flex items-center gap-2 min-w-0">
                                <x-status-badge :status="$story->status" />
                                <span class="text-sm truncate">{{ $story->title }}</span>
                            </div>
                            @if ($story->total_points)
                                <flux:badge size="sm" color="zinc">{{ $story->total_points }} SP</flux:badge>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</x-project-layout>
