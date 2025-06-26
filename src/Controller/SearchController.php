<?php

namespace App\Controller;

use App\Dto\SearchInput;
use App\Repository\ReadEventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class SearchController
{
    private ReadEventRepository $repository;
    private SerializerInterface $serializer;

    public function __construct(
        ReadEventRepository $repository,
        SerializerInterface  $serializer
    ) {
        $this->repository = $repository;
        $this->serializer = $serializer;
    }

    /**
     * @Route(path="/api/search", name="api_search", methods={"GET"})
     */
    public function searchEvents(Request $request): Response
    {
        $searchInput = $this->serializer->denormalize($request->query->all(), SearchInput::class);

        // TODO: Validate $searchInput here (using ValidatorInterface)
        // Return errors if validation fails

        try {
            $countByType = $this->repository->countByType($searchInput);

            $data = [
                'meta' => [
                    'totalEvents' => $this->repository->countAll($searchInput),
                    'totalPullRequests' => $countByType['pullRequest'] ?? 0,
                    'totalCommits' => $countByType['commit'] ?? 0,
                    'totalComments' => $countByType['comment'] ?? 0,
                ],
                'data' => [
                    'events' => $this->repository->getLatest($searchInput),
                    'stats' => $this->repository->statsByTypePerHour($searchInput)
                ]
            ];

            return new JsonResponse($data);
        } catch (\DomainException $exception) {
            // Log the exception
            return new JsonResponse(
                ['message' => 'Domain error: ' . $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $exception) {
            // Log the exception (eg Sentry, Datadog)

            // Return a JSON response with a generic error message
            return new JsonResponse(
                ['message' => 'Internal server error. Please contact support.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
