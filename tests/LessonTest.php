<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;

class LessonTest extends AbstractTest
{
    private $coursesIndexPath = '/courses/';
    private $lessonsIndexPath = '/lessons/';

    protected function getFixtures(): array
    {
        return [
            AppFixtures::class
        ];
    }

    public function testLessonPagesResponseIsSuccessful(): void
    {
        $client = self::getClient();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();
        foreach ($courses as $course) {
            foreach ($course->getLessons() as $lesson) {
                $client->request('GET', $this->lessonsIndexPath . $lesson->getId());
                $this->assertResponseOk();

                $client->request('GET', $this->lessonsIndexPath . $lesson->getId() . '/edit');
                $this->assertResponseOk();

                $client->request('POST', $this->lessonsIndexPath . $lesson->getId() . '/edit');
                $this->assertResponseOk();
            }
        }
    }

    public function testValidDataLessonAdd(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', $this->coursesIndexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-success')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Создать');
        $form = $submitButton->form([
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => 'Контент урока',
            'lesson[number]' => 1000,
        ]);

        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['id' => $form['lesson[course]']->getValue()]);

        $client->submit($form);
        self::assertTrue($client->getResponse()->isRedirect($this->coursesIndexPath . $course->getId()));
        $crawler = $client->followRedirect();

        $lessonLink = $crawler->filter('.list-group-item > a')->last()->link();
        $client->click($lessonLink);
        $this->assertResponseOk();
    }

    public function testInvalidDataLessonAdd(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', $this->coursesIndexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-success')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitForm = $crawler->selectButton('Создать');
        $form = $submitForm->form([
            'lesson[name]' => 'qwertyuiopqwertyuiopqwertyuiopq
            wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopq
            wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopq
            wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopq
            wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopq
            wertyuiopqwertyuiopqwertyuiopqwertyuiop',
            'lesson[content]' => 'Контент',
            'lesson[number]' => 1000,
        ]);

        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.form-error-message')->first();
        self::assertEquals('Значение слишком длинное. Должно быть равно 255 символам или меньше.', $error->text());

        $submitButton = $crawler->selectButton('Создать');
        $form = $submitButton->form([
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => 'Контент',
            'lesson[number]' => 1000000,
        ]);

        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.form-error-message')->first();
        self::assertEquals('Значение должно быть между 1 и 10000.', $error->text());
    }

    public function testBlankDataLessonAdd(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', $this->coursesIndexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-success')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitForm = $crawler->selectButton('Создать');
        $form = $submitForm->form([
            'lesson[name]' => '',
            'lesson[content]' => 'Контент',
            'lesson[number]' => 1000,
        ]);

        $client->submit($form);
        self::assertFalse($client->getResponse()->isRedirect());

        $submitForm = $crawler->selectButton('Создать');
        $form = $submitForm->form([
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => '',
            'lesson[number]' => 1000,
        ]);

        $client->submit($form);
        self::assertFalse($client->getResponse()->isRedirect());

        $submitForm = $crawler->selectButton('Создать');
        $form = $submitForm->form([
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => 'Контент',
            'lesson[number]' => '',
        ]);

        $client->submit($form);
        self::assertFalse($client->getResponse()->isRedirect());
    }

    public function testLessonsDelete(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', $this->coursesIndexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $lessonsCount = $crawler->filter('.list-group-item > a')->count();

        $lessonLink = $crawler->filter('.list-group-item > a')->first()->link();
        $crawler = $client->click($lessonLink);
        $this->assertResponseOk();

        $client->submitForm('lesson-delete');
        self::assertTrue($client->getResponse()->isRedirect());
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        self::assertCount($lessonsCount - 1, $crawler->filter('.list-group-item'));
    }

    public function testLessonsEdit(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', $this->coursesIndexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $lessonLink = $crawler->filter('.list-group-item > a')->first()->link();
        $crawler = $client->click($lessonLink);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-primary')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form();
        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['id' => $form['lesson[course]']->getValue()]);

        $form['lesson[name]'] = 'Изменённый урок';
        $form['lesson[content]'] = 'Контент урока';
        $form['lesson[number]'] = 100;
        $client->submit($form);

        self::assertTrue($client->getResponse()->isRedirect($this->coursesIndexPath . $course->getId()));
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        $lessonLink = $crawler->filter('.list-group-item > a')->last()->link();
        $crawler = $client->click($lessonLink);
        $this->assertResponseOk();

        $lessonName = $crawler->filter('h1')->text();
        self::assertEquals('Изменённый урок', $lessonName);

        $courseName = $crawler->filter('h3')->text();
        self::assertEquals('Курс ' . $course->getName(), $courseName);

        $courseDescription = $crawler->filter('h5')->text();
        self::assertEquals('Контент урока', $courseDescription);
    }
}
