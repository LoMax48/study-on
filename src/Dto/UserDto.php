<?php

namespace App\Dto;

use JMS\Serializer\Annotation as Serializer;

class UserDto
{
    /**
     * @var string
     * @Serializer\Type("string")
     */
    public string $username;

    /**
     * @var string
     * @Serializer\Type("string")
     */
    public string $password;

    /**
     * @var array
     * @Serializer\Type("array")
     */
    public array $roles;

    /**
     * @var float
     * @Serializer\Type("float")
     */
    public float $balance;

    /**
     * @var string
     * @Serializer\Type("string")
     */
    public string $token;
}
