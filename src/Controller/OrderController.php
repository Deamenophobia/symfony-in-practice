<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\PlaceOrder;
use App\Dto\CreateOrder;
use App\Entity\Order;
use App\Security\ApiUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class OrderController extends AbstractController
{
    #[Route('/api/orders', name: 'orders_create', methods: ['POST'], format: 'json')]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json', serializationContext: ['allow_extra_attributes' => false])]
        CreateOrder $input,
        #[CurrentUser] ApiUser $user,
        PlaceOrder $placeOrder,
    ): JsonResponse {
        if (!$user->can('orders:write')) {
            throw $this->createAccessDeniedException();
        }

        return $this->json(['data' => $placeOrder->place($user->accountId, $input)->view()], 201);
    }

    #[Route('/api/orders/{id}', name: 'orders_view', requirements: ['id' => '\\d+'], methods: ['GET'], format: 'json')]
    public function view(int $id, EntityManagerInterface $entities): JsonResponse
    {
        $order = $entities->find(Order::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('ORDER_VIEW', $order);

        return $this->json(['data' => $order->view()]);
    }
}
