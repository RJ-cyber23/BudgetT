<?php

namespace App\Controller;

use App\Entity\UsersEntity;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

trait CurrentUserTrait
{
    private function currentUser(): UsersEntity
    {
        $user = $this->getUser();

        if (!$user instanceof UsersEntity) {
            throw new AccessDeniedException('You must be signed in to do that.');
        }

        return $user;
    }

    private function currentUserId(): int
    {
        $userId = $this->currentUser()->getUserId();

        if (null === $userId) {
            throw new AccessDeniedException('Your account is not ready yet.');
        }

        return $userId;
    }
}
