<?php

namespace App\Controller;

use App\Dto\EventInput;
use App\Repository\ReadEventRepository;
use App\Repository\WriteEventRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventController
{
    private WriteEventRepository $writeEventRepository;
    private ReadEventRepository $readEventRepository;
    private SerializerInterface $serializer;

    public function __construct(
        WriteEventRepository $writeEventRepository,
        ReadEventRepository $readEventRepository,
        SerializerInterface $serializer
    ) {
        $this->writeEventRepository = $writeEventRepository;
        $this->readEventRepository = $readEventRepository;
        $this->serializer = $serializer;
    }

    /**
     * @Route(path="/api/event/{id}/update", name="api_event_update", methods={"PUT"})
     */
    public function update(Request $request, int $id, ValidatorInterface $validator): Response
    {
        $eventInput = $this->serializer->deserialize($request->getContent(), EventInput::class, 'json');

        $errors = $validator->validate($eventInput);

        if (\count($errors) > 0) {
            // Return all validation errors
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(
                ['errors' => $errorMessages],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->readEventRepository->exist($id)) {
            return new JsonResponse(
                ['message' => sprintf('Event identified by %d not found !', $id)],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $this->writeEventRepository->update($eventInput, $id);
        } catch (\DomainException $exception) {
            // Log the exception
            return new JsonResponse(
                ['message' => 'Domain error: ' . $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $exception) {
            // Log the exception
            // Return a JSON response with a generic error message
            return new JsonResponse(
                ['message' => 'Internal server error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
