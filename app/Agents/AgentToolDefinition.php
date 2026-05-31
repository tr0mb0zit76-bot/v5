<?php

namespace App\Agents;

use App\Models\User;
use Closure;

final class AgentToolDefinition
{
    /**
     * @param  array<string, mixed>  $parameters  JSON Schema object (type object, properties, required)
     * @param  Closure(User): bool  $canUse
     * @param  Closure(User, array<string, mixed>): array<string, mixed>  $invoke
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly Closure $canUse,
        public readonly Closure $invoke,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function openAiDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
            ],
        ];
    }
}
