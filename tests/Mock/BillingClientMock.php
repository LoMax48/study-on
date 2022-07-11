<?php

namespace App\Tests\Mock;

use App\Exception\BillingUnavailableException;
use App\Exception\ClientException;
use App\Dto\UserDto;
use App\Security\User;
use App\Service\BillingClient;

class BillingClientMock extends BillingClient
{
    public function auth(string $request): User
    {
        $data = json_decode($request, true);
        $user = new User();

        if ($data['username'] === 'user@mail.ru' && $data['password'] === 'user123') {
            $user->setEmail('user@mail.ru');
            $user->setApiToken($this->generateToken('ROLE_USER', 'user@mail.ru'));
            $user->setRoles(['ROLE_USER']);

            return $user;
        }

        if ($data['username'] === 'admin@mail.ru' && $data['password'] === 'admin123') {
            $user->setEmail('admin@mail.ru');
            $user->setApiToken($this->generateToken('ROLE_SUPER_ADMIN', 'admin@mail.ru'));
            $user->setRoles(['ROLE_SUPER_ADMIN']);

            return $user;
        }

        throw new BillingUnavailableException('Проверьте правильность введёного логина и пароля');
    }

    public function register(UserDto $dataUser): UserDto
    {
        if ($dataUser->username === 'user@mail.ru' | $dataUser->username === 'admin@mail.ru') {
            throw new ClientException('Данный пользователь уже существует.');
        }

        $token = $this->generateToken('ROLE_USER', $dataUser->username);
        $dataUser->token = $token;
        $dataUser->balance = 0;
        $dataUser->roles = ['ROLE_USER'];

        return $dataUser;
    }

    private function generateToken(string $role, string $username): string
    {
        $roles = null;

        if ($role === 'ROLE_USER') {
            $roles = ['ROLE_USER'];
        } elseif ($role === 'ROLE_SUPER_ADMIN') {
            $roles = ['ROLE_SUPER_ADMIN', 'ROLE_USER'];
        }

        $data = [
            'username' => $username,
            'roles' => $roles,
            'exp' => (new \DateTime('+ 1 hour'))->getTimestamp(),
        ];

        $query = base64_encode(json_encode($data));

        return 'header.' . $query . '.signature';
    }
}
