<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Projelerim — Canopy')] class extends Component {
    public function render(): mixed
    {
        return view($this->viewName(), [
            'projects' => auth()->user()
                ->projectMemberships()
                ->with('project.owner', 'project.memberships')
                ->get()
                ->pluck('project')
                ->filter(),
        ]);
    }
}

?>

<div>
    <flux:sidebar sticky collapsible class="border-e bg-zinc-50 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <a href="/dashboard" wire:navigate class="flex items-center gap-2 px-2">
            <flux:icon name="tree-pine" class="size-6 text-indigo-600" />
            <span class="text-lg font-bold text-zinc-900 dark:text-white">Canopy</span>
        </a>

        <flux:navlist variant="outline">
            <flux:navlist.group heading="Menü">
                <flux:navlist.item icon="home" href="/dashboard" wire:navigate wire:current="font-semibold">
                    Projelerim
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            <flux:navlist.item icon="arrow-right-start-on-rectangle" wire:click="$dispatch('logout')">
                Çıkış Yap
            </flux:navlist.item>
        </flux:navlist>
    </flux:sidebar>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:brand name="Canopy" />
    </flux:header>

    <flux:main>
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="xl">Projelerim</flux:heading>
            <flux:button variant="primary" icon="plus" href="/projects/create" wire:navigate>
                Yeni Proje
            </flux:button>
        </div>

        @if ($projects->isEmpty())
            <flux:card class="text-center py-16">
                <div class="flex flex-col items-center gap-4">
                    <flux:icon name="folder-open" class="size-12 text-zinc-300" />
                    <div>
                        <flux:heading size="lg">Henüz projeniz yok</flux:heading>
                        <flux:subheading>İlk projenizi oluşturarak başlayın</flux:subheading>
                    </div>
                    <flux:button variant="primary" icon="plus" href="/projects/create" wire:navigate>
                        Proje Oluştur
                    </flux:button>
                </div>
            </flux:card>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($projects as $project)
                    <flux:card class="hover:shadow-md transition-shadow">
                        <a href="/projects/{{ $project->slug }}" wire:navigate class="block space-y-3">
                            <div class="flex items-center justify-between">
                                <flux:heading size="lg" class="truncate">{{ $project->name }}</flux:heading>
                                <flux:badge size="sm" color="indigo">
                                    {{ auth()->user()->projectMemberships->where('project_id', $project->id)->first()?->role->label() ?? 'Üye' }}
                                </flux:badge>
                            </div>

                            @if ($project->description)
                                <flux:text class="line-clamp-2">{{ $project->description }}</flux:text>
                            @endif

                            <div class="flex items-center gap-4 text-xs text-zinc-500">
                                <span class="flex items-center gap-1">
                                    <flux:icon name="users" variant="mini" class="size-4" />
                                    {{ $project->memberships->count() }} üye
                                </span>
                            </div>
                        </a>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </flux:main>
</div>
