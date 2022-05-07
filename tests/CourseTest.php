<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;

class CourseTest extends AbstractTest
{
    private $indexPath = '/courses/';

    protected function getFixtures(): array
    {
        return [
            AppFixtures::class
        ];
    }

    public function urlProviderSuccessful()
    {
        yield ['/'];
        yield [$this->indexPath];
        yield [$this->indexPath . 'new'];
    }

    public function urlProviderNotFound()
    {
        yield ['/cources'];
        yield [$this->indexPath . '/30'];
    }

    /**
     * @dataProvider urlProviderSuccessful
     */
    public function testMainPagesGetResponse($url): void
    {
        $client = self::getClient();
        $client->request('GET', $url);
        $this->assertResponseOk();
    }

    /**
     * @dataProvider urlProviderNotFound
     */
    public function testNotFoundPagesGetResponse($url): void
    {
        $client = self::getClient();
        $client->request('GET', $url);
        $this->assertResponseNotFound();
    }

    public function testCoursesCount(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->indexPath);

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();
        self::assertNotEmpty($courses);

        $actualCoursesCount = count($courses);

        self::assertCount($actualCoursesCount, $crawler->filter('.card'));
    }

    public function testPostResponse(): void
    {
        $client = self::getClient();

        $client->request('POST', $this->indexPath . 'new');
        $this->assertResponseOk();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();

        foreach ($courses as $course) {
            $client->request('POST', $this->indexPath . $course->getId() . '/edit');
            $this->assertResponseOk();

            $client->request('POST', '/lessons/' . $course->getId() . '/new');
            $this->assertResponseOk();
        }
    }

    public function testLessonsCount(): void
    {
        $client = self::getClient();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();
        self::assertNotEmpty($courses);

        foreach ($courses as $course) {
            $crawler = $client->request('GET', $this->indexPath . $course->getId());
            $this->assertResponseOk();

            $actualLessonsCount = count($course->getLessons());
            self::assertCount($actualLessonsCount, $crawler->filter('.list-group-item'));
        }
    }

    public function testValidDataCourseAdd(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', $this->indexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-success')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Создать');

        $form = $submitButton->form([
           'course[code]' => 'NEW',
           'course[name]' => 'Новый курс',
           'course[description]' => 'Описание курса',
        ]);

        $client->submit($form);
        self::assertTrue($client->getResponse()->isRedirect($this->indexPath));

        $crawler = $client->followRedirect();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();
        $actualCoursesCount = count($courses);

        self::assertCount($actualCoursesCount, $crawler->filter('.card'));
    }

    public function testBlankDataCourseAdd(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', $this->indexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-success')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Создать');

        $form = $submitButton->form([
            'course[code]' => '',
            'course[name]' => 'Новый курс',
            'course[description]' => 'Описание курса',
        ]);

        $client->submit($form);
        self::assertFalse($client->getResponse()->isRedirect($this->indexPath));

        $form = $submitButton->form([
            'course[code]' => 'NEW',
            'course[name]' => '',
            'course[description]' => 'Описание курса',
        ]);

        $client->submit($form);
        self::assertFalse($client->getResponse()->isRedirect($this->indexPath));

        $form = $submitButton->form([
            'course[code]' => 'NEW',
            'course[name]' => 'Новый курс',
            'course[description]' => '',
        ]);

        $client->submit($form);
        self::assertTrue($client->getResponse()->isRedirect($this->indexPath));
    }

    public function testInvalidLengthDataCourseAdd(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', $this->indexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-success')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Создать');

        $form = $submitButton->form([
            'course[code]' => 'QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP
            QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIO
            PQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUI
            OPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYU
            IOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP',
            'course[name]' => 'Новый курс',
            'course[description]' => 'Описание курса',
        ]);

        $crawler = $client->submit($form);
        $error = $crawler->filter('.form-error-message');
        self::assertEquals('Значение слишком длинное. Должно быть равно 255 символам или меньше.', $error->text());

        $form = $submitButton->form([
            'course[code]' => 'NEW',
            'course[name]' => 'QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP
            QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIO
            PQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUI
            OPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYU
            IOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP',
            'course[description]' => 'Описание курса',
        ]);

        $crawler = $client->submit($form);
        $error = $crawler->filter('.form-error-message');
        self::assertEquals('Значение слишком длинное. Должно быть равно 255 символам или меньше.', $error->text());

        $form = $submitButton->form([
            'course[code]' => 'NEW',
            'course[name]' => 'Новый курс',
            'course[description]' => 'QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP
            QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIO
            PQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUI
            OPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYU
            IOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP
            QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIO
            PQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUI
            OPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYU
            IOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP
            QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIO
            PQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUI
            OPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYU
            IOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP
            QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIO
            PQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUI
            OPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYU
            IOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP
            QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIO
            PQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUI
            OPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYU
            IOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP
            QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIO
            PQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUI
            OPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYU
            IOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP
            QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIO
            PQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUI
            OPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYU
            IOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP
            QWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIO
            PQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUI
            OPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYU
            IOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOPQWERTYUIOP',
        ]);

        $crawler = $client->submit($form);
        $error = $crawler->filter('.form-error-message');
        self::assertEquals('Значение слишком длинное. Должно быть равно 1000 символам или меньше.', $error->text());
    }

    public function testCourseDelete(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', $this->indexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $client->submitForm('course-delete');
        self::assertTrue($client->getResponse()->isRedirect($this->indexPath));

        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();
        $actualCoursesCount = count($courses);

        self::assertCount($actualCoursesCount, $crawler->filter('.card'));
    }

    public function testCourseEdit(): void
    {
        $client = self::getClient();

        $crawler = $client->request('GET', $this->indexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-primary')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('Сохранить');
        $form = $submitButton->form();
        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['code' => $form['course[code]']->getValue()]);

        $form['course[code]'] = 'EDIT';
        $form['course[name]'] = 'Изменённый курс';
        $form['course[description]'] = 'Описание курса';
        $client->submit($form);

        self::assertTrue($client->getResponse()->isRedirect($this->indexPath . $course->getId()));
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        $courseName = $crawler->filter('h1')->text();
        self::assertEquals('Изменённый курс', $courseName);

        $courseDescription = $crawler->filter('h4')->text();
        self::assertEquals('Описание курса', $courseDescription);
    }
}
