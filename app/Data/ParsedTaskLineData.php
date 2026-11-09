<?php

namespace App\Data;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Livewire\Wireable;

readonly class ParsedTaskLineData implements Wireable
{
    /**
     * @param  array<string>  $errors
     * @param  array<string>  $warnings
     */
    public function __construct(
        public string $rawLine,
        public string $title,
        public string $criterion,
        public ?string $dependency = null,
        public TaskStatus $status = TaskStatus::Todo,
        public TaskPriority $priority = TaskPriority::Low,
        public ?string $areaTag = null,
        public ?int $areaId = null,
        public ?string $areaName = null,
        public ?string $areaColor = null,
        public array $errors = [],
        public array $warnings = [],
    ) {}

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    public function description(): string
    {
        if (filled($this->dependency)) {
            return $this->criterion."\n\nDepende de: {$this->dependency}";
        }

        return $this->criterion;
    }

    public function toLivewire(): array
    {
        return [
            'rawLine' => $this->rawLine,
            'title' => $this->title,
            'criterion' => $this->criterion,
            'dependency' => $this->dependency,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'areaTag' => $this->areaTag,
            'areaId' => $this->areaId,
            'areaName' => $this->areaName,
            'areaColor' => $this->areaColor,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * @param  array{
     *     rawLine?: string,
     *     title?: string,
     *     criterion?: string,
     *     dependency?: ?string,
     *     status: string,
     *     priority: string,
     *     areaTag?: ?string,
     *     areaId?: ?int,
     *     areaName?: ?string,
     *     areaColor?: ?string,
     *     errors?: array<string>,
     *     warnings?: array<string>
     * }|mixed  $value
     */
    public static function fromLivewire(mixed $value): static
    {
        return new self(
            rawLine: $value['rawLine'] ?? '',
            title: $value['title'] ?? '',
            criterion: $value['criterion'] ?? '',
            dependency: $value['dependency'] ?? null,
            status: TaskStatus::from($value['status']),
            priority: TaskPriority::from($value['priority']),
            areaTag: $value['areaTag'] ?? null,
            areaId: $value['areaId'] ?? null,
            areaName: $value['areaName'] ?? null,
            areaColor: $value['areaColor'] ?? null,
            errors: $value['errors'] ?? [],
            warnings: $value['warnings'] ?? [],
        );
    }

    public function toArray(): array
    {
        return $this->toLivewire();
    }
}
