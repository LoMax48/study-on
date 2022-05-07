<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Tests\AbstractTest;

class LessonTest extends AbstractTest
{
    private $startingPathCourse = '/courses';
    private $startingPathLesson = '/lessons';

    protected function getFixtures(): array
    {
        return [
            AppFixtures::class
        ];
    }
}
