<?php

namespace App\Services;

use BadMethodCallException;

abstract class BaseService
{
    /**
     * The resolved repository instance (cached after first resolution).
     */
    private ?object $resolvedRepository = null;

    /**
     * Override in child class to set an explicit repository class.
     * When null, it is resolved by convention: FooService -> FooRepository.
     */
    protected ?string $repositoryClass = null;

    /**
     * Delegate method calls that do not exist on the service to the
     * corresponding repository (matched by naming convention or explicit override).
     */
    public function __call(string $method, array $arguments): mixed
    {
        $repository = $this->resolveRepository();

        if ($repository && method_exists($repository, $method)) {
            return $repository->{$method}(...$arguments);
        }

        throw new BadMethodCallException(
            sprintf(
                'Method %s::%s does not exist on the service or its repository.',
                static::class,
                $method
            )
        );
    }

    /**
     * Resolve the repository instance by explicit class or naming convention.
     *
     * Convention: Modules\Auth\Services\UserService -> Modules\Auth\Repositories\UserRepository
     */
    private function resolveRepository(): ?object
    {
        if ($this->resolvedRepository !== null) {
            return $this->resolvedRepository;
        }

        $repositoryClass = $this->repositoryClass ?? $this->guessRepositoryClass();

        if ($repositoryClass && class_exists($repositoryClass)) {
            $this->resolvedRepository = app($repositoryClass);

            return $this->resolvedRepository;
        }

        return null;
    }

    /**
     * Guess the repository class from the service class name.
     *
     * Replaces "Services" namespace segment with "Repositories" and
     * "Service" suffix with "Repository".
     */
    private function guessRepositoryClass(): ?string
    {
        $serviceClass = static::class;

        if (! str_ends_with($serviceClass, 'Service')) {
            return null;
        }

        $repositoryClass = preg_replace('/\\\\Services\\\\/', '\\Repositories\\', $serviceClass, 1);
        $repositoryClass = preg_replace('/Service$/', 'Repository', $repositoryClass);

        return $repositoryClass;
    }
}
