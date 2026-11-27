<?php

namespace App\Contracts;

use App\Data\AiProviders\AiChatMessage;
use App\Data\AiProviders\AiReply;
use App\Models\AiConnection;

/**
 * Port for sending a chat turn to a user-configured AI provider.
 *
 * Implementations stay free of HTTP transport details and must not leak
 * credentials in exceptions or logs. Callers should run requests in the
 * background and avoid assuming synchronous completion inside an HTTP
 * request: a future local companion provider (#16) may dispatch work to
 * a paired desktop agent and complete replies asynchronously.
 */
interface AiProvider
{
    /**
     * @param  list<AiChatMessage>  $messages
     */
    public function chat(AiConnection $connection, array $messages): AiReply;
}
