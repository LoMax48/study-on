<?php

namespace App\Controller;

use App\Dto\UserDto;
use App\Exception\BillingUnavailableException;
use App\Repository\CourseRepository;
use App\Service\BillingClient;
use App\Service\DecodingJwt;
use JMS\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/profile")
 */
class ProfileController extends AbstractController
{
    private BillingClient $billingClient;
    private DecodingJwt $decodingJwt;
    private SerializerInterface $serializer;

    public function __construct(BillingClient $billingClient, DecodingJwt $decodingJwt, SerializerInterface $serializer)
    {
        $this->billingClient = $billingClient;
        $this->decodingJwt = $decodingJwt;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/", name="app_profile")
     * @IsGranted("ROLE_USER")
     */
    public function index(): Response
    {
        try {
            $response = $this->billingClient->getCurrentUser($this->getUser(), $this->decodingJwt);
        } catch (BillingUnavailableException $exception) {
            throw new \Exception($exception->getMessage());
        }

        $userDto = $this->serializer->deserialize($response, UserDto::class, 'json');

        return $this->render('profile/index.html.twig', [
            'userDto' => $userDto,
        ]);
    }

    /**
     * @Route("/history", name="app_profile_history")
     */
    public function history(CourseRepository $courseRepository): Response
    {
        try {
            $transactionsDto = $this->billingClient->transactionHistory($this->getUser());

            $courses = $courseRepository->findAll();

            $coursesData = [];
            foreach ($transactionsDto as $transactionDto) {
                foreach ($courses as $course) {
                    if ($transactionDto && $transactionDto->course === $course->getCode()) {
                        $coursesData[$transactionDto->course] = [
                            'id' => $course->getId(),
                            'name' => $course->getName(),
                        ];
                    }
                }
            }
        } catch (BillingUnavailableException $exception) {
            throw new \Exception($exception->getMessage());
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

        return $this->render('profile/history.html.twig', [
            'transactions' => $transactionsDto,
            'courses' => $coursesData,
        ]);
    }
}
