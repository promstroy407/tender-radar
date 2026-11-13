<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

/**
 * Создаёт администратора. Пароль вводится в терминале и нигде не сохраняется в открытом виде.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin {email} {name}';

    protected $description = 'Создать администратора Тендер-Радара';

    public function handle(ValidationFactory $validation): int
    {
        $password = (string) $this->secret('Пароль (не меньше 12 символов)');

        $validator = $validation->make(
            [
                'email' => $this->argument('email'),
                'name' => $this->argument('name'),
                'password' => $password,
            ],
            [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:12', 'max:255'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        /** @var array{email: string, name: string, password: string} $data */
        $data = $validator->validated();

        $user = new User;
        $user->fill($data);
        $user->is_admin = true;
        $user->save();

        $this->info('Администратор '.$user->email.' создан.');

        return self::SUCCESS;
    }
}
