<?php

namespace App\Data\Projects;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;

readonly class ProjectData
{
    public const string DEFAULT_ICON = 'folder';

    public const string DEFAULT_TYPE = ProjectType::Personal->value;

    public const string DEFAULT_STATUS = ProjectStatus::Active->value;

    public function __construct(
        public string $name,
        public ?string $description = null,
        public string $icon = self::DEFAULT_ICON,
        public string $type = self::DEFAULT_TYPE,
        public string $status = self::DEFAULT_STATUS,
        public ?string $dueAt = null,
        public ?string $archivedAt = null,
    ) {}
}
