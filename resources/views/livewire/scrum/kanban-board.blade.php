<?php

use App\Enums\StoryStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Services\TaskService;
use App\Services\UserStoryService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Kanban Board — Canopy')] class extends Component {
    public Project $project;

    public ?string $selectedSprintId = null;

    public function mount(Project $project): void
    {
        $this->project = $project;

        $activeSprint = $project->sprints()->active()->first();

        if ($activeSprint) {
            $this->selectedSprintId = $activeSprint->id;
        }
    }

    public function changeTaskStatus(string $taskId, string $newStatus): void
    {
        $task = \App\Models\Task::findOrFail($taskId);

        try {
            app(TaskService::class)->changeStatus($task, TaskStatus::from($newStatus), auth()->user());
        } catch (\App\Exceptions\TaskNotAssignedException $e) {
            session()->flash('error', 'Task önce birine atanmalı.');
        } catch (\App\Exceptions\InvalidStatusTransitionException $e) {
            session()->flash('error', 'Geçersiz durum geçişi.');
        }
    }

    public function changeStoryStatus(string $storyId, string $newStatus): void
    {
        $story = \App\Models\UserStory::findOrFail($storyId);

        try {
            app(UserStoryService::class)->changeStatus($story, StoryStatus::from($newStatus), auth()->user());
        } catch (\App\Exceptions\InvalidStatusTransitionException $e) {
            session()->flash('error', 'Geçersiz durum geçişi.');
        }
    }

    #[Computed]
    public function sprint(): mixed
    {
        return $this->selectedSprintId
            ? Sprint::with(['userStories.tasks.assignee', 'userStories.epic'])->find($this->selectedSprintId)
            : null;
    }

    #[Computed]
    public function columns(): mixed
    {
        $columns = collect([
            StoryStatus::New->value => ['label' => 'New', 'color' => 'sky', 'stories' => collect()],
            StoryStatus::InProgress->value => ['label' => 'In Progress', 'color' => 'amber', 'stories' => collect()],
            StoryStatus::Done->value => ['label' => 'Done', 'color' => 'emerald', 'stories' => collect()],
        ]);

        if ($this->sprint) {
            foreach ($this->sprint->userStories as $story) {
                $statusKey = $story->status->value;
                if ($columns->has($statusKey)) {
                    $columns[$statusKey]['stories']->push($story);
                }
            }
        }

        return $columns;
    }

    #[Computed]
    public function sprints(): mixed
    {
        return $this->project->sprints()
            ->whereIn('status', ['planning', 'active'])
            ->get();
    }
}

?>

<x-project-layout :project="$project">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item href="/dashboard" wire:navigate>Projelerim</flux:breadcrumbs.item>
        <flux:breadcrumbs.item href="/projects/{{ $project->slug }}" wire:navigate>{{ $project->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Board</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Kanban Board</flux:heading>

        @if ($this->sprints->count() > 1)
            <flux:select wire:model.live="selectedSprintId" size="sm" class="w-56">
                @foreach ($this->sprints as $s)
                    <flux:select.option :value="$s->id">{{ $s->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @elseif ($this->sprint)
            <flux:badge color="indigo">{{ $this->sprint->name }}</flux:badge>
        @endif
    </div>

    @if (!$this->sprint)
        <x-empty-state
            icon="view-columns"
            heading="Sprint bulunamadı"
            description="Kanban board'u kullanmak için aktif veya planlama aşamasında bir sprint gerekli."
            action-text="Sprint Oluştur"
            :action-url="'/projects/' . $project->slug . '/sprints'"
        />
    @else
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 min-h-[60vh]">
            @foreach ($this->columns as $statusValue => $column)
                <div
                    class="flex flex-col rounded-lg bg-zinc-50 dark:bg-zinc-900 p-3"
                    x-data="{}"
                    x-init="
                        new Sortable($el.querySelector('.kanban-column-body'), {
                            group: 'kanban',
                            animation: 150,
                            ghostClass: 'opacity-30',
                            dragClass: 'shadow-xl',
                            onAdd(evt) {
                                const storyId = evt.item.dataset.storyId;
                                $wire.changeStoryStatus(storyId, '{{ $statusValue }}');
                            }
                        })
                    "
                >
                    {{-- Column Header --}}
                    <div class="flex items-center justify-between mb-3 px-1">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-{{ $column['color'] }}-500"></div>
                            <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ $column['label'] }}</span>
                        </div>
                        <flux:badge size="sm" color="zinc">{{ $column['stories']->count() }}</flux:badge>
                    </div>

                    {{-- Column Body --}}
                    <div class="kanban-column-body flex-1 space-y-2 min-h-[200px]">
                        @foreach ($column['stories'] as $story)
                            <div
                                wire:key="board-story-{{ $story->id }}"
                                data-story-id="{{ $story->id }}"
                                class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 cursor-grab active:cursor-grabbing hover:shadow-sm transition-shadow {{ $story->epic ? 'border-l-4' : '' }}"
                                @if ($story->epic) style="border-left-color: {{ $story->epic->color ?? '#6366f1' }}" @endif
                            >
                                <a href="/projects/{{ $project->slug }}/stories/{{ $story->id }}" wire:navigate class="block">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-white mb-2">{{ $story->title }}</div>
                                </a>

                                <div class="flex items-center justify-between text-xs text-zinc-500">
                                    <div class="flex items-center gap-2">
                                        @if ($story->total_points)
                                            <flux:badge size="sm" color="indigo">{{ $story->total_points }} SP</flux:badge>
                                        @endif
                                        @if ($story->tasks->count())
                                            <span>{{ $story->tasks->where('status.value', 'done')->count() }}/{{ $story->tasks->count() }} task</span>
                                        @endif
                                    </div>
                                    @if ($story->epic)
                                        <flux:badge size="sm" :color="$story->epic->color ?? 'zinc'">{{ $story->epic->title }}</flux:badge>
                                    @endif
                                </div>

                                {{-- Task Sub-cards --}}
                                @if ($story->tasks->count())
                                    <div class="mt-2 space-y-1">
                                        @foreach ($story->tasks as $task)
                                            <div class="flex items-center gap-2 text-xs p-1.5 rounded bg-zinc-50 dark:bg-zinc-900">
                                                <flux:checkbox
                                                    wire:click="changeTaskStatus('{{ $task->id }}', '{{ $task->status->value === 'done' ? 'in_progress' : 'done' }}')"
                                                    :checked="$task->status->value === 'done'"
                                                    class="size-3.5"
                                                />
                                                <span class="{{ $task->status->value === 'done' ? 'line-through text-zinc-400' : '' }} truncate">{{ $task->title }}</span>
                                                @if ($task->assignee)
                                                    <flux:avatar size="xs" :name="$task->assignee->name" class="ml-auto" />
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-project-layout>
