<?php

namespace Webkul\Website\Filament\Customer\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Webkul\Security\Models\User;

class Login extends Page
{
    use InteractsWithFormActions;
    use InteractsWithForms;
    use WithRateLimiting;

    protected string $view = 'website::filament.customer.pages.auth.login';

    public ?array $data = [];

    public function mount(): void
    {
        if (Auth::guard('web')->check() || Filament::auth()->check()) {
            redirect()->to('/admin');
            return;
        }

        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        // 1. Consulta no Banco de Dados pelo Usuário e Tenant
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        $tenant = DB::table('zeny_erp_tenants')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orWhere('admin_user_id', $user?->id ?? 0)
            ->first();

        // 2. CASO NÃO TENHA CONTA E NÃO PAGOU:
        // O usuário não existe em 'users' e não possui registro em 'zeny_erp_tenants'
        if (! $user && ! $tenant) {
            Notification::make()
                ->title('Conta não encontrada')
                ->body('Não encontramos nenhuma conta com o e-mail informado. Convidamos você a conhecer nossos planos e assinar o ZenyDesk ERP para liberar seu acesso instantâneo!')
                ->warning()
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('assinar')
                        ->button()
                        ->label('Conhecer Planos e Assinar')
                        ->url(url('/assinar')),
                ])
                ->send();

            return null;
        }

        // 3. CASO TENHA CADASTRO, MAS O PAGAMENTO ESTEJA PENDENTE OU SUSPENSO:
        if ($tenant && in_array($tenant->status, ['pendente', 'cancelado', 'suspenso'], true)) {
            $statusLabel = $tenant->status === 'pendente' ? 'aguardando confirmação do pagamento' : 'com pagamento pendente';
            Notification::make()
                ->title('Assinatura Pendente')
                ->body("Sua assinatura para o ambiente [{$tenant->subdominio}.erp.zenydesk.com] está {$statusLabel}. Convidamos você a concluir o pagamento para ativar seu acesso imediato.")
                ->warning()
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('assinar')
                        ->button()
                        ->label('Concluir Assinatura')
                        ->url(url('/assinar?sub='.$tenant->subdominio)),
                ])
                ->send();

            return null;
        }

        // 4. CASO O USUÁRIO EXISTA NO BANCO DE DADOS:
        if ($user) {
            // Validação da senha
            if (! Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'data.password' => 'A senha informada está incorreta. Verifique suas credenciais e tente novamente.',
                ]);
            }

            // Verifica se o usuário está ativo
            if (! $user->is_active) {
                Notification::make()
                    ->title('Usuário Desativado')
                    ->body('Sua conta de usuário encontra-se desativada. Entre em contato com o administrador da sua empresa.')
                    ->danger()
                    ->send();

                return null;
            }

            // Autentica o usuário no guard 'web'
            Auth::guard('web')->login($user, $data['remember'] ?? false);
            session()->regenerate();

            // Mensagem calorosa de boas-vindas de volta
            Notification::make()
                ->title("Bem-vindo de volta, {$user->name}!")
                ->body('Identificamos sua conta com sucesso no banco de dados. Acessando seu ambiente ZenyDesk ERP...')
                ->success()
                ->send();

            // Determina a URL do ambiente do cliente
            if ($tenant && ! empty($tenant->subdominio) && $tenant->subdominio !== 'erp') {
                $redirectUrl = "https://{$tenant->subdominio}.erp.zenydesk.com/admin";
            } elseif ($user->default_company_id) {
                $company = DB::table('companies')->where('id', $user->default_company_id)->first();
                if ($company && ! empty($company->subdominio) && $company->subdominio !== 'erp') {
                    $redirectUrl = "https://{$company->subdominio}.erp.zenydesk.com/admin";
                } else {
                    $redirectUrl = url('/admin');
                }
            } else {
                $redirectUrl = url('/admin');
            }

            redirect()->away($redirectUrl);

            return null;
        }

        Notification::make()
            ->title('Ambiente em Provisionamento')
            ->body('Seu ambiente está sendo ativado. Por favor, acesse a página de assinatura ou aguarde alguns instantes.')
            ->info()
            ->send();

        return null;
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        return Notification::make()
            ->title(__('website::filament/customer/pages/auth/login.notifications.throttled.title', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => $exception->minutesUntilAvailable,
            ]))
            ->body(array_key_exists('body', __('website::filament/customer/pages/auth/login.notifications.throttled') ?: []) ? __('website::filament/customer/pages/auth/login.notifications.throttled.body', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => $exception->minutesUntilAvailable,
            ]) : null)
            ->danger();
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeSchema()
                    ->components([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getRememberFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('website::filament/customer/pages/auth/login.form.email.label'))
            ->email()
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('website::filament/customer/pages/auth/login.form.password.label'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->extraInputAttributes(['tabindex' => 2]);
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label(__('website::filament/customer/pages/auth/login.form.remember.label'));
    }

    public function registerAction(): Action
    {
        return Action::make('register')
            ->link()
            ->label('Ainda não é assinante? Conheça os planos e assine já')
            ->url(url('/assinar'));
    }

    public function getTitle(): string|Htmlable
    {
        return __('website::filament/customer/pages/auth/login.title');
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
        ];
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label(__('website::filament/customer/pages/auth/login.form.actions.authenticate.label'))
            ->submit('authenticate');
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }
}
