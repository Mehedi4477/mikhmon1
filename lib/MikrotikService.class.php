<?php

class MikrotikService
{
    private ?RouterosAPI $api = null;
    private ?string $activeSession = null;
    private string $sessionName;
    private string $ipHost;
    private string $userHost;
    private string $passHost;

    public function __construct(string $sessionName)
    {
        $this->sessionName = $sessionName;
        if (!isset($GLOBALS['data'][$sessionName])) {
            throw new RuntimeException("Session {$sessionName} not configured in include/config.php");
        }

        $row = $GLOBALS['data'][$sessionName];
        $this->ipHost = explode('!', $row[1])[1] ?? '';
        $this->userHost = explode('@|@', $row[2])[1] ?? '';
        $this->passHost = explode('#|#', $row[3])[1] ?? '';

        if ($this->ipHost === '' || $this->userHost === '' || $this->passHost === '') {
            throw new RuntimeException("Incomplete MikroTik credential for session {$sessionName}");
        }
    }

    private function ensureConnected(): void
    {
        if ($this->api !== null && $this->api->connected) {
            return;
        }

        $api = new RouterosAPI();
        $api->debug = false;
        if (!$api->connect($this->ipHost, $this->userHost, decrypt($this->passHost))) {
            throw new RuntimeException('Failed to connect to MikroTik router');
        }

        $this->api = $api;
    }

    public function setPppProfile(string $username, string $profileName): bool
    {
        $this->ensureConnected();

        $this->api->write(
            '/ppp/secret/set',
            false
        );
        $this->api->write('=name=' . $username, false);
        $this->api->write('=profile=' . $profileName, true);
        $response = $this->api->read();

        return !isset($response['!trap']);
    }

    public function dropActiveSession(string $username): void
    {
        $this->ensureConnected();

        $this->api->write('/ppp/active/print', false);
        $this->api->write('?name=' . $username, true);
        $active = $this->api->read();

        if (!is_array($active)) {
            return;
        }

        foreach ($active as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = $entry['.id'] ?? null;
            if (!$id) {
                continue;
            }
            $this->api->write('/ppp/active/remove', false);
            $this->api->write('=.id=' . $id, true);
            $this->api->read();
        }
    }

    public function disconnect(): void
    {
        if ($this->api && $this->api->connected) {
            $this->api->disconnect();
        }
        $this->api = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
