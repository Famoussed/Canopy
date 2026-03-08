<?php

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\ProjectService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Proje Ayarları — Canopy')] class extends Component {
    public Project $project;

    public string $projectName = '';

    public string $projectDescription = '';

    // Member management
    public string $newMemberEmail = '';

    public string $newMemberRole = 'member';

    public bool $showAddMember = false;

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->projectName = $project->name;
        $this->projectDescription = $project->description ?? '';
    }

    /** @return array<string, string> */
    public function getListeners(): array
    {
        return [
            "echo-private:project.{$this->project->id},.member.added" => 'refreshMembers',
        ];
    }

    public function refreshMembers(): void
    {
        $this->project->refresh();
    }

    public function saveProject(): void
    {
        $this->validate([
            'projectName' => 'required|string|max:255',
            'projectDescription' => 'nullable|string|max:1000',
        ]);

        app(ProjectService::class)->update($this->project, [
            'name' => $this->projectName,
            'description' => $this->projectDescription,
        ]);

        $this->project->refresh();
        session()->flash('success', 'Proje bilgileri güncellendi.');
    }

    public function addMember(): void
    {
        $this->validate([
            'newMemberEmail' => 'required|email',
            'newMemberRole' => 'required|in:member,moderator',
        ]);

        $user = User::where('email', $this->newMemberEmail)->first();

        if (! $user) {
            $this->addError('newMemberEmail', 'Bu e-posta ile kayıtlı kullanıcı bulunamadı.');

            return;
        }

        // Check if already a member
        $existing = $this->project->memberships()->where('user_id', $user->id)->exists();

        if ($existing) {
            $this->addError('newMemberEmail', 'Bu kullanıcı zaten üye.');

            return;
        }

        try {
            app(MembershipService::class)->add(
                $this->project,
                $user,
                ProjectRole::from($this->newMemberRole),
                auth()->user(),
            );

            $this->reset(['newMemberEmail', 'newMemberRole', 'showAddMember']);
            $this->newMemberRole = 'member';
            $this->project->refresh();
        } catch (\App\Exceptions\DuplicateMemberException) {
            $this->addError('newMemberEmail', 'Bu kullanıcı zaten üye.');
        } catch (\App\Exceptions\MaxMembersExceededException $e) {
            $this->addError('newMemberEmail', $e->getMessage());
        }
    }

    public function changeRole(string $membershipId, string $role): void
    {
        $membership = $this->project->memberships()->findOrFail($membershipId);
        $user = $membership->user;

        app(MembershipService::class)->changeRole(
            $this->project,
            $user,
            ProjectRole::from($role),
        );

        $this->project->refresh();
    }

    public function removeMember(string $membershipId): void
    {
        $membership = $this->project->memberships()->findOrFail($membershipId);
        $user = $membership->user;

        app(MembershipService::class)->remove(
            $this->project,
            $user,
            auth()->user(),
        );

        $this->project->refresh();
    }

    public function deleteProject(): void
    {
        app(ProjectService::class)->delete($this->project);
        $this->redirect('/dashboard', navigate: true);
    }

    #[Computed]
    public function members(): mixed
    {
        return $this->project->memberships()
            ->with('user')
            ->orderByRaw("CASE WHEN role = 'owner' THEN 0 WHEN role = 'moderator' THEN 1 ELSE 2 END")
            ->get();
    }
}

?>

<x-project-layout :project="$project">
    <flux:heading size="xl" class="mb-6">Proje Ayarları</flux:heading>

    @session('success')
        <flux:card class="mb-4 border-green-200 bg-green-50 dark:bg-green-900/20">
            <flux:text class="text-green-600 dark:text-green-400">{{ $value }}</flux:text>
        </flux:card>
    @endsession

    {{-- General Settings --}}
    <flux:card class="mb-6">
        <flux:heading class="mb-4">Genel Bilgiler</flux:heading>
        <form wire:submit="saveProject" class="space-y-4">
            <flux:input wire:model="projectName" label="Proje Adı" required />
            <flux:textarea wire:model="projectDescription" label="Açıklama" rows="3" placeholder="Proje hakkında kısa açıklama..." />
            <flux:button type="submit" variant="primary">Kaydet</flux:button>
        </form>
    </flux:card>

    {{-- Members --}}
    <flux:card class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading>Üyeler ({{ $this->members->count() }})</flux:heading>
            <flux:button variant="outline" size="sm" icon="user-plus" wire:click="$toggle('showAddMember')">Üye Ekle</flux:button>
        </div>

        @if ($showAddMember)
            <form wire:submit="addMember" class="flex items-end gap-3 mb-4 p-3 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                <div class="flex-1">
                    <flux:input wire:model="newMemberEmail" label="E-posta" type="email" placeholder="kullanici@ornek.com" required />
                </div>
                <flux:select wire:model="newMemberRole" label="Rol" class="w-40">
                    <option value="member">Üye</option>
                    <option value="moderator">Moderatör</option>
                </flux:select>
                <flux:button type="submit" variant="primary" size="sm">Ekle</flux:button>
                <flux:button variant="ghost" size="sm" wire:click="$toggle('showAddMember')">İptal</flux:button>
            </form>
        @endif

        <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
            @foreach ($this->members as $membership)
                <div wire:key="member-{{ $membership->id }}" class="flex items-center gap-3 py-3">
                    <flux:avatar size="sm" :name="$membership->user->name" />
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium">{{ $membership->user->name }}</div>
                        <flux:text class="text-xs text-zinc-500">{{ $membership->user->email }}</flux:text>
                    </div>
                    <flux:badge size="sm" :color="match($membership->role) {
                        ProjectRole::Owner => 'amber',
                        ProjectRole::Moderator => 'blue',
                        default => 'zinc',
                    }">
                        {{ $membership->role->label() }}
                    </flux:badge>
                    @if ($membership->role !== ProjectRole::Owner)
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                            <flux:menu>
                                @if ($membership->role === ProjectRole::Member)
                                    <flux:menu.item icon="arrow-up" wire:click="changeRole('{{ $membership->id }}', 'moderator')">Moderatör Yap</flux:menu.item>
                                @else
                                    <flux:menu.item icon="arrow-down" wire:click="changeRole('{{ $membership->id }}', 'member')">Üye Yap</flux:menu.item>
                                @endif
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="removeMember('{{ $membership->id }}')" wire:confirm="Bu üyeyi projeden çıkarmak istediğinize emin misiniz?">Çıkar</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>
            @endforeach
        </div>
    </flux:card>

    {{-- Danger Zone --}}
    <flux:card class="border-red-200 dark:border-red-800">
        <flux:heading class="text-red-600 dark:text-red-400 mb-2">Tehlikeli Bölge</flux:heading>
        <flux:text class="text-sm mb-4">Bu işlemler geri alınamaz.</flux:text>
        <flux:button
            variant="danger"
            icon="trash"
            wire:click="deleteProject"
            wire:confirm="Bu projeyi kalıcı olarak silmek istediğinize emin misiniz? Bu işlem geri alınamaz."
        >
            Projeyi Sil
        </flux:button>
    </flux:card>
</x-project-layout>
