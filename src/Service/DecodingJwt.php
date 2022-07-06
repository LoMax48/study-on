<?php

namespace App\Service;

class DecodingJwt
{
    private string $username;
    private array $roles;
    private int $exp;

    public function decode($token): void
    {
        $partsToken = explode('.', $token);
        $payload = json_decode(base64_decode($partsToken[1]), true, 512, JSON_THROW_ON_ERROR);

        $this->username = $payload['username'];
        $this->roles = $payload['roles'];
        $this->exp = $payload['exp'];
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function getExp(): ?int
    {
        return $this->exp;
    }
}
