<?php

namespace PerficientTest\ChainOfResponsibility;

abstract class Middleware
{
    /**
     * @var Middleware
     */
    private $next;

    public function linkWith(Middleware $next): Middleware
    {
        $this->next = $next;

        return $next;
    }

    public function check(string $email, string $password): bool
    {
        if (!$this->next) {
            return true;
        }

        return $this->next->check($email, $password);
    }
}

class UserExistsMiddleware extends Middleware
{
    private $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function check(string $email, string $password): bool
    {
        if (!$this->server->hasEmail($email)) {
            echo "UserExistsMiddleware: This email is not registered!\n";

            return false;
        }

        if (!$this->server->isValidPassword($email, $password)) {
            echo "UserExistsMiddleware: Wrong password!\n";

            return false;
        }

        return parent::check($email, $password);
    }
}

class RoleCheckMiddleware extends Middleware
{
    public function check(string $email, string $password): bool
    {
        if ($email === "admin@exmaple.com") {
            echo "RoleCheckMiddleware: Hello, admin!\n";

            return true;
        }
        echo "RoleCheckMiddleware: Hello, user!\n";

        return parent::check($email, $password);
    }
}

class ThrottlingMiddleware extends Middleware
{
    private $requestPerMinute;

    private $request;

    private $currentTime;

    public function __construct(int $requestPerMinute)
    {
        $this->requestPerMinute = $requestPerMinute;
        $this->currentTime = time();
    }

    public function check(string $email, string $password): bool
    {
        if (time() > $this->currentTime + 60) {
            $this->request = 0;
            $this->currentTime = time();
        }

        $this->request++;

        if ($this->request > $this->requestPerMinute) {
            echo "ThrottlingMiddleware: Request limit exceeded!\n";
            die();
        }

        return parent::check($email, $password);
    }
}

class Server
{
    private $users = [];

    /**
     * @var Middleware
     */
    private $middleware;

    public function setMiddleware(Middleware $middleware): void
    {
        $this->middleware = $middleware;
    }

    public function logIn(string $email, string $password): bool
    {
        if (!$this->middleware->check($email, $password)) {
            echo "Server: Authorization has been successful!\n";

            return true;
        }

        return false;
    }

    public function register(string $email, string $password): void
    {
        $this->users[$email] = $password;
    }

    public function hasEmail(string $email): bool
    {
        return isset($this->users[$email]);
    }

    public function isValidPassword(string $email, string $password): bool
    {
        return $this->users[$email] === $password;
    }
}

$server = new Server();
$server->register("admin@example.com", "admin_pass");
$server->register("user@example.com", "user_pass");

$middleware = new ThrottlingMiddleware(2);
$middleware
    ->linkWith(new UserExistsMiddleware($server))
    ->linkWith(new RoleCheckMiddleware());

$server->setMiddleware($middleware);

do {
    echo "\nEnter your email:\n";
    $email = readline();
    echo "Enter your password:\n";
    $password = readline();
    $success = $server->logIn($email, $password);
} while (!$success);
