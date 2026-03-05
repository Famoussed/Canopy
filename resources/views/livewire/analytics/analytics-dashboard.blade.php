<?php

use App\Models\Project;
use App\Services\BurndownService;
use App\Services\VelocityService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Analiz — Canopy')] class extends Component {
    public Project $project;

    public int $velocitySprintCount = 5;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    #[Computed]
    public function activeSprint(): mixed
    {
        return $this->project->sprints()->active()->with('userStories')->first();
    }

    #[Computed]
    public function velocityData(): array
    {
        return app(VelocityService::class)->getVelocityData($this->project, $this->velocitySprintCount);
    }

    #[Computed]
    public function burndownData(): array
    {
        return $this->activeSprint
            ? app(BurndownService::class)->getBurndownData($this->activeSprint)
            : [];
    }

    #[Computed]
    public function totalStories(): int
    {
        return $this->project->userStories()->count();
    }

    #[Computed]
    public function completedStories(): int
    {
        return $this->project->userStories()->byStatus(\App\Enums\StoryStatus::Done)->count();
    }

    #[Computed]
    public function totalIssues(): int
    {
        return $this->project->issues()->count();
    }

    #[Computed]
    public function openIssues(): int
    {
        return $this->project->issues()->open()->count();
    }

    #[Computed]
    public function closedSprints(): int
    {
        return $this->project->sprints()->closed()->count();
    }

    #[Computed]
    public function completionRate(): int
    {
        return $this->totalStories > 0 ? (int) round(($this->completedStories / $this->totalStories) * 100) : 0;
    }
}

?>

<x-project-layout :project="$project">
    <flux:heading size="xl" class="mb-6">Analiz</flux:heading>

    {{-- Summary Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
        <x-stat-card label="Toplam Story" :value="$this->totalStories" icon="document-text" />
        <x-stat-card label="Tamamlanan" :value="$this->completedStories" icon="check-circle" color="green" />
        <x-stat-card label="Tamamlanma" :value="$this->completionRate . '%'" icon="chart-pie" color="indigo" />
        <x-stat-card label="Kapatılan Sprint" :value="$this->closedSprints" icon="clock" color="blue" />
        <x-stat-card label="Toplam Issue" :value="$this->totalIssues" icon="clipboard-document-list" />
        <x-stat-card label="Açık Issue" :value="$this->openIssues" icon="exclamation-circle" color="red" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Velocity Chart --}}
        <flux:card>
            <flux:heading class="mb-4">Velocity (Son {{ $velocitySprintCount }} Sprint)</flux:heading>
            @if (empty($this->velocityData))
                <x-empty-state icon="chart-bar" title="Veri yok" description="Kapatılmış sprint verisi bulunamadı." />
            @else
                <div
                    x-data="{
                        data: @js($this->velocityData),
                        get maxPoints() {
                            return Math.max(...this.data.map(d => d.points || d.story_points || 0), 1);
                        }
                    }"
                    class="space-y-3"
                >
                    <template x-for="(item, index) in data" :key="index">
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-zinc-500 w-24 truncate" x-text="item.sprint_name || item.name || ('Sprint ' + (index + 1))"></span>
                            <div class="flex-1 h-6 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-indigo-500 rounded-full transition-all duration-500"
                                    :style="'width: ' + ((item.points || item.story_points || 0) / maxPoints * 100) + '%'"
                                ></div>
                            </div>
                            <span class="text-sm font-medium w-12 text-right" x-text="(item.points || item.story_points || 0) + ' SP'"></span>
                        </div>
                    </template>
                </div>
            @endif
        </flux:card>

        {{-- Burndown Chart --}}
        <flux:card>
            <flux:heading class="mb-4">
                Burndown
                @if ($this->activeSprint)
                    <flux:badge size="sm" color="indigo" class="ml-2">{{ $this->activeSprint->name }}</flux:badge>
                @endif
            </flux:heading>
            @if (empty($this->burndownData))
                <x-empty-state icon="chart-bar" title="Aktif sprint yok" description="Burndown grafiği aktif bir sprint gerektirir." />
            @else
                <div
                    x-data="{
                        data: @js($this->burndownData),
                        get maxValue() {
                            return Math.max(...this.data.map(d => d.ideal || d.remaining || 0), 1);
                        },
                        svgPoints(key) {
                            if (!this.data.length) return '';
                            const width = 400;
                            const height = 200;
                            const step = width / Math.max(this.data.length - 1, 1);
                            return this.data.map((d, i) =>
                                (i * step) + ',' + (height - ((d[key] || 0) / this.maxValue * height))
                            ).join(' ');
                        }
                    }"
                >
                    <svg viewBox="0 0 400 200" class="w-full h-48">
                        {{-- Grid lines --}}
                        <line x1="0" y1="0" x2="0" y2="200" stroke="#e4e4e7" stroke-width="1" />
                        <line x1="0" y1="200" x2="400" y2="200" stroke="#e4e4e7" stroke-width="1" />
                        <line x1="0" y1="100" x2="400" y2="100" stroke="#e4e4e7" stroke-width="0.5" stroke-dasharray="4" />
                        <line x1="0" y1="50" x2="400" y2="50" stroke="#e4e4e7" stroke-width="0.5" stroke-dasharray="4" />
                        <line x1="0" y1="150" x2="400" y2="150" stroke="#e4e4e7" stroke-width="0.5" stroke-dasharray="4" />

                        {{-- Ideal line --}}
                        <polyline
                            :points="svgPoints('ideal')"
                            fill="none"
                            stroke="#a5b4fc"
                            stroke-width="2"
                            stroke-dasharray="6"
                        />
                        {{-- Actual remaining --}}
                        <polyline
                            :points="svgPoints('remaining')"
                            fill="none"
                            stroke="#6366f1"
                            stroke-width="2.5"
                        />
                    </svg>
                    <div class="flex items-center justify-center gap-6 mt-2 text-xs text-zinc-500">
                        <div class="flex items-center gap-1">
                            <div class="w-4 h-0.5 bg-indigo-400" style="border-top: 2px dashed;"></div>
                            <span>İdeal</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <div class="w-4 h-0.5 bg-indigo-600"></div>
                            <span>Gerçek</span>
                        </div>
                    </div>
                </div>
            @endif
        </flux:card>
    </div>

    {{-- Story Status Distribution --}}
    <flux:card class="mt-6">
        <flux:heading class="mb-4">Story Durum Dağılımı</flux:heading>
        @php
            $statusCounts = $project->userStories()
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');
            $total = $statusCounts->sum();
        @endphp
        @if ($total === 0)
            <flux:text class="text-zinc-400 text-sm">Henüz story oluşturulmamış.</flux:text>
        @else
            <div class="flex h-4 rounded-full overflow-hidden bg-zinc-100 dark:bg-zinc-800 mb-3">
                @foreach (\App\Enums\StoryStatus::cases() as $status)
                    @php $count = $statusCounts[$status->value] ?? 0; @endphp
                    @if ($count > 0)
                        <div
                            class="h-full transition-all"
                            style="width: {{ ($count / $total) * 100 }}%; background-color: {{ $status->color() }}"
                            title="{{ $status->label() }}: {{ $count }}"
                        ></div>
                    @endif
                @endforeach
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                @foreach (\App\Enums\StoryStatus::cases() as $status)
                    @php $count = $statusCounts[$status->value] ?? 0; @endphp
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full" style="background-color: {{ $status->color() }}"></div>
                        <span>{{ $status->label() }}: {{ $count }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>
</x-project-layout>
