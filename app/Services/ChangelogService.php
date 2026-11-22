<?php

namespace App\Services;

use App\Data\Changelog\ChangelogEntryData;
use App\Data\Changelog\ChangelogReleaseData;
use App\Enums\ChangelogEntryLabel;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

class ChangelogService
{
    private const string FRONT_MATTER_PATTERN = '/\A---\R(.*?)\R---(?:\R|\z)/s';

    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? resource_path('changelog');
    }

    /**
     * @return Collection<int, ChangelogReleaseData>
     */
    public function releases(?User $user = null, ?CarbonInterface $previousLastSeen = null): Collection
    {
        $parsedFiles = $this->parseFiles();

        if ($parsedFiles->isEmpty()) {
            return new Collection;
        }

        $latestProject = $this->resolveLatestProject($user);

        return $parsedFiles
            ->groupBy(static fn (array $parsed): string => $parsed['date']->toDateString())
            ->map(function (Collection $filesOnDate, string $dateKey) use ($latestProject, $previousLastSeen): ChangelogReleaseData {
                /** @var CarbonImmutable $releaseDate */
                $releaseDate = $filesOnDate->max(static fn (array $parsed): CarbonImmutable => $parsed['date']);

                $entries = $filesOnDate
                    ->flatMap(static fn (array $parsed): array => $parsed['entries'])
                    ->map(fn (array $entry): ChangelogEntryData => $this->buildEntryData($entry, $latestProject))
                    ->values();

                $isNew = $previousLastSeen === null || $releaseDate->gt($previousLastSeen);

                return new ChangelogReleaseData(
                    date: $releaseDate,
                    formattedDate: $releaseDate->format('F j, Y'),
                    isNewSinceLastVisit: $isNew,
                    entries: $entries,
                );
            })
            ->sortByDesc(static fn (ChangelogReleaseData $release): int => $release->date->getTimestamp())
            ->values();
    }

    public function latestReleaseDate(): ?CarbonImmutable
    {
        return $this->parseFiles()
            ->map(static fn (array $parsed): CarbonImmutable => $parsed['date'])
            ->sortByDesc(static fn (CarbonImmutable $date): int => $date->getTimestamp())
            ->first();
    }

    /**
     * @return Collection<int, array{date: CarbonImmutable, entries: array<int, array<string, mixed>>}>
     */
    private function parseFiles(): Collection
    {
        if (! File::isDirectory($this->directory)) {
            return new Collection;
        }

        return collect(File::glob($this->directory.'/*.md'))
            ->sort()
            ->map(fn (string $path): ?array => $this->parseReleaseFile($path))
            ->filter()
            ->values();
    }

    /**
     * @return array{date: CarbonImmutable, entries: array<int, array<string, mixed>>}|null
     */
    private function parseReleaseFile(string $path): ?array
    {
        $contents = File::get($path);

        if (! preg_match(self::FRONT_MATTER_PATTERN, $contents, $matches)) {
            return null;
        }

        $frontMatter = Yaml::parse($matches[1]);

        if (! is_array($frontMatter) || ! isset($frontMatter['date'], $frontMatter['entries']) || ! is_array($frontMatter['entries'])) {
            return null;
        }

        $date = $this->parseDate($frontMatter['date']);

        if ($date === null) {
            return null;
        }

        return [
            'date' => $date,
            'entries' => array_values(array_filter($frontMatter['entries'], 'is_array')),
        ];
    }

    private function parseDate(mixed $rawDate): ?CarbonImmutable
    {
        if ($rawDate instanceof DateTimeInterface) {
            return CarbonImmutable::instance($rawDate);
        }

        if (is_int($rawDate)) {
            return CarbonImmutable::createFromTimestamp($rawDate, 'UTC');
        }

        if (is_string($rawDate) && trim($rawDate) !== '') {
            return CarbonImmutable::parse($rawDate, 'UTC');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rawEntry
     */
    private function buildEntryData(array $rawEntry, ?Project $latestProject): ChangelogEntryData
    {
        $label = ChangelogEntryLabel::tryFrom((string) ($rawEntry['label'] ?? '')) ?? ChangelogEntryLabel::New;
        $routeName = isset($rawEntry['route']) ? (string) $rawEntry['route'] : null;
        $url = $this->resolveRouteUrl($routeName, $latestProject);
        $linkLabel = $url !== null ? (string) ($rawEntry['link_label'] ?? 'Open') : null;

        return new ChangelogEntryData(
            label: $label,
            title: (string) ($rawEntry['title'] ?? ''),
            description: (string) ($rawEntry['description'] ?? ''),
            url: $url,
            linkLabel: $linkLabel,
        );
    }

    private function resolveLatestProject(?User $user): ?Project
    {
        if ($user === null) {
            return null;
        }

        return $user->projects()
            ->latest('updated_at')
            ->first(['id', 'owner_id']);
    }

    private function resolveRouteUrl(?string $routeName, ?Project $latestProject): ?string
    {
        if ($routeName === null || $routeName === '' || ! Route::has($routeName)) {
            return null;
        }

        $route = Route::getRoutes()->getByName($routeName);

        if ($route === null) {
            return null;
        }

        $parameterNames = $route->parameterNames();

        if ($parameterNames === []) {
            return route($routeName);
        }

        if ($parameterNames === ['project'] && $latestProject !== null) {
            return route($routeName, ['project' => $latestProject]);
        }

        return null;
    }
}
