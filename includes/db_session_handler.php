<?php
// Database-backed PHP session storage.
//
// PHP's default session handler writes session data to local disk
// (sys_get_temp_dir()). On Vercel, each request can land on a different
// (or freshly recycled) serverless container with its own ephemeral
// filesystem, so a session file written on one request may simply not
// exist when the next request arrives — session_start() then silently
// starts a brand-new, empty session instead of erroring, which is
// indistinguishable from being logged out. Storing sessions in the
// database (already durable and shared across every container) fixes
// this at the source.
class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $maxLifetime;

    public function __construct(PDO $pdo, int $maxLifetime)
    {
        $this->pdo = $pdo;
        $this->maxLifetime = $maxLifetime > 0 ? $maxLifetime : 1440;
    }

    public function open($path, $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        $stmt = $this->pdo->prepare("SELECT `data` FROM `sessions` WHERE `id` = ? AND `last_activity` > ?");
        $stmt->execute([$id, time() - $this->maxLifetime]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }

    public function write($id, $data): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `sessions` (`id`, `data`, `last_activity`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `last_activity` = VALUES(`last_activity`)"
        );
        return $stmt->execute([$id, $data, time()]);
    }

    public function destroy($id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `sessions` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }

    public function gc($max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare("DELETE FROM `sessions` WHERE `last_activity` <= ?");
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
