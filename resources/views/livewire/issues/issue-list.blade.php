    <?php

    use App\Enums\IssuePriority;
    use App\Enums\IssueSeverity;
    use App\Enums\IssueStatus;
    use App\Enums\IssueType;
    use App\Exceptions\InvalidStatusTransitionException;
    use App\Livewire\Forms\IssueForm;
    use App\Models\Issue;
    use App\Models\Project;
    use App\Services\IssueService;
    use Livewire\Attributes\Async;
    use Livewire\Attributes\Computed;
    use Livewire\Attributes\Layout;
    use Livewire\Attributes\On;
    use Livewire\Attributes\Title;
    use Livewire\Component;
    use Livewire\WithPagination;

    new #[Layout('components.layouts.app')] #[Title('Issue\'lar — Canopy')] class extends Component {
        use WithPagination;

        public Project $project;

        protected IssueService $issueService;

        public function boot(IssueService $issueService)
        {
            $this->issueService = $issueService;
        }

        public ?IssueStatus $statusFilter = null;

        public ?IssueType $typeFilter = null;

        public ?IssuePriority $priorityFilter = null;
        public bool $showCreateForm = false;

        public IssueForm $createForm;

        // Edit form
        public ?string $editingIssueId = null;

        public IssueForm $editForm;

        public function mount(Project $project): void
        {
            $this->project = $project;
        }

        #[On('echo-private:project.{project.id},.issue.created')]
        #[On('echo-private:project.{project.id},.issue.status-changed')]
        public function refreshIssues(): void
        {
            unset($this->issues, $this->counts);
        }

        public function createIssue(): void
        {
            $this->authorize('create', [Issue::class, $this->project]);
            $this->createForm->validate();

            $this->issueService->create($this->createForm->toArray(), $this->project, auth()->user());

            $this->createForm->resetWithDefaults();
            $this->showCreateForm = false;
        }

        public function editIssue(string $issueId): void
        {
            $issue = Issue::findOrFail($issueId);
            $this->editingIssueId = $issueId;
            $this->editForm->setFromIssue($issue);
        }

        public function updateIssue(): void
        {
            $this->editForm->validate();

            $issue = Issue::findOrFail($this->editingIssueId);
            $this->authorize('update', $issue);
            $this->issueService->update($issue, $this->editForm->toArray());

            $this->editingIssueId = null;
        }

        #[Async]
        public function changeStatus(string $issueId, string $newStatus): void
        {
            $issue = Issue::findOrFail($issueId);
            $this->authorize('changeStatus', $issue);

            try {
                $this->issueService->changeStatus(
                    $issue,
                    IssueStatus::from($newStatus),
                    auth()->user(),
                );
            } catch (InvalidStatusTransitionException) {
                session()->flash('error', 'Geçersiz durum geçişi.');
            }
        }

        public function deleteIssue(string $issueId): void
        {
            $issue = Issue::findOrFail($issueId);
            $this->authorize('delete', $issue);
            $this->issueService->delete($issue);
        }

        public function assignIssue(string $issueId, string $userId): void
        {
            $issue = Issue::findOrFail($issueId);
            $this->authorize('assign', $issue);

            $this->issueService->update($issue, [
                'assigned_to' => $userId ?: null,
            ]);

            unset($this->issues);
        }

        #[Computed]
        public function issues(): mixed
        {
            return $this->issueService->getFilteredIssues($this->project, [
                'status' => $this->statusFilter?->value,
                'type' => $this->typeFilter?->value,
                'priority' => $this->priorityFilter?->value,
            ]);
        }

        #[Computed]
        public function counts(): array
        {
            return $this->issueService->getProjectIssueCounts($this->project);
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
            <flux:card class="mb-6" wire:transition>
                <flux:heading class="mb-4">Yeni Issue Oluştur</flux:heading>
                <form wire:submit="createIssue" class="space-y-4">
                    <flux:input wire:model="createForm.title" label="Başlık" placeholder="Issue başlığı..." required />
                    <flux:textarea wire:model="createForm.description" label="Açıklama" rows="3" />
                    <div class="grid grid-cols-3 gap-4">
                        <flux:select wire:model="createForm.type" label="Tip">
                            @foreach (IssueType::cases() as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="createForm.priority" label="Öncelik">
                            @foreach (IssuePriority::cases() as $p)
                                <option value="{{ $p->value }}">{{ $p->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="createForm.severity" label="Ciddiyet">
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
                    <flux:table.column>Oluşturan</flux:table.column>
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
                                @if ($issue->creator)
                                    <flux:text class="text-xs">{{ $issue->creator->name }}</flux:text>
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

            <div class="mt-4">
                {{ $this->issues->links() }}
            </div>
        @endif

        {{-- Edit Modal --}}
        @if ($editingIssueId)
            <flux:modal wire:model="editingIssueId" class="max-w-lg" wire:transition>
                <flux:heading>Issue Düzenle</flux:heading>
                <form wire:submit="updateIssue" class="space-y-4 mt-4">
                    <flux:input wire:model="editForm.title" label="Başlık" required />
                    <flux:textarea wire:model="editForm.description" label="Açıklama" rows="3" />
                    <div class="grid grid-cols-3 gap-4">
                        <flux:select wire:model="editForm.type" label="Tip">
                            @foreach (IssueType::cases() as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="editForm.priority" label="Öncelik">
                            @foreach (IssuePriority::cases() as $p)
                                <option value="{{ $p->value }}">{{ $p->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="editForm.severity" label="Ciddiyet">
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
