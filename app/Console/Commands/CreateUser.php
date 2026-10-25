<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('app:create-user')]
#[Description('Crear una cuenta de acceso para el POS y el panel')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = trim((string) $this->ask('Nombre'));
        $email = Str::lower(trim((string) $this->ask('Correo')));
        $pin = (string) $this->secret('PIN de 4 dígitos');
        $pinConfirmation = (string) $this->secret('Confirma el PIN');

        if (! hash_equals($pin, $pinConfirmation)) {
            $this->error('Los PIN no coinciden.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $pin],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'digits:4'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $pin,
        ]);

        $this->info('Cuenta creada. Ya puedes iniciar sesión.');

        return self::SUCCESS;
    }
}
