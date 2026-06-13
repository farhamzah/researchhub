<?php

namespace App\Modules\Projects\Services;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class ProjectTemplateCatalogService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        return collect(config('myriset_project_templates', []))
            ->map(fn (array $template, string $key): array => $this->normalize($key, $template))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $key): array
    {
        $template = config("myriset_project_templates.{$key}");

        if (! is_array($template)) {
            throw new InvalidArgumentException('Project template not found.');
        }

        return $this->normalize($key, $template);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(string $key, array $template): array
    {
        return [
            'key' => $key,
            'name' => (string) $template['name'],
            'default_title' => (string) $template['default_title'],
            'description' => (string) $template['description'],
            'best_for' => (string) $template['best_for'],
            'duration_days' => (int) ($template['duration_days'] ?? 120),
            'milestones' => array_values($template['milestones'] ?? []),
            'tasks' => array_values($template['tasks'] ?? []),
            'documents' => array_values($template['documents'] ?? []),
            'survey' => $template['survey'] ?? null,
            'research_links' => array_values($template['research_links'] ?? []),
        ];
    }
}
