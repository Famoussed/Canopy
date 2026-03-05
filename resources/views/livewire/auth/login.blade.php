<?php

use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.guest')] #[Title('Giriş Yap — Canopy')] class extends Component {
    public string $email = '';

    public string $password = '';

    /** @var array<string, string[]> */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function login(): void
    {
        $this->validate();

        try {
            $user = app(AuthService::class)->login([
                'email' => $this->email,
                'password' => $this->password,
            ]);

            auth()->guard('web')->login($user);

            session()->regenerate();

            $this->redirectIntended('/dashboard', navigate: true);
        } catch (AuthenticationException) {
            $this->addError('email', 'E-posta adresi veya şifre hatalı.');
        }
    }
}

?>

<div>
    <flux:card class="space-y-6">
        <div>
            <flux:heading size="lg">Giriş Yap</flux:heading>
            <flux:subheading>Canopy hesabınıza giriş yapın</flux:subheading>
        </div>

        <form wire:submit="login" class="space-y-4">
            <flux:input
                wire:model="email"
                label="E-posta"
                type="email"
                placeholder="ornek@email.com"
                required
                autofocus
            />

            <flux:input
                wire:model="password"
                label="Şifre"
                type="password"
                placeholder="••••••••"
                required
            />

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="login">Giriş Yap</span>
                <span wire:loading wire:target="login">Giriş yapılıyor...</span>
            </flux:button>
        </form>

        <flux:separator />

        <p class="text-center text-sm text-zinc-500">
            Hesabın yok mu?
            <flux:link href="/register" wire:navigate class="font-medium">Kayıt Ol</flux:link>
        </p>
    </flux:card>
</div>
