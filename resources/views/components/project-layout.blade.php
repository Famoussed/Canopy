@props(['project', 'title' => ''])

<div>
    <flux:sidebar sticky collapsible class="border-e bg-zinc-50 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <a href="/dashboard" wire:navigate class="flex items-center gap-2 px-2">
            <div class="size-7 rounded-lg bg-indigo-600 flex items-center justify-center shrink-0">
                <flux:icon name="squares-2x2" variant="mini" class="size-4 text-white" />
            </div>
            <span class="text-lg font-bold text-zinc-900 dark:text-white">Canopy</span>
        </a>

        <flux:navlist variant="outline">
            <flux:navlist.group :heading="$project->name">
                <flux:navlist.item icon="chart-bar-square" href="/projects/{{ $project->slug }}" wire:navigate>
                    Dashboard
                </flux:navlist.item>
                <flux:navlist.item icon="queue-list" href="/projects/{{ $project->slug }}/backlog" wire:navigate>
                    Backlog
                </flux:navlist.item>
                <flux:navlist.item icon="view-columns" href="/projects/{{ $project->slug }}/board" wire:navigate>
                    Kanban Board
                </flux:navlist.item>
                <flux:navlist.item icon="arrow-path" href="/projects/{{ $project->slug }}/sprints" wire:navigate>
                    Sprints
                </flux:navlist.item>
                <flux:navlist.item icon="rectangle-stack" href="/projects/{{ $project->slug }}/epics" wire:navigate>
                    Epics
                </flux:navlist.item>
                <flux:navlist.item icon="exclamation-triangle" href="/projects/{{ $project->slug }}/issues" wire:navigate>
                    Issues
                </flux:navlist.item>
                <flux:navlist.item icon="chart-pie" href="/projects/{{ $project->slug }}/analytics" wire:navigate>
                    Analiz
                </flux:navlist.item>
                <flux:navlist.item icon="cog-6-tooth" href="/projects/{{ $project->slug }}/settings" wire:navigate>
                    Ayarlar
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            <flux:navlist.item icon="arrow-left" href="/dashboard" wire:navigate>
                Projelere Dön
            </flux:navlist.item>
            <form method="POST" action="/logout">
                @csrf
                <flux:navlist.item icon="arrow-right-start-on-rectangle" type="submit" as="button">
                    Çıkış Yap
                </flux:navlist.item>
            </form>
        </flux:navlist>
    </flux:sidebar>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:spacer />
        <flux:brand :name="$project->name" />
    </flux:header>

    <flux:main>
        @auth
            <div class="flex items-center justify-end mb-4" data-notification-header>
                <livewire:notification.notification-bell />
            </div>
        @endauth

        {{ $slot }}
    </flux:main>
</div>
