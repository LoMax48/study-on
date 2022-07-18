<?php

namespace App\Dto;

use JMS\Serializer\Annotation as Serializer;

class CourseDto
{
    /**
     * @Serializer\Type("string")
     * @var string
     */
    public string $code;

    /**
     * @Serializer\Type("string")
     * @var string
     */
    public string $type;

    /**
     * @Serializer\Type("float")
     * @var float
     */
    public float $price;

    /**
     * @Serializer\Type("string")
     * @var string
     */
    public string $title;
}