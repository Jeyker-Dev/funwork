<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head', ['title' => 'Privacy Policy'])
</head>
<body class="min-h-screen bg-gray-100 antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
<main class="mx-auto max-w-3xl px-6 py-12 space-y-6 text-zinc-700 dark:text-zinc-300">
    <flux:heading size="xl" level="1">Privacy Policy</flux:heading>
    <flux:text>Last updated: November 6, 2026</flux:text>

    <flux:text>
        FunWork is a personal project management app. This page explains what data FunWork stores and how it uses
        your Google account when you connect it.
    </flux:text>

    <flux:heading size="lg" level="2">Data you store in FunWork</flux:heading>
    <flux:text>
        Your account details (name, email, password hash, timezone, avatar) and the content you create: projects,
        tasks, notebooks, inbox items and files. This data is only visible to you.
    </flux:text>

    <flux:heading size="lg" level="2">Google account</flux:heading>
    <flux:text>Connecting Google is optional. When you connect it, FunWork:</flux:text>
    <ul class="list-disc space-y-2 pl-6 text-sm">
        <li>Reads your Google account ID and email address, to show which account is connected.</li>
        <li>
            Requests only the <code>drive.file</code> scope: it can access only the Google Docs that FunWork itself
            creates in your Drive. It cannot see, read or change any other file in your Drive.
        </li>
        <li>Uses that access only to create and update the Google Doc of each notebook you choose to sync.</li>
        <li>Stores the access and refresh tokens encrypted in its database.</li>
    </ul>
    <flux:text>
        FunWork does not sell, share or transfer Google user data to third parties, does not use it for advertising,
        and does not use it to train AI models. Its use of information received from Google APIs adheres to the
        <flux:link href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank">
            Google API Services User Data Policy</flux:link>, including the Limited Use requirements.
    </flux:text>

    <flux:heading size="lg" level="2">Disconnecting and deleting data</flux:heading>
    <flux:text>
        You can disconnect Google at any time in
        <flux:link :href="route('integrations.edit')">Settings → Integrations</flux:link>. FunWork revokes its
        access and deletes the stored tokens. The Google Docs already created stay in your Drive and are yours to
        keep or delete. You can also remove the access from your
        <flux:link href="https://myaccount.google.com/permissions" target="_blank">Google account permissions</flux:link>.
        Deleting your FunWork account deletes your data, including the Google connection.
    </flux:text>

    <flux:heading size="lg" level="2">Contact</flux:heading>
    <flux:text>
        Questions about this policy: <flux:link href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</flux:link>
    </flux:text>
</main>
@fluxScripts
</body>
</html>
