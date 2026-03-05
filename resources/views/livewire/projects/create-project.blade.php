<?php

use App\Services\ProjectService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Yeni Proje — Canopy')] class extends Component {
    public string $name = '';

    public string $description = '';

    /** @var array<string, string[]> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function create(): void
    {
        $this->validate();

        $project = app(ProjectService::class)->create([
            'name' => $this->name,
            'description' => $this->description,
        ], auth()->user());

        $this->redirect("/projects/{$project->slug}/backlog", navigate: true);
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
                <flux:navlist.item icon="home" href="/dashboard" wire:navigate>
                    Projelerim
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />
    </flux:sidebar>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:spacer />
        <flux:brand name="Canopy" />
    </flux:header>

    <flux:main>
        <div class="max-w-xl mx-auto">
            <flux:breadcrumbs class="mb-4">
                <flux:breadcrumbs.item href="/dashboard" wire:navigate>Projelerim</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Yeni Proje</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Yeni Proje Oluştur</flux:heading>
                    <flux:subheading>Projenizin temel bilgilerini girin</flux:subheading>
                </div>

                <form wire:submit="create" class="space-y-4">
                    <flux:input
                        wire:model="name"
                        label="Proje Adı"
                        placeholder="Proje adını girin"
                        required
                        autofocus
                    />

                    <flux:textarea
                        wire:model="description"
                        label="Açıklama"
                        placeholder="Projenin kısa bir açıklaması (opsiyonel)"
                        rows="3"
                    />

                    <div class="flex items-center gap-3 pt-2">
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="create">Oluştur</span>
                            <span wire:loading wire:target="create">Oluşturuluyor...</span>
                        </flux:button>
                        <flux:button variant="ghost" href="/dashboard" wire:navigate>
                            İptal
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        </div>
    </flux:main>
</div>
