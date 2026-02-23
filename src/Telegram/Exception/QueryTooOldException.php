<?php
namespace App\Telegram\Exception;

use SergiX44\Nutgram\Exception\ApiException;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;

class QueryTooOldException extends ApiException
{
    // capture the "query is too old" error
    public static ?string $pattern = '.*query is too old.*';

    public function __invoke(Nutgram $bot, TelegramException $e)
    {
        // ignore
    }
}
