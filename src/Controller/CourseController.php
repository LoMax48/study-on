<?php

namespace App\Controller;

use App\Dto\CourseDto;
use App\Entity\Course;
use App\Exception\BillingUnavailableException;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Repository\LessonRepository;
use App\Service\BillingClient;
use App\Service\DecodingJwt;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Route("/courses")
 */
class CourseController extends AbstractController
{
    /**
     * @Route("/", name="app_course_index", methods={"GET"})
     */
    public function index(
        CourseRepository $courseRepository,
        BillingClient $billingClient,
        DecodingJwt $decodingJwt
    ): Response {
        try {
            $coursesDto = $billingClient->getAllCourses();
            $coursesInfoBilling = [];
            foreach ($coursesDto as $courseDto) {
                $coursesInfoBilling[$courseDto->code] = [
                    'course' => $courseDto,
                    'transaction' => null,
                ];
            }

            if (!$this->getUser()) {
                return $this->render('course/index.html.twig', [
                    'courses' => $courseRepository->findAll(),
                    'coursesInfoBilling' => $coursesInfoBilling,
                ]);
            }

            $transactionsDto = $billingClient->transactionHistory(
                $this->getUser(),
                'type=payment&skip_expired=true'
            );
            $coursesInfoBilling = [];
            foreach ($coursesDto as $courseDto) {
                foreach ($transactionsDto as $transactionDto) {
                    if ($transactionDto->course === $courseDto->code) {
                        $coursesInfoBilling[$courseDto->code] = [
                            'course' => $courseDto,
                            'transaction' => $transactionDto,
                        ];

                        break;
                    }

                    $coursesInfoBilling[$courseDto->code] = [
                        'course' => $courseDto,
                        'transaction' => null,
                    ];
                }
            }
            $response = $billingClient->getCurrentUser($this->getUser(), $decodingJwt);
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            $balance = $data['balance'];

            return $this->render('course/index.html.twig', [
                'courses' => $courseRepository->findAll(),
                'coursesInfoBilling' => $coursesInfoBilling,
                'balance' => $balance,
            ]);
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * @Route("/pay", name="app_course_pay", methods={"GET"})
     * @IsGranted("ROLE_USER", message="Сначала авторизуйтесь или зарегистрируйтесь")
     */
    public function pay(Request $request, BillingClient $billingClient): Response
    {
        $referer = $request->headers->get('referer');

        $courseCode = $request->get('course_code');
        try {
            $payDto = $billingClient->paymentCourse($this->getUser(), $courseCode);
            $this->addFlash('success', 'Оплата прошла успешно! Наслаждайтесь курсом!');
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

        return $this->redirect($referer);
    }

    /**
     * @Route("/new", name="app_course_new", methods={"GET", "POST"})
     * @IsGranted("ROLE_SUPER_ADMIN", statusCode=403, message="Доступ только для администратора.")
     */
    public function new(Request $request, CourseRepository $courseRepository, BillingClient $billingClient): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $courseDto = new CourseDto();

                $courseDto->title = $form->get('name')->getData();
                $courseDto->code = $form->get('code')->getData();
                $courseDto->type = $form->get('type')->getData();
                if ($courseDto->type === 'free') {
                    $courseDto->price = 0;
                } else {
                    $courseDto->price = $form->get('price')->getData();
                }

                $response = $billingClient->newCourse($this->getUser(), $courseDto);

                $courseRepository->add($course);
                return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $exception) {
                return $this->render('course/new.html.twig', [
                    'course' => $course,
                    'form' => $form->createView(),
                    'errors' => $exception->getMessage(),
                ]);
            }
        }

        return $this->renderForm('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    /**
     * @Route("/{id}", name="app_course_show", methods={"GET"})
     */
    public function show(Course $course, LessonRepository $lessonRepository, BillingClient $billingClient): Response
    {
        try {
            if ($this->getUser() && in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles(), true)) {
                $lessons = $lessonRepository->findByCourse($course);

                return $this->render('course/show.html.twig', [
                    'course' => $course,
                    'lessons' => $lessons,
                ]);
            }

            $courseDto = $billingClient->getCourse($course->getCode());
            if ($courseDto->type === 'free') {
                $lessons = $lessonRepository->findByCourse($course);

                return $this->render('course/show.html.twig', [
                    'course' => $course,
                    'lessons' => $lessons,
                ]);
            }

            if ($this->getUser()) {
                throw new AccessDeniedException('Требуется авторизация.');
            }

            $transactionDto = $billingClient->transactionHistory(
                $this->getUser(),
                'type=payment&course_code=' . $course->getCode() . '&skip_expired=true'
            );

            if ($transactionDto !== []) {
                $lessons = $lessonRepository->findByCourse($course);

                return $this->render('course/show.html.twig', [
                    'course' => $course,
                    'lessons' => $lessons,
                ]);
            }

            throw new AccessDeniedException('Доступ запрещён.');
        } catch (AccessDeniedException $exception) {
            throw new \Exception($exception->getMessage());
        } catch (BillingUnavailableException $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * @Route("/{id}/edit", name="app_course_edit", methods={"GET", "POST"})
     * @IsGranted("ROLE_SUPER_ADMIN", statusCode=403, message="Доступ только для администратора.")
     */
    public function edit(
        Request $request,
        Course $course,
        CourseRepository $courseRepository,
        BillingClient $billingClient
    ): Response {
        $courseCode = $course->getCode();
        try {
            $billingCourse = $billingClient->getCourse($courseCode);
        } catch (\Exception $exception) {
            throw new BillingUnavailableException($exception->getMessage());
        }

        $form = $this->createForm(CourseType::class, $course, [
            'price' => $billingCourse->price,
            'type' => $billingCourse->type,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $courseDto = new CourseDto();

                $courseDto->title = $form->get('name')->getData();
                $courseDto->code = $form->get('code')->getData();
                $courseDto->type = $form->get('type')->getData();

                if ($courseDto->type === 'free') {
                    $courseDto->price = 0;
                } else {
                    $courseDto->price = $form->get('price')->getData();
                }

                $response = $billingClient->editCourse($this->getUser(), $courseCode, $courseDto);
                $courseRepository->add($course);

                return $this->redirectToRoute(
                    'app_course_show',
                    ['id' => $course->getId()],
                    Response::HTTP_SEE_OTHER
                );
            } catch (\Exception $exception) {
                return $this->render('course/edit.html.twig', [
                    'course' => $course,
                    'form' => $form->createView(),
                    'errors' => $exception->getMessage(),
                ]);
            }
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="app_course_delete", methods={"POST"})
     * @IsGranted("ROLE_SUPER_ADMIN", statusCode=403, message="Доступ только для администратора.")
     */
    public function delete(Request $request, Course $course, CourseRepository $courseRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->request->get('_token'))) {
            $courseRepository->remove($course);
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }
}
