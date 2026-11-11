<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('it can be created using factory', function () {
    $task = Task::factory()->create();

    expect($task)->toBeInstanceOf(Task::class)
        ->and($task->title)->not->toBeEmpty()
        ->and($task->status)->toBeInstanceOf(TaskStatus::class)
        ->and($task->priority)->toBeInstanceOf(TaskPriority::class);
});

test('it belongs to a project', function () {
    $task = Task::factory()->create();

    expect($task->project)->toBeInstanceOf(Project::class);
});

test('it can have multiple assigned users', function () {
    $task = Task::factory()->create();
    $users = User::factory()->count(2)->create();

    $task->users()->attach($users);

    expect($task->users)->toHaveCount(2);
    expect($task->users->first())->toBeInstanceOf(User::class);
});

test('normalization migration converts lowercase status and priority values to enum values', function () {
    $project = Project::factory()->create();

    $todoLowId = DB::table('tasks')->insertGetId([
        'title' => 'Legacy Todo Low',
        'description' => 'Legacy task',
        'status' => 'todo',
        'priority' => 'low',
        'project_id' => $project->id,
        'position' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $doingMediumId = DB::table('tasks')->insertGetId([
        'title' => 'Legacy Doing Medium',
        'description' => 'Legacy task',
        'status' => 'doing',
        'priority' => 'medium',
        'project_id' => $project->id,
        'position' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $doneHighId = DB::table('tasks')->insertGetId([
        'title' => 'Legacy Done High',
        'description' => 'Legacy task',
        'status' => 'done',
        'priority' => 'high',
        'project_id' => $project->id,
        'position' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_11_02_151329_normalize_task_status_and_priority_values.php');
    $migration->up();

    expect(DB::table('tasks')->where('id', $todoLowId)->first())
        ->status->toBe('Todo')
        ->priority->toBe('Low');

    expect(DB::table('tasks')->where('id', $doingMediumId)->first())
        ->status->toBe('Doing')
        ->priority->toBe('Medium');

    expect(DB::table('tasks')->where('id', $doneHighId)->first())
        ->status->toBe('Done')
        ->priority->toBe('High');

    $migration->down();

    expect(DB::table('tasks')->where('id', $todoLowId)->first())
        ->status->toBe('Todo')
        ->priority->toBe('Low');
});

test('description_html renders markdown headings, lists, tables, and fenced code blocks', function () {
    $markdown = <<<'MD'
    # Main Heading
    Paragraph with **bold text** and `inline code`.
    - Bullet one
    - Bullet two

    ```php
    echo 'hello world';
    ```

    | Col A | Col B |
    |---|---|
    | Val 1 | Val 2 |
    MD;

    $task = Task::factory()->create(['description' => $markdown]);

    $html = (string) $task->description_html;

    expect($html)
        ->toContain('<h1>Main Heading</h1>')
        ->toContain('<strong>bold text</strong>')
        ->toContain('<code>inline code</code>')
        ->toContain('<li>Bullet one</li>')
        ->toContain('language-php')
        ->toContain("echo 'hello world';")
        ->toContain('<table>')
        ->toContain('<th>Col A</th>')
        ->toContain('<td>Val 1</td>');
});

test('description_html returns empty html string when description is null or blank', function () {
    $nullTask = Task::factory()->create(['description' => null]);
    $emptyTask = Task::factory()->create(['description' => '   ']);

    expect((string) $nullTask->description_html)->toBe('')
        ->and((string) $emptyTask->description_html)->toBe('');
});

test('description_excerpt strips markdown syntax and tags into a squished plain text string', function () {
    $markdown = <<<'MD'
    # Heading
    This has **bold text**, `code`, and a [link](https://example.com).
    - First item
    - Second item

    ```
    fenced block
    ```
    MD;

    $task = Task::factory()->create(['description' => $markdown]);

    expect($task->description_excerpt)
        ->toBe('Heading This has bold text, code, and a link. First item Second item fenced block')
        ->not->toContain('#')
        ->not->toContain('**')
        ->not->toContain('```')
        ->not->toContain('<h1>')
        ->not->toContain('<p>');
});

test('description_excerpt returns empty string when description is null or blank', function () {
    $nullTask = Task::factory()->create(['description' => null]);
    $emptyTask = Task::factory()->create(['description' => '   ']);

    expect($nullTask->description_excerpt)->toBe('')
        ->and($emptyTask->description_excerpt)->toBe('');
});

test('description_excerpt truncates a 1000-character description to at most 203 characters', function () {
    $longDescription = str_repeat('A very long task description sentence. ', 30);
    $task = Task::factory()->create(['description' => $longDescription]);

    expect(mb_strlen($task->description_excerpt))->toBeLessThanOrEqual(203)
        ->and($task->description_excerpt)->toEndWith('...');
});

test('description_html strips raw script tags to prevent XSS', function () {
    $task = Task::factory()->create([
        'description' => "Malicious <script>alert('xss')</script> text",
    ]);

    expect((string) $task->description_html)
        ->not->toContain('<script>')
        ->not->toContain('</script>')
        ->toContain('Malicious');
});

test('description_html neutralizes javascript and unsafe link schemes', function () {
    $task = Task::factory()->create([
        'description' => "[Click me](javascript:alert('xss')) and [Data](data:text/html;base64,PHNjcmlwdD4=) and [Safe](https://example.com)",
    ]);

    $html = (string) $task->description_html;

    expect($html)
        ->not->toContain('href="javascript:')
        ->not->toContain('href="data:')
        ->toContain('href="https://example.com"');
});

test('description_html strips inline event handlers and malicious HTML tags', function () {
    $task = Task::factory()->create([
        'description' => '<img src="x" onerror="alert(1)"> <div onmouseover="alert(2)">Hello</div>',
    ]);

    $html = (string) $task->description_html;

    expect($html)
        ->not->toContain('onerror')
        ->not->toContain('onmouseover')
        ->not->toContain('<img')
        ->toContain('Hello');
});
