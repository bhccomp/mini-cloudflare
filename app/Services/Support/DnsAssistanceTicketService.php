<?php

namespace App\Services\Support;

use App\Models\Site;
use App\Models\User;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use Illuminate\Support\Arr;
use RuntimeException;

class DnsAssistanceTicketService
{
    public function create(
        Site $site,
        User $user,
        string $registrarUrl,
        string $registrarUsername,
        string $registrarPassword,
        bool $requiresTwoFactor,
    ): Ticket {
        $department = Department::query()
            ->where('is_active', true)
            ->where('name', 'Technical Support')
            ->first();

        if (! $department) {
            throw new RuntimeException('Technical Support department is not available right now.');
        }

        $form = $department->forms()->with('fields')->first();

        if (! $form) {
            throw new RuntimeException('Technical Support intake form is not configured.');
        }

        $defaultStatus = TicketStatus::query()
            ->where('is_default_for_new', true)
            ->first();

        $customFields = [];

        foreach ($form->fields as $field) {
            $name = (string) $field->name;

            $customFields[$name] = match ($name) {
                'Subject' => 'DNS Assistance Request',
                'Website / Domain' => $site->apex_domain,
                'What’s happening?' => $this->buildMessage($site, $registrarUrl, $registrarUsername, $registrarPassword, $requiresTwoFactor),
                'Priority' => TicketPriority::MEDIUM->value,
                default => Arr::get($customFields, $name),
            };
        }

        return Ticket::query()->create([
            'department_id' => $department->id,
            'form_id' => $form->id,
            'custom_fields' => $customFields,
            'user_id' => $user->id,
            'ticket_status_id' => $defaultStatus?->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);
    }

    private function buildMessage(
        Site $site,
        string $registrarUrl,
        string $registrarUsername,
        string $registrarPassword,
        bool $requiresTwoFactor,
    ): string {
        $recordLines = collect($site->required_dns_records ?? [])
            ->flatten(1)
            ->filter(fn ($record): bool => is_array($record))
            ->map(function (array $record): string {
                $host = (string) data_get($record, 'name', data_get($record, 'host', ''));
                $type = (string) data_get($record, 'type', 'DNS');
                $value = (string) data_get($record, 'value', '');
                $ttl = (string) data_get($record, 'ttl', 'Auto');

                return trim("{$type} {$host} -> {$value} (TTL: {$ttl})");
            })
            ->filter()
            ->values();

        $instructions = [
            "Please handle DNS onboarding for {$site->apex_domain}.",
            '',
            'Registrar access details:',
            "Registrar URL: {$registrarUrl}",
            "Username: {$registrarUsername}",
            "Password: {$registrarPassword}",
            '2FA required at login: '.($requiresTwoFactor ? 'Yes' : 'No'),
            '',
            'Requested work:',
            '- Sign in to the registrar or DNS provider account.',
            '- Review the current DNS zone for this domain.',
            '- Add or update the DNS records shown in the FirePhage Site Status Hub.',
            '- Confirm once the DNS changes are saved so FirePhage can continue verification and cutover.',
        ];

        if ($recordLines->isNotEmpty()) {
            $instructions[] = '';
            $instructions[] = 'Current DNS instructions from FirePhage:';

            foreach ($recordLines as $line) {
                $instructions[] = '- '.$line;
            }
        }

        return implode("\n", $instructions);
    }
}
