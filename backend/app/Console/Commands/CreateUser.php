<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'app:create-user {email : Correo con el que va a iniciar sesión} {--name= : Nombre para mostrar}';

    protected $description = 'Crea un usuario con acceso al panel (no hay registro público)';

    public function handle(): int
    {
        $email = Str::lower($this->argument('email'));
        $name = $this->option('name') ?: $this->ask('Nombre');

        $password = $this->secret('Contraseña (mínimo 12 caracteres, con mayúsculas, minúsculas, números y símbolos)');
        $confirmation = $this->secret('Repetí la contraseña');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        AuditLog::record('user.created', $user, ['via' => 'artisan']);

        $this->info("Usuario {$user->email} creado. Ya puede iniciar sesión.");

        return self::SUCCESS;
    }
}
