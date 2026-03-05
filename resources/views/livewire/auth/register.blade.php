<?php

use App\Services\AuthService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.guest')] #[Title('Kayıt Ol — Canopy')] class extends Component {
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** @var array<string, string[]> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function register(): void
    {
        $this->validate();

        $user = app(AuthService::class)->register([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        auth()->guard('web')->login($user);

        session()->regenerate();

        $this->redirect('/dashboard', navigate: true);
    }
}

?>

<div>
    <flux:card class="space-y-6">
        <div>
            <flux:heading size="lg">Kayıt Ol</flux:heading>
            <flux:subheading>Yeni bir Canopy hesabı oluşturun</flux:subheading>
        </div>

        <form wire:submit="register" class="space-y-4">
            <flux:input
                wire:model="name"
                label="Ad Soyad"
                type="text"
                placeholder="Adınız Soyadınız"
                required
                autofocus
            />

            <flux:input
                wire:model="email"
                label="E-posta"
                type="email"
                placeholder="ornek@email.com"
                required
            />

            <flux:input
                wire:model="password"
                label="Şifre"
                type="password"
                placeholder="••••••••"
                required
            />

            <flux:input
                wire:model="password_confirmation"
                label="Şifre Tekrar"
                type="password"
                placeholder="••••••••"
                required
            />

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="register">Kayıt Ol</span>
                <span wire:loading wire:target="register">Hesap oluşturuluyor...</span>
            </flux:button>
        </form>

        <flux:separator />

        <p class="text-center text-sm text-zinc-500">
            Zaten hesabın var mı?
            <flux:link href="/login" wire:navigate class="font-medium">Giriş Yap</flux:link>
        </p>
    </flux:card>
</div>
