<?php

namespace App\Support;

class MailTransport
{
    /** @param list<string> $visited */
    public static function usesLog(?string $mailer = null, array $visited = []): bool
    {
        $mailer ??= (string) config('mail.default');
        if (in_array($mailer, $visited, true)) {
            return false;
        }

        $transport = config("mail.mailers.{$mailer}.transport");
        if ($transport === 'log') {
            return true;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = config("mail.mailers.{$mailer}.mailers", []);
            if (is_array($children)) {
                foreach ($children as $child) {
                    if (is_string($child) && self::usesLog($child, [...$visited, $mailer])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
