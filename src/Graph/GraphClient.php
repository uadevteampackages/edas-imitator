<?php

namespace Uadevteampackages\Imitator\Graph;

use Illuminate\Support\Facades\Http;

class GraphClient
{
    public function getBearerToken(): ?string
    {
        $tenantId = config('imitator.azure.tenant_id');
        $clientId = config('imitator.azure.client_id');
        $clientSecret = config('imitator.azure.client_secret');

        if (! $tenantId || ! $clientId || ! $clientSecret) {
            return null;
        }

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ],
        );

        if ($response->successful() && isset($response['access_token'])) {
            return $response['access_token'];
        }

        return null;
    }

    /**
     * @return array{value: list<array<string, mixed>>}|null
     */
    public function searchUsers(string $searchTerm, ?string $accessToken = null): ?array
    {
        $accessToken ??= $this->getBearerToken();

        if (! $accessToken) {
            return null;
        }

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'ConsistencyLevel' => 'eventual',
            ])
            ->get('https://graph.microsoft.com/v1.0/users', [
                '$search' => '"displayName:'.$searchTerm.'" OR "userPrincipalName:'.$searchTerm.'"',
                '$filter' => $this->jobTitleFilter(),
                '$orderby' => 'displayName asc',
                '$top' => 100,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $data['value'] = array_values(array_filter($data['value'] ?? [], function (array $user): bool {
            return $this->passesUpnFilters($user['userPrincipalName'] ?? '');
        }));

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUser(string $id, ?string $accessToken = null): ?array
    {
        $accessToken ??= $this->getBearerToken();

        if (! $accessToken) {
            return null;
        }

        $response = Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/users/'.$id);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    private function jobTitleFilter(): string
    {
        $titles = config('imitator.graph_filters.exclude_job_titles', ['STUDENT']);

        if ($titles === []) {
            return 'accountEnabled eq true';
        }

        $clauses = array_map(
            fn (string $title): string => "jobTitle ne '".str_replace("'", "''", $title)."'",
            $titles,
        );

        return implode(' and ', $clauses);
    }

    private function passesUpnFilters(string $upn): bool
    {
        $suffixes = config('imitator.graph_filters.exclude_upn_suffixes', []);
        $prefixes = config('imitator.graph_filters.exclude_upn_prefixes', []);

        foreach ($suffixes as $suffix) {
            if (str_ends_with($upn, $suffix)) {
                return false;
            }
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($upn, $prefix)) {
                return false;
            }
        }

        return true;
    }
}
