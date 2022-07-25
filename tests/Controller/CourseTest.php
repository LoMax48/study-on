<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Service\BillingClient;
use App\Service\DecodingJwt;
use App\Tests\AbstractTest;
use App\Tests\Authorization\Auth;
use App\Tests\Mock\BillingClientMock;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\DomCrawler\Crawler;

class CourseTest extends AbstractTest
{
    private string $indexPath = '/courses/';
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    protected function getFixtures(): array
    {
        return [AppFixtures::class];
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

    private function getBillingClient(): void
    {
        self::getClient()->disableReboot();

        self::getClient()->getContainer()->set(
            BillingClient::class,
            new BillingClientMock(new DecodingJwt(), $this->serializer)
        );
    }

    /**
     * @dataProvider urlProviderSuccessful
     */
    public function testMainPagesAdminAccess($url): void
    {
        $crawler = $this->adminAuth();
        $client = self::getClient();
        $client->request('GET', $url);
        $this->assertResponseOk();

        $entityManager = self::getEntityManager();
        $courses = $entityManager->getRepository(Course::class)->findAll();
        $this->assertNotEmpty($courses);

        foreach ($courses as $course) {
            self::getClient()->request('GET', $this->indexPath . $course->getId());
            $this->assertResponseOk();

            self::getClient()->request('GET', $this->indexPath . $course->getId() . '/edit');
            $this->assertResponseOk();

            self::getClient()->request('POST', $this->indexPath . 'new');
            $this->assertResponseOk();

            self::getClient()->request('POST', $this->indexPath . $course->getId() . '/edit');
            $this->assertResponseOk();
        }

        $client = self::getClient();
        $url = $this->indexPath . '228';
        $client->request('GET', $url);
        $this->assertResponseNotFound();
    }

    /**
     * @dataProvider urlProviderSuccessful
     */
    public function testMainPagesUserAccess($url): void
    {
        $crawler = $this->userAuth();

        $client = self::getClient();

        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();
        self::assertNotEmpty($courses);

        foreach ($courses as $course) {
            self::getClient()->request('GET', $this->indexPath . $course->getId() . '/edit');
            self::assertResponseStatusCodeSame(403);

            self::getClient()->request('POST', $this->indexPath . 'new');
            self::assertResponseStatusCodeSame(403);

            self::getClient()->request('POST', $this->indexPath . $course->getId() . '/edit');
            self::assertResponseStatusCodeSame(403);
        }
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

    public function testLessonsCount(): void
    {
        $crawler = $this->adminAuth();
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
        $crawler = $this->adminAuth();

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
           'course[type]' => 'rent',
           'course[price]' => 100,
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
        $crawler = $this->adminAuth();

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
            'course[type]' => 'rent',
            'course[price]' => 100,
            'course[description]' => 'Описание курса',
        ]);

        $client->submit($form);
        self::assertFalse($client->getResponse()->isRedirect($this->indexPath));

        $form = $submitButton->form([
            'course[code]' => 'BLANK',
            'course[name]' => '',
            'course[type]' => 'rent',
            'course[price]' => 100,
            'course[description]' => 'Описание курса',
        ]);

        $client->submit($form);
        self::assertFalse($client->getResponse()->isRedirect($this->indexPath));

        $form = $submitButton->form([
            'course[code]' => 'BLANK',
            'course[name]' => 'Новый курс',
            'course[type]' => 'rent',
            'course[price]' => 100,
            'course[description]' => '',
        ]);

        $client->submit($form);
        self::assertTrue($client->getResponse()->isRedirect($this->indexPath));
    }

    public function testInvalidLengthDataCourseAdd(): void
    {
        $crawler = $this->adminAuth();

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
        self::assertEquals('Описание превышает 1000 символов.', $error->text());
    }

    public function testCourseDelete(): void
    {
        $crawler = $this->adminAuth();

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
        $crawler = $this->adminAuth();

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

    private function adminAuth(): Crawler
    {
        $auth = new Auth();
        $auth->setSerializer($this->serializer);

        $data = [
            'username' => 'admin@mail.ru',
            'password' => 'admin123'
        ];

        $requestData = $this->serializer->serialize($data, 'json');

        return $auth->auth($requestData);
    }

    private function userAuth(): Crawler
    {
        $auth = new Auth();
        $auth->setSerializer($this->serializer);

        $data = [
            'username' => 'user@mail.ru',
            'password' => 'user123',
        ];

        $requestData = $this->serializer->serialize($data, 'json');

        return $auth->auth($requestData);
    }
}
