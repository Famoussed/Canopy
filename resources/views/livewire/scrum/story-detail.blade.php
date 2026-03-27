<?php

use App\Enums\TaskStatus;
use App\Livewire\Forms\StoryForm;
use App\Livewire\Forms\TaskForm;
use App\Models\Project;
use App\Models\UserStory;
use App\Services\TaskService;
use App\Services\UserStoryService;
use Livewire\Attributes\Async;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Story Detay — Canopy')] class extends Component {
    public Project $project;

    public UserStory $story;

    public TaskForm $taskForm;

    public StoryForm $storyForm;

    public bool $showTaskForm = false;

    public bool $editingTitle = false;

    /** @var array<string, string> */
    public array $estimationPoints = [];

    #[On('echo-private:project.{project.id},.task.status-changed')]
    #[On('echo-private:project.{project.id},.task.assigned')]
    public function refreshStoryTasks(): void
    {
        $this->story->load(['tasks.assignee', 'epic', 'sprint', 'creator', 'storyPoints', 'attachments']);
    }

    public ?string $selectedEpicId = null;

    public function mount(Project $project, UserStory $story): void
    {
        $this->project = $project;
        $this->story = $story;
        $this->storyForm->setFromStory($story);
        $this->selectedEpicId = $story->epic_id;

        // Mevcut puanları forma yükle
        foreach ($story->storyPoints as $point) {
            $this->estimationPoints[$point->role_name] = (string) (int) $point->points == $point->points
                ? (string) (int) $point->points
                : (string) $point->points;
        }
    }

    #[Async]
    public function saveEstimation(\App\Services\UserStoryService $service): void
    {
        $this->authorize('estimate', $this->story);

        $points = collect($this->estimationPoints)
            ->filter(fn ($value) => $value !== '' && $value !== null && (float) $value > 0)
            ->map(fn ($value, $role) => ['role_name' => $role, 'points' => (float) $value])
            ->values()
            ->toArray();

        $service->estimate($this->story, $points);
        $this->story->refresh();
        $this->story->load('storyPoints');
    }

    #[Computed]
    public function epics(): mixed
    {
        return $this->project->epics()->orderBy('title')->get();
    }

    #[Computed]
    public function members(): mixed
    {
        return $this->project->memberships()
            ->with('user')
            ->get()
            ->pluck('user');
    }

    #[Async]
    public function saveTitle(\App\Services\UserStoryService $service): void
    {
        $this->storyForm->validate(['title' => 'required|string|max:255']);

        $service->update($this->story, [
            'title' => $this->storyForm->title,
        ]);

        $this->story->refresh();
        $this->editingTitle = false;
    }

    #[Async]
    public function saveDescription(\App\Services\UserStoryService $service): void
    {
        $service->update($this->story, [
            'description' => $this->storyForm->description,
        ]);

        $this->story->refresh();
    }

    public function changeStatus(string $newStatus, \App\Services\UserStoryService $service): void
    {
        try {
            $service->changeStatus(
                $this->story,
                \App\Enums\StoryStatus::from($newStatus),
                auth()->user(),
            );
            $this->story->refresh();
        } catch (\App\Exceptions\InvalidStatusTransitionException) {
            session()->flash('error', 'Geçersiz durum geçişi.');
        }
    }

    public function createTask(TaskService $service): void
    {
        $this->taskForm->validate();

        try {
            $this->authorize('create', [\App\Models\Task::class, $this->story->project]);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            session()->flash('error', 'Task oluşturma yetkiniz yok.');

            return;
        }

        $service->create([
            'title' => $this->taskForm->title,
        ], $this->story, auth()->user());

        $this->taskForm->reset();
        $this->showTaskForm = false;
        $this->story->refresh();
    }

    public function updateEpic(UserStoryService $service): void
    {
        $epicId = $this->selectedEpicId ?: null;

        $service->update($this->story, [
            'epic_id' => $epicId,
        ]);

        $this->story->refresh();
    }

    public function assignTask(string $taskId, string $userId, \App\Services\TaskService $service): void
    {
        $task = $service->findById($taskId);

        try {
            $this->authorize('assign', $task);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            session()->flash('error', 'Task atama yetkiniz yok.');

            return;
        }

        if ($userId === '') {
            $service->unassign($task);
            $this->story->refresh();

            return;
        }

        $service->assignById($task, $userId, auth()->user());
        $this->story->refresh();
    }

    public function changeTaskStatus(string $taskId, string $newStatus, \App\Services\TaskService $service): void
    {
        $task = $service->findById($taskId);
        $status = TaskStatus::from($newStatus);

        try {
            $this->authorize('changeStatus', $task);
            $service->changeStatus($task, $status, auth()->user());
            $this->story->refresh();
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            session()->flash('error', 'Bu task\'\u0131n durumunu değiştirme yetkiniz yok.');
        } catch (\Exception) {
            session()->flash('error', 'Task durumu değiştirilemedi.');
        }
    }

    #[Async]
    public function toggleTaskStatus(string $taskId, TaskService $service): void
    {
        $task = $service->findById($taskId);
        $newStatus = $task->status->value === 'done'
            ? TaskStatus::InProgress
            : TaskStatus::Done;

        try {
            $this->authorize('changeStatus', $task);
            $service->changeStatus($task, $newStatus, auth()->user());
            $this->story->refresh();
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            session()->flash('error', 'Bu task\'\u0131n durumunu değiştirme yetkiniz yok.');
        } catch (\Exception) {
            session()->flash('error', 'Task durumu değiştirilemedi.');
        }
    }

    public function rendering(): void
    {
        $this->story->load(['tasks.assignee', 'epic', 'sprint', 'creator', 'storyPoints', 'attachments']);
    }
}

?>

<x-project-layout :project="$project">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item href="/dashboard" wire:navigate>Projelerim</flux:breadcrumbs.item>
        <flux:breadcrumbs.item href="/projects/{{ $project->slug }}" wire:navigate>{{ $project->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item href="/projects/{{ $project->slug }}/backlog" wire:navigate>Backlog</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ Str::limit($story->title, 30) }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex gap-6">
        {{-- Main Content (Left ~65%) --}}
        <div class="flex-1 min-w-0 space-y-6">
            {{-- Title (Inline Editable) --}}
            <div>
                @if ($editingTitle)
                    <form wire:submit="saveTitle" class="flex items-center gap-2">
                        <flux:input wire:model="storyForm.title" class="text-xl font-bold" autofocus />
                        <flux:button type="submit" variant="primary" size="sm">Kaydet</flux:button>
                        <flux:button variant="ghost" size="sm" wire:click="$set('editingTitle', false)">İptal</flux:button>
                    </form>
                @else
                    <flux:heading size="xl" class="cursor-pointer hover:text-indigo-600 transition-colors" wire:click="$set('editingTitle', true)">
                        {{ $story->title }}
                        <span wire:show="$dirty('storyForm.title')" class="text-amber-500 text-xs ml-2">Kaydedilmemiş</span>
                    </flux:heading>
                @endif
            </div>

            {{-- Description --}}
            <flux:card>
                <flux:heading class="mb-2">Açıklama</flux:heading>
                <flux:textarea
                    wire:model.live.blur="storyForm.description"
                    wire:change="saveDescription"
                    placeholder="Story açıklaması ekleyin..."
                    rows="4"
                />
            </flux:card>

            {{-- Tasks --}}
            @island(name: 'story-tasks')
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading>
                        Task'lar
                        @if ($story->tasks->count())
                            <flux:badge size="sm" color="zinc" class="ml-2">
                                {{ $story->tasks->where('status.value', 'done')->count() }}/{{ $story->tasks->count() }}
                            </flux:badge>
                        @endif
                    </flux:heading>
                    <flux:button variant="outline" size="sm" icon="plus" wire:click="$toggle('showTaskForm')">
                        Task Ekle
                    </flux:button>
                </div>

                @if ($showTaskForm)
                    <form wire:submit="createTask" class="flex items-end gap-3 mb-4" wire:transition>
                        <div class="flex-1">
                            <flux:input wire:model="taskForm.title" placeholder="Task başlığı" autofocus required />
                        </div>
                        <flux:button type="submit" variant="primary" size="sm">Ekle</flux:button>
                        <flux:button variant="ghost" size="sm" wire:click="$toggle('showTaskForm')">İptal</flux:button>
                    </form>
                @endif

                @if ($story->tasks->isEmpty())
                    <flux:text class="text-zinc-400 text-sm">Henüz task eklenmemiş.</flux:text>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                        @foreach ($story->tasks as $task)
                            <div wire:key="task-{{ $task->id }}" class="flex items-center gap-3 py-3">
                                <flux:checkbox
                                    wire:click="toggleTaskStatus('{{ $task->id }}')"
                                    :checked="$task->status->value === 'done'"
                                />
                                <span class="flex-1 text-sm {{ $task->status->value === 'done' ? 'line-through text-zinc-400' : '' }}">
                                    {{ $task->title }}
                                </span>

                                {{-- Task Status --}}
                                <flux:select
                                    wire:change="changeTaskStatus('{{ $task->id }}', $event.target.value)"
                                    size="sm"
                                    class="w-36"
                                >
                                    <flux:select.option :value="$task->status->value" selected>{{ $task->status->label() }}</flux:select.option>
                                    @foreach ($task->status->allowedTransitions()[$task->status->value] ?? [] as $transitionValue)
                                        @php $transitionEnum = \App\Enums\TaskStatus::from($transitionValue); @endphp
                                        <flux:select.option :value="$transitionValue">{{ $transitionEnum->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                {{-- Task Assignee --}}
                                <flux:select
                                    wire:change="assignTask('{{ $task->id }}', $event.target.value)"
                                    size="sm"
                                    class="w-36"
                                >
                                    <flux:select.option value="">Atanmadı</flux:select.option>
                                    @foreach ($this->members as $member)
                                        <flux:select.option :value="$member->id" :selected="$task->assigned_to === $member->id">{{ $member->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>
            @endisland
        </div>

        {{-- Sidebar (Right ~35%) --}}
        <div class="w-80 shrink-0 hidden lg:block space-y-4">
            {{-- Status --}}
            <flux:card class="space-y-3">
                <flux:heading>Durum</flux:heading>
                <div class="flex gap-2 flex-wrap">
                    @foreach ($story->availableTransitions() as $transitionValue)
                        @php $transitionEnum = \App\Enums\StoryStatus::from($transitionValue); @endphp
                        <flux:button
                            variant="outline"
                            size="sm"
                            wire:click="changeStatus('{{ $transitionValue }}')"
                        >
                            {{ $transitionEnum->label() }}
                        </flux:button>
                    @endforeach
                </div>
                <div class="flex items-center gap-2">
                    <flux:text class="text-xs">Mevcut:</flux:text>
                    <x-status-badge :status="$story->status" />
                </div>
            </flux:card>

            {{-- Details --}}
            <flux:card class="space-y-3">
                <flux:heading>Detaylar</flux:heading>
                <div class="space-y-2 text-sm">
                    {{-- Epic Assignment --}}
                    <div class="flex items-center justify-between">
                        <flux:text>Epic</flux:text>
                        <flux:select wire:model.live="selectedEpicId" wire:change="updateEpic" placeholder="Epic seçin" size="sm" class="w-40">
                            <flux:select.option value="">Epic yok</flux:select.option>
                            @foreach ($this->epics as $epic)
                                <flux:select.option :value="$epic->id">{{ $epic->title }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    @if ($story->sprint)
                        <div class="flex items-center justify-between">
                            <flux:text>Sprint</flux:text>
                            <flux:badge size="sm" color="indigo">{{ $story->sprint->name }}</flux:badge>
                        </div>
                    @endif
                    @if ($story->total_points)
                        <div class="flex items-center justify-between">
                            <flux:text>Toplam Puan</flux:text>
                            <flux:badge size="sm" color="indigo">{{ $story->total_points }} SP</flux:badge>
                        </div>
                    @endif
                    @if ($story->creator)
                        <div class="flex items-center justify-between">
                            <flux:text>Oluşturan</flux:text>
                            <div class="flex items-center gap-1">
                                <flux:avatar size="xs" :name="$story->creator->name" />
                                <span class="text-xs">{{ $story->creator->name }}</span>
                            </div>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <flux:text>Oluşturulma</flux:text>
                        <span class="text-xs text-zinc-500">{{ $story->created_at->format('d M Y H:i') }}</span>
                    </div>
                </div>
            </flux:card>

            {{-- Story Points --}}
            @island(name: 'story-estimation')
            <flux:card class="space-y-3">
                <flux:heading>Story Puanları</flux:heading>

                @can('estimate', $story)
                    <form wire:submit="saveEstimation" class="space-y-2">
                        @foreach ($project->getEstimationRoles() as $role)
                            <div class="flex items-center justify-between text-sm">
                                <flux:text>{{ $role }}</flux:text>
                                <flux:input
                                    type="number"
                                    wire:model="estimationPoints.{{ $role }}"
                                    size="sm"
                                    class="w-20"
                                    min="0"
                                    max="999"
                                    step="0.5"
                                    placeholder="0"
                                />
                            </div>
                        @endforeach
                        <flux:button type="submit" variant="primary" size="sm" class="w-full">Puanla</flux:button>
                    </form>
                @else
                    @if ($story->storyPoints->count())
                        <div class="space-y-1">
                            @foreach ($story->storyPoints as $point)
                                <div class="flex items-center justify-between text-sm">
                                    <flux:text>{{ $point->role_name }}</flux:text>
                                    <flux:badge size="sm" color="zinc">{{ $point->points }}</flux:badge>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <flux:text class="text-zinc-400 text-sm">Henüz puanlanmamış.</flux:text>
                    @endif
                @endcan
            </flux:card>
            @endisland

            {{-- Attachments --}}
            <flux:card class="space-y-3">
                <flux:heading>Dosyalar</flux:heading>
                @if ($story->attachments->isEmpty())
                    <flux:text class="text-zinc-400 text-sm">Dosya eklenmemiş.</flux:text>
                @else
                    <div class="space-y-1">
                        @foreach ($story->attachments as $attachment)
                            <div class="flex items-center gap-2 text-sm">
                                <flux:icon name="paper-clip" variant="mini" class="size-4 text-zinc-400" />
                                <span class="truncate">{{ $attachment->filename }}</span>
                                <flux:badge size="sm" color="zinc">{{ number_format($attachment->size / 1024, 0) }}KB</flux:badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</x-project-layout>
