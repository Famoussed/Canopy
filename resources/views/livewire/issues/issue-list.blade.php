<?php

use App\Enums\IssuePriority;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use App\Services\IssueService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Issue\'lar — Canopy')] class extends Component {
    public Project $project;

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $priorityFilter = '';

    public bool $showCreateForm = false;

    // Create form fields
    public string $title = '';

    public string $description = '';

    public string $type = 'bug';

    public string $priority = 'normal';

    public string $severity = 'minor';

    // Edit form
    public ?string $editingIssueId = null;

    public string $editTitle = '';

    public string $editDescription = '';

    public string $editType = '';

    public string $editPriority = '';

    public string $editSeverity = '';

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    #[On('echo-private:project.{project.id},issue.created')]
    #[On('echo-private:project.{project.id},issue.status-changed')]
    public function refreshIssues(): void
    {
        unset($this->issues, $this->counts);
    }

    public function createIssue(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:bug,question,enhancement',
            'priority' => 'required|in:low,normal,high',
            'severity' => 'required|in:wishlist,minor,critical',
        ]);

        app(IssueService::class)->create([
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority,
            'severity' => $this->severity,
        ], $this->project, auth()->user());

        $this->reset(['title', 'description', 'type', 'priority', 'severity', 'showCreateForm']);
        $this->type = 'bug';
        $this->priority = 'normal';
        $this->severity = 'minor';
    }

    public function editIssue(string $issueId): void
    {
        $issue = Issue::findOrFail($issueId);
        $this->editingIssueId = $issueId;
        $this->editTitle = $issue->title;
        $this->editDescription = $issue->description ?? '';
        $this->editType = $issue->type->value;
        $this->editPriority = $issue->priority->value;
        $this->editSeverity = $issue->severity->value;
    }

    public function updateIssue(): void
    {
        $this->validate([
            'editTitle' => 'required|string|max:255',
        ]);

        $issue = Issue::findOrFail($this->editingIssueId);
        app(IssueService::class)->update($issue, [
            'title' => $this->editTitle,
            'description' => $this->editDescription,
            'type' => $this->editType,
            'priority' => $this->editPriority,
            'severity' => $this->editSeverity,
        ]);

        $this->editingIssueId = null;
    }

    public function changeStatus(string $issueId, string $newStatus): void
    {
        $issue = Issue::findOrFail($issueId);

        try {
            app(IssueService::class)->changeStatus(
                $issue,
                IssueStatus::from($newStatus),
                auth()->user(),
            );
        } catch (\App\Exceptions\InvalidStatusTransitionException) {
            session()->flash('error', 'Geçersiz durum geçişi.');
        }
    }

    public function deleteIssue(string $issueId): void
    {
        $issue = Issue::findOrFail($issueId);
        app(IssueService::class)->delete($issue);
    }

    #[Computed]
    public function issues(): mixed
    {
        $query = $this->project->issues()->with(['assignee', 'creator']);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }
        if ($this->priorityFilter) {
            $query->where('priority', $this->priorityFilter);
        }

        return $query->latest()->get();
    }

    #[Computed]
    public function counts(): array
    {
        return [
            'total' => $this->project->issues()->count(),
            'open' => $this->project->issues()->open()->count(),
            'bugs' => $this->project->issues()->byType(IssueType::Bug)->count(),
            'critical' => $this->project->issues()->bySeverity(IssueSeverity::Critical)->count(),
        ];
    }
}

?>

<x-project-layout :project="$project">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Issue'lar</flux:heading>
        <flux:button variant="primary" icon="plus" wire:click="$toggle('showCreateForm')">Yeni Issue</flux:button>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <x-stat-card label="Toplam" :value="$this->counts['total']" icon="clipboard-document-list" />
        <x-stat-card label="Açık" :value="$this->counts['open']" icon="exclamation-circle" color="blue" />
        <x-stat-card label="Bug" :value="$this->counts['bugs']" icon="bug-ant" color="red" />
        <x-stat-card label="Kritik" :value="$this->counts['critical']" icon="fire" color="red" />
    </div>

    @session('error')
        <flux:card class="mb-4 border-red-200 bg-red-50 dark:bg-red-900/20">
            <flux:text class="text-red-600 dark:text-red-400">{{ $value }}</flux:text>
        </flux:card>
    @endsession

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <flux:select wire:model.live="statusFilter" size="sm" class="w-36">
            <option value="">Tüm Durumlar</option>
            @foreach (IssueStatus::cases() as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="typeFilter" size="sm" class="w-36">
            <option value="">Tüm Tipler</option>
            @foreach (IssueType::cases() as $type)
                <option value="{{ $type->value }}">{{ $type->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="priorityFilter" size="sm" class="w-36">
            <option value="">Tüm Öncelikler</option>
            @foreach (IssuePriority::cases() as $priority)
                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
            @endforeach
        </flux:select>
        @if ($statusFilter || $typeFilter || $priorityFilter)
            <flux:button variant="ghost" size="sm" wire:click="$set('statusFilter', ''); $set('typeFilter', ''); $set('priorityFilter', '')">
                Temizle
            </flux:button>
        @endif
    </div>

    {{-- Create Form --}}
    @if ($showCreateForm)
        <flux:card class="mb-6">
            <flux:heading class="mb-4">Yeni Issue Oluştur</flux:heading>
            <form wire:submit="createIssue" class="space-y-4">
                <flux:input wire:model="title" label="Başlık" placeholder="Issue başlığı..." required />
                <flux:textarea wire:model="description" label="Açıklama" rows="3" />
                <div class="grid grid-cols-3 gap-4">
                    <flux:select wire:model="type" label="Tip">
                        @foreach (IssueType::cases() as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="priority" label="Öncelik">
                        @foreach (IssuePriority::cases() as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="severity" label="Ciddiyet">
                        @foreach (IssueSeverity::cases() as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">Oluştur</flux:button>
                    <flux:button variant="ghost" wire:click="$toggle('showCreateForm')">İptal</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    {{-- Issue Table --}}
    @if ($this->issues->isEmpty())
        <x-empty-state
            icon="clipboard-document-list"
            title="Issue bulunamadı"
            :description="($statusFilter || $typeFilter || $priorityFilter) ? 'Filtreleri değiştirmeyi deneyin.' : 'İlk issue\'nizi oluşturarak başlayın.'"
        />
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Tip</flux:table.column>
                <flux:table.column>Başlık</flux:table.column>
                <flux:table.column>Durum</flux:table.column>
                <flux:table.column>Öncelik</flux:table.column>
                <flux:table.column>Ciddiyet</flux:table.column>
                <flux:table.column>Atanan</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->issues as $issue)
                    <flux:table.row wire:key="issue-{{ $issue->id }}">
                        <flux:table.cell>
                            <flux:icon :name="$issue->type->icon()" variant="mini" class="size-5" style="color: {{ $issue->type->color() }}" />
                        </flux:table.cell>
                        <flux:table.cell class="font-medium">{{ $issue->title }}</flux:table.cell>
                        <flux:table.cell>
                            <x-status-badge :status="$issue->status" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :style="'background-color: ' . $issue->priority->color() . '20; color: ' . $issue->priority->color()">
                                {{ $issue->priority->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :style="'background-color: ' . $issue->severity->color() . '20; color: ' . $issue->severity->color()">
                                {{ $issue->severity->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($issue->assignee)
                                <flux:avatar size="xs" :name="$issue->assignee->name" />
                            @else
                                <flux:text class="text-xs text-zinc-400">—</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    @foreach (IssueStatus::allowedTransitions()[$issue->status->value] ?? [] as $transition)
                                        <flux:menu.item wire:click="changeStatus('{{ $issue->id }}', '{{ $transition }}')">
                                            {{ IssueStatus::from($transition)->label() }}
                                        </flux:menu.item>
                                    @endforeach
                                    <flux:menu.separator />
                                    <flux:menu.item icon="pencil" wire:click="editIssue('{{ $issue->id }}')">Düzenle</flux:menu.item>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="deleteIssue('{{ $issue->id }}')" wire:confirm="Bu issue silinecek. Emin misiniz?">Sil</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    {{-- Edit Modal --}}
    @if ($editingIssueId)
        <flux:modal wire:model="editingIssueId" class="max-w-lg">
            <flux:heading>Issue Düzenle</flux:heading>
            <form wire:submit="updateIssue" class="space-y-4 mt-4">
                <flux:input wire:model="editTitle" label="Başlık" required />
                <flux:textarea wire:model="editDescription" label="Açıklama" rows="3" />
                <div class="grid grid-cols-3 gap-4">
                    <flux:select wire:model="editType" label="Tip">
                        @foreach (IssueType::cases() as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="editPriority" label="Öncelik">
                        @foreach (IssuePriority::cases() as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="editSeverity" label="Ciddiyet">
                        @foreach (IssueSeverity::cases() as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="flex gap-2 justify-end">
                    <flux:button variant="ghost" wire:click="$set('editingIssueId', null)">İptal</flux:button>
                    <flux:button type="submit" variant="primary">Kaydet</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</x-project-layout>
