<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Filesystem\Filesystem;

class PresentationController extends AbstractController
{
    private string $dataDir = __DIR__ . '/../../var/data';

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('presentation/index.html.twig');
    }

    private function getJsonFilePath(): string
    {
        return $this->dataDir . '/presentations.json';
    }

    private function loadPresentations(): array
    {
        $fs = new Filesystem();
        if (!$fs->exists($this->getJsonFilePath())) {
            return [];
        }

        $data = json_decode(file_get_contents($this->getJsonFilePath()), true);
        return $data ?: [];
    }

    private function savePresentations(array $data): void
    {
        $fs = new Filesystem();
        if (!$fs->exists($this->dataDir)) {
            $fs->mkdir($this->dataDir);
        }
        file_put_contents($this->getJsonFilePath(), json_encode($data, JSON_PRETTY_PRINT));
    }

    #[Route('/presentation/list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->loadPresentations());
    }

    #[Route('/presentation/create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $nickname = $request->request->get('nickname');
        $presentations = $this->loadPresentations();

        $id = uniqid();
        $presentations[$id] = [
            'id' => $id,
            'title' => 'Untitled Presentation',
            'created_by' => $nickname,
            'users' => [
                [
                    'name' => $nickname,
                    'role' => 'Creator'
                ]
            ],
            'slides' => [
                [ 'blocks' => [] ]
            ]
        ];

        $this->savePresentations($presentations);

        return $this->redirectToRoute('presentation_show', ['id' => $id]);
    }

    #[Route('/presentation/{id}', name: 'presentation_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        return $this->render('presentation/slide.html.twig', [
            'presentation_id' => $id
        ]);
    }

    #[Route('/presentation/{id}/data', methods: ['GET'])]
    public function data(string $id): JsonResponse
    {
        $presentations = $this->loadPresentations();

        if (!isset($presentations[$id])) {
            return $this->json(['error' => 'Presentation not found'], 404);
        }

        return $this->json($presentations[$id]);
    }

    #[Route('/presentation/{id}/update', methods: ['POST'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $presentations = $this->loadPresentations();

        if (!isset($presentations[$id])) {
            return $this->json(['error' => 'Presentation not found'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        if ($payload) {
            $presentations[$id] = array_merge($presentations[$id], $payload);
            $this->savePresentations($presentations);
        }

        return $this->json(['status' => 'updated']);
    }
}

