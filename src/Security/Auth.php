<?php
declare(strict_types=1);

namespace Cms\Security;

use Cms\Repositories\AdminRepository;

final class Auth
{
    private ?array $cachedUser = null;

    public function __construct(private AdminRepository $repository)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->repository->findUserByEmail($email);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        Session::regenerate();
        Session::put('auth_user_id', (int) $user['id']);
        $this->cachedUser = $user;

        return true;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $userId = (int) Session::get('auth_user_id', 0);

        if ($userId <= 0) {
            return null;
        }

        $user = $this->repository->findUserById($userId);

        if ($user === null) {
            Session::forget('auth_user_id');

            return null;
        }

        $this->cachedUser = $user;

        return $this->cachedUser;
    }

    public function logout(): void
    {
        $this->cachedUser = null;
        Session::forget('auth_user_id');
        Session::regenerate();
    }
}
