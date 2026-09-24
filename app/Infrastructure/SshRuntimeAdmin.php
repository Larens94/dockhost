<?php

namespace App\Infrastructure;

use App\Contracts\RuntimeAdmin;
use App\Models\Pool;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Creates an SFTP user only when the storage pool has operator-supplied SSH credentials.
 */
class SshRuntimeAdmin implements RuntimeAdmin
{
    public function canManage(Pool $pool): bool
    {
        $connection = $pool->adminConnection();

        return ($connection['ssh_host'] ?? '') !== ''
            && ($connection['ssh_username'] ?? '') !== '';
    }

    public function createSftpUser(Pool $pool, string $username, string $password, string $chroot): void
    {
        if (! $this->canManage($pool)) {
            throw new RuntimeException('Storage pool has no SSH credentials.');
        }

        $this->run($pool, $this->script($username, $password, $chroot));
    }

    private function script(string $username, string $password, string $chroot): string
    {
        $user = $this->assertUser($username);
        $path = $this->assertPath($chroot);
        $secret = str_replace("'", "'\\''", $password);

        return implode("\n", [
            "id -u {$user} >/dev/null 2>&1 || useradd --no-create-home --shell /usr/sbin/nologin {$user}",
            "mkdir -p {$path}/files",
            "chown root:root {$path}",
            "chmod 755 {$path}",
            "chown {$user}:{$user} {$path}/files",
            "printf '%s:%s\\n' '{$user}' '{$secret}' | chpasswd",
        ]);
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function run(Pool $pool, string $script): void
    {
        $connection = $pool->adminConnection();
        $keyPath = null;

        try {
            $command = [
                'ssh',
                '-o', 'BatchMode=yes',
                '-o', 'StrictHostKeyChecking=accept-new',
                '-p', (string) ($connection['ssh_port'] ?: 22),
            ];

            $key = $connection['ssh_private_key'] ?? null;

            if (is_string($key) && trim($key) !== '') {
                $keyPath = tempnam(sys_get_temp_dir(), 'dockhost-ssh-');

                if ($keyPath === false) {
                    throw new RuntimeException('Could not store the SSH key.');
                }

                file_put_contents($keyPath, $key);
                chmod($keyPath, 0600);
                $command[] = '-i';
                $command[] = $keyPath;
            }

            $command[] = $connection['ssh_username'].'@'.$connection['ssh_host'];
            $command[] = 'bash -s';

            $process = new Process($command);
            $process->setInput($script);
            $process->setTimeout(20);
            $process->mustRun();
        } finally {
            if (is_string($keyPath) && is_file($keyPath)) {
                unlink($keyPath);
            }
        }
    }

    private function assertUser(string $username): string
    {
        if (! preg_match('/^[a-z][a-z0-9_]{0,31}$/', $username)) {
            throw new RuntimeException('Unsafe SFTP username.');
        }

        return $username;
    }

    private function assertPath(string $path): string
    {
        if (! preg_match('#^/var/sites/[0-9]+$#', $path)) {
            throw new RuntimeException('Unsafe SFTP path.');
        }

        return $path;
    }
}
