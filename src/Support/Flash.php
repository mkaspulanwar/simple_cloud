<?php
declare(strict_types=1);

namespace App\Support;

final class Flash
{
    private const SESSION_KEY = '_flash_messages';

    public static function add(string $type, string $title, string $description = ''): void
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $_SESSION[self::SESSION_KEY][] = [
            'type' => $type,
            'title' => $title,
            'description' => $description,
        ];
    }

    /**
     * @return array<int, array{type:string,title:string,description:string}>
     */
    public static function pull(): array
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return [];
        }

        $messages = $_SESSION[self::SESSION_KEY];
        unset($_SESSION[self::SESSION_KEY]);

        return $messages;
    }
}
