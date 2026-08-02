<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Models\User;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Repositories\UserRepository;

final readonly class AuthService
{
    private const SESSION_USER_ID = 'user_id';
    private const SESSION_USER_CODE = 'user_code';
    private const SESSION_TENANT_ID = 'tenant_id';
    private const SESSION_TENANT_NAME = 'tenant_name';
    private const SESSION_LOGIN = 'login';
    private const SESSION_NAME = 'name';
    private const SESSION_TYPE = 'type';

    public function __construct(
        private ?TenantRepository $tenants = null,
        private ?UserRepository $users = null,
    ) {
    }

    public function attempt(string $tenantSlug, string $login, string $password): bool
    {
        $this->ensureSessionStarted();

        if (!$this->tenants instanceof TenantRepository || !$this->users instanceof UserRepository) {
            return false;
        }

        $tenant = $this->tenants->findActiveBySlug($tenantSlug);

        if (!$tenant instanceof Tenant) {
            return false;
        }

        $user = $this->users->findActiveByTenantAndLogin($tenant->id, $login);

        if (!$user instanceof User || !password_verify($password, $user->password)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $user->id;
        $_SESSION[self::SESSION_USER_CODE] = $user->code;
        $_SESSION[self::SESSION_TENANT_ID] = $tenant->id;
        $_SESSION[self::SESSION_TENANT_NAME] = $tenant->name;
        $_SESSION[self::SESSION_LOGIN] = $user->login;
        $_SESSION[self::SESSION_NAME] = $user->name;
        $_SESSION[self::SESSION_TYPE] = $user->type;

        return true;
    }

    public function logout(): void
    {
        $this->ensureSessionStarted();

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();

            if (ini_get('session.use_cookies')) {
                setcookie(
                    session_name(),
                    '',
                    [
                        'expires' => time() - 42000,
                        'path' => $params['path'],
                        'domain' => $params['domain'],
                        'secure' => $params['secure'],
                        'httponly' => $params['httponly'],
                        'samesite' => $params['samesite'] ?? 'Lax',
                    ],
                );
            }

            session_destroy();
        }
    }

    public function userId(): ?int
    {
        $this->ensureSessionStarted();

        $userId = $_SESSION[self::SESSION_USER_ID] ?? null;

        if (!is_int($userId)) {
            return null;
        }

        return $userId;
    }

    public function check(): bool
    {
        return $this->userId() !== null;
    }

    public function tenantId(): ?int
    {
        $this->ensureSessionStarted();

        $tenantId = $_SESSION[self::SESSION_TENANT_ID] ?? null;

        if (!is_int($tenantId)) {
            return null;
        }

        return $tenantId;
    }

    /**
     * @return array<string, mixed>
     */
    public static function sessionUser(): array
    {
        self::ensureStaticSessionStarted();

        return [
            'id' => $_SESSION[self::SESSION_USER_ID] ?? null,
            'code' => $_SESSION[self::SESSION_USER_CODE] ?? '',
            'login' => $_SESSION[self::SESSION_LOGIN] ?? '',
            'name' => $_SESSION[self::SESSION_NAME] ?? '',
            'type' => $_SESSION[self::SESSION_TYPE] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sessionTenant(): array
    {
        self::ensureStaticSessionStarted();

        return [
            'id' => $_SESSION[self::SESSION_TENANT_ID] ?? null,
            'name' => $_SESSION[self::SESSION_TENANT_NAME] ?? '',
        ];
    }

    private function ensureSessionStarted(): void
    {
        self::ensureStaticSessionStarted();
    }

    private static function ensureStaticSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
