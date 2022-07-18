<?php

namespace App\Dto;

use JMS\Serializer\Annotation as Serializer;

class PayDto
{
    /**
     * @Serializer\Type("bool")
     * @var bool
     */
    public bool $success;

    /**
     * @Serializer\Type("string")
     * @var string
     */
    public string $courseType;

    /**
     * @Serializer\Type("string")
     * @var string
     */
    public string $expiresTime;
}